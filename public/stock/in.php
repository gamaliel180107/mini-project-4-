<?php
/**
 * Modul Transaksi Stok - Barang Masuk (Stock In)
 * Mengimplementasikan ACID Database Transaction & Business Logic (Slide 25 & 27)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Catat Barang Masuk (Stock IN)';
$pageSubtitle = 'Penambahan stok fisik ke gudang dengan pencatatan audit trail otomatis';
$baseUrl = getBaseUrl();

// Ambil daftar produk untuk pilihan select beserta stok saat ini
$stmtProducts = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.name ASC
");
$products = $stmtProducts->fetchAll();

// Preselect jika ada product_id di URL query string
$preselectedId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid. Silakan ulangi pengiriman form.';
    } else {
        $productId   = validateInt($_POST['product_id'] ?? 0);
        $quantity    = validateInt($_POST['quantity'] ?? 0, 1); // quantity harus > 0
        $referenceNo = validateString($_POST['reference_no'] ?? '', 0, 50);
        $notes       = trim($_POST['notes'] ?? '');

        // Validasi Syntactic & Semantic (Slide 21 & 25)
        if (!$productId) {
            $errors[] = 'Pilih produk yang akan ditambahkan stoknya.';
        }

        if (!$quantity || $quantity <= 0) {
            $errors[] = 'Jumlah barang masuk harus berupa angka bulat lebih dari nol (quantity > 0).';
        }

        if (empty($errors)) {
            // EKSEKUSI DATABASE TRANSACTION (Slide 27)
            $pdo->beginTransaction();
            try {
                // 1. Kunci baris produk dengan FOR UPDATE untuk mencegah race condition
                $stmtCheck = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $stmtCheck->execute(['id' => $productId]);
                $prod = $stmtCheck->fetch();

                if (!$prod) {
                    throw new Exception("Produk tidak ditemukan di database.");
                }

                // 2. Tambah stok produk (Slide 25)
                $stmtUpd = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id");
                $stmtUpd->execute([
                    'qty' => $quantity,
                    'id'  => $productId
                ]);

                // 3. Catat histori pergerakan stok ke tabel stock_movements
                $stmtMove = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_no, notes)
                    VALUES (:product_id, :user_id, 'IN', :quantity, :reference_no, :notes)
                ");
                $stmtMove->execute([
                    'product_id'   => $productId,
                    'user_id'      => currentUser()['id'],
                    'quantity'     => $quantity,
                    'reference_no' => $referenceNo ?: ('PO-' . date('Ymd-His')),
                    'notes'        => $notes ?: 'Penerimaan stok masuk'
                ]);

                // Semua query berhasil -> Commit
                $pdo->commit();

                $newStock = (int)$prod['stock'] + $quantity;
                setFlash('success', "Berhasil menambahkan {$quantity} unit ke produk '{$prod['name']}'. Stok sekarang: {$newStock} unit.");
                header("Location: history.php");
                exit;

            } catch (Throwable $e) {
                // Rollback jika terjadi kesalahan agar data tetap konsisten (Slide 27)
                $pdo->rollBack();
                $errors[] = 'Gagal memproses transaksi barang masuk: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <span style="color: var(--success);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </span>
            Pencatatan Barang Masuk (Restock / Supply)
        </h3>
        <a href="history.php" class="btn btn-secondary btn-sm">Lihat Histori</a>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <div>
                    <strong>Gagal memproses formulir:</strong>
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
                <label class="form-label" for="product_id">Pilih Produk <span class="required">*</span></label>
                <select id="product_id" name="product_id" class="form-control" required autofocus>
                    <option value="">-- Pilih Produk yang Masuk --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-stock="<?= (int)$p['stock'] ?>" <?= ((int)($_POST['product_id'] ?? $preselectedId) === (int)$p['id']) ? 'selected' : '' ?>>
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok saat ini: <?= (int)$p['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Preview Stok Saat Ini -->
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 13px; font-weight: 600; color: #065f46;">Stok Fisik di Gudang Saat Ini:</span>
                <span style="font-size: 18px; font-weight: 800; color: #047857;" id="currentStockPreview">-</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="quantity">Jumlah Masuk (Unit) <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" min="1" class="form-control" placeholder="Contoh: 10" required value="<?= e($_POST['quantity'] ?? '') ?>">
                    <span class="form-text">Nilai harus lebih dari 0.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reference_no">Nomor Dokumen / PO / Surat Jalan</label>
                    <input type="text" id="reference_no" name="reference_no" class="form-control" placeholder="Contoh: PO-2026-089" value="<?= e($_POST['reference_no'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Catatan Transaksi</label>
                <textarea id="notes" name="notes" class="form-control" placeholder="Keterangan asal pasokan supplier, no faktur, kondisi barang saat diterima, dll..."><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 24px; font-size: 12.5px; color: var(--text-secondary);">
                <strong>Aturan Transaksi (Slide 25 & 27):</strong> Proses ini dieksekusi dalam transaksi basis data atomic (ACID). Penambahan saldo produk dan pencatatan audit log stock_movements dipastikan tersimpan serentak.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="<?= $baseUrl ?>/public/dashboard.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-success" style="padding: 10px 24px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    Simpan Transaksi Barang Masuk
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
