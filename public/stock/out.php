<?php
/**
 * Modul Transaksi Stok - Barang Keluar (Stock Out)
 * Mengimplementasikan ACID Database Transaction, Row Locking FOR UPDATE, dan Validasi Stok Negatif (Slide 26 & 27)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Catat Barang Keluar (Stock OUT)';
$pageSubtitle = 'Pengeluaran barang gudang dengan verifikasi stok tersedia dan penguncian transaksi';
$pageType = 'stock-out';
$baseUrl = getBaseUrl();

$stmtProducts = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.name ASC
");
$products = $stmtProducts->fetchAll();

$preselectedId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid. Silakan coba lagi.';
    } else {
        $productId   = validateInt($_POST['product_id'] ?? 0);
        $quantity    = validateInt($_POST['quantity'] ?? 0, 1); // quantity harus > 0
        $referenceNo = validateString($_POST['reference_no'] ?? '', 0, 50);
        $notes       = trim($_POST['notes'] ?? '');

        if (!$productId) {
            $errors[] = 'Pilih produk yang akan dikeluarkan.';
        }

        if (!$quantity || $quantity <= 0) {
            $errors[] = 'Jumlah barang keluar harus berupa angka bulat lebih dari nol (quantity > 0).';
        }

        if (empty($errors)) {
            // EKSEKUSI DATABASE TRANSACTION (Slide 26 & 27)
            $pdo->beginTransaction();
            try {
                // 1. SELECT FOR UPDATE: Kunci baris produk untuk mencegah race condition / concurrent checkout
                $stmtLock = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $stmtLock->execute(['id' => $productId]);
                $prod = $stmtLock->fetch();

                if (!$prod) {
                    throw new Exception("Produk tidak ditemukan di database.");
                }

                $currentStock = (int)$prod['stock'];

                // 2. Business Logic Validation: Validasi quantity <= stock (Slide 26 & 33)
                if ($quantity > $currentStock) {
                    throw new Exception("Gagal: Jumlah pengeluaran ({$quantity} unit) melebihi stok yang tersedia di gudang ({$currentStock} unit). Stok tidak boleh menjadi negatif.");
                }

                // 3. Kurangi stok produk
                $stmtUpd = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
                $stmtUpd->execute([
                    'qty' => $quantity,
                    'id'  => $productId
                ]);

                // 4. Catat transaksi keluar ke tabel stock_movements
                $stmtMove = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_no, notes)
                    VALUES (:product_id, :user_id, 'OUT', :quantity, :reference_no, :notes)
                ");
                $stmtMove->execute([
                    'product_id'   => $productId,
                    'user_id'      => currentUser()['id'],
                    'quantity'     => $quantity,
                    'reference_no' => $referenceNo ?: ('DO-' . date('Ymd-His')),
                    'notes'        => $notes ?: 'Pengeluaran barang / distribusi'
                ]);

                // 5. Commit transaksi jika semua langkah sukses
                $pdo->commit();

                $remainingStock = $currentStock - $quantity;
                setFlash('success', "Berhasil mengeluarkan {$quantity} unit '{$prod['name']}'. Sisa stok gudang: {$remainingStock} unit.");
                header("Location: history.php");
                exit;

            } catch (Throwable $e) {
                // Rollback transaksi jika validasi gagal atau query error
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <span style="color: var(--danger);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </span>
            Pencatatan Barang Keluar (Distribusi / Pemakaian)
        </h3>
        <a href="history.php" class="btn btn-secondary btn-sm">Lihat Histori</a>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <div>
                    <strong>Validasi Transaksi Gagal:</strong>
                    <ul style="margin-left: 18px; margin-top: 4px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="product_id">Pilih Produk yang Dikeluarkan <span class="required">*</span></label>
                <select id="product_id" name="product_id" class="form-control" required autofocus>
                    <option value="">-- Pilih Produk --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock'] ?>" <?= ((int)($_POST['product_id'] ?? $preselectedId) === (int)$p['id']) ? 'selected' : '' ?>>
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Tersedia: <?= (int)$p['stock'] ?> unit)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Panel Indikator Stok Real-Time -->
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <span style="font-size: 12px; color: var(--text-secondary); display: block;">Stok Tersedia Saat Ini:</span>
                    <span style="font-size: 20px; font-weight: 800; color: var(--text-primary);" id="currentStockPreview">-</span>
                </div>
                <div>
                    <span style="font-size: 12px; color: var(--text-secondary); display: block;">Perkiraan Sisa Setelah Keluar:</span>
                    <span style="font-size: 20px; font-weight: 800; color: #10b981;" id="remainingStockPreview">-</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="quantity">Jumlah Keluar (Unit) <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" min="1" class="form-control" placeholder="Contoh: 3" required value="<?= e($_POST['quantity'] ?? '') ?>">
                    <span class="form-text">Tidak boleh melebihi stok yang ada.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reference_no">Nomor Dokumen / DO / Bukti Serah Terima</label>
                    <input type="text" id="reference_no" name="reference_no" class="form-control" placeholder="Contoh: DO-2026-042" value="<?= e($_POST['reference_no'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Tujuan & Keterangan Pengeluaran</label>
                <textarea id="notes" name="notes" class="form-control" placeholder="Keterangan peruntukan, divisi pemohon, nama penerima, atau tujuan proyek..."><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 24px; font-size: 12.5px; color: #92400e;">
                <strong>Integritas Bisnis (Slide 26 & 33):</strong> Database transaction mengunci baris produk (<code>FOR UPDATE</code>) dan memverifikasi ketersediaan stok fisik sebelum pengurangan dilakukan. Stok dicegah secara mutlak agar tidak pernah bernilai negatif.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="<?= $baseUrl ?>/public/dashboard.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-danger" style="padding: 10px 24px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Konfirmasi Barang Keluar
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
