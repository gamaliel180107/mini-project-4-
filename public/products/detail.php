<?php
/**
 * Modul Produk - Tampilan Detail Produk & Histori Spesifik
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Query Data Produk dengan Relasi Kategori
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_desc
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('danger', 'Produk tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// 2. Query Relasi Many-to-Many: Daftar Supplier untuk Produk ini
$stmtSuppliers = $pdo->prepare("
    SELECT s.id, s.name, s.contact_name, s.phone, s.email, ps.supplier_price, ps.is_primary
    FROM product_suppliers ps
    JOIN suppliers s ON s.id = ps.supplier_id
    WHERE ps.product_id = :id
    ORDER BY ps.is_primary DESC, s.name ASC
");
$stmtSuppliers->execute(['id' => $id]);
$suppliers = $stmtSuppliers->fetchAll();

// 3. Query Histori Transaksi Khusus Produk ini (Stock Movements)
$stmtHistory = $pdo->prepare("
    SELECT sm.*, u.username, COALESCE(up.full_name, u.username) AS recorded_by
    FROM stock_movements sm
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE sm.product_id = :id
    ORDER BY sm.id DESC
");
$stmtHistory->execute(['id' => $id]);
$movements = $stmtHistory->fetchAll();

// 4. Hitung Agregat Khusus Produk ini (Slide 28)
$totalIn = 0;
$totalOut = 0;
foreach ($movements as $m) {
    if ($m['type'] === 'IN') {
        $totalIn += (int)$m['quantity'];
    } elseif ($m['type'] === 'OUT') {
        $totalOut += (int)$m['quantity'];
    }
}

$pageTitle = 'Detail Produk: ' . $product['name'];
$pageSubtitle = 'Rincian spesifikasi, rantai pasok supplier, dan audit trail pergerakan stok';
include __DIR__ . '/../../views/header.php';
?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- INFORMASI UTAMA PRODUK -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <span class="sku-badge" style="font-size: 13px;"><?= e($product['sku']) ?></span>
                <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-top: 6px;"><?= e($product['name']) ?></h3>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="<?= $baseUrl ?>/public/stock/in.php?product_id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                    Restock
                </a>
                <a href="<?= $baseUrl ?>/public/stock/out.php?product_id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14"/></svg>
                    Keluarkan
                </a>
                <?php if (isAdmin()): ?>
                    <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Edit
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($product['image']) && file_exists(__DIR__ . '/../../public/' . $product['image'])): ?>
                <div class="product-detail-img-box">
                    <img src="<?= $baseUrl ?>/public/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); font-weight: 700;">Kategori</div>
                    <div style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;"><?= e($product['category_name']) ?></div>
                </div>

                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); font-weight: 700;">Harga Jual Satuan</div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--primary); margin-top: 4px;"><?= formatRupiah($product['price']) ?></div>
                </div>

                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); font-weight: 700;">Nilai Aset Stok</div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--success-text); margin-top: 4px;"><?= formatRupiah($product['stock'] * $product['price']) ?></div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Deskripsi & Catatan</h4>
                <div style="font-size: 14px; color: var(--text-primary); background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; line-height: 1.6;">
                    <?= nl2br(e($product['description'] ?: 'Tidak ada catatan deskripsi tambahan untuk produk ini.')) ?>
                </div>
            </div>

            <!-- RELASI SUPPLIER (MANY-TO-MANY) -->
            <div>
                <h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Pemasok / Suppliers Terdaftar (Many-to-Many)
                </h4>
                <?php if (empty($suppliers)): ?>
                    <p style="font-size: 13px; color: var(--text-muted);">Belum ada supplier yang dihubungkan ke produk ini.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($suppliers as $sup): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                                <div>
                                    <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">
                                        <?= e($sup['name']) ?>
                                        <?php if ($sup['is_primary']): ?>
                                            <span class="badge badge-primary" style="margin-left: 6px; font-size: 11px;">Utama</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                        Kontak: <?= e($sup['contact_name'] ?: '-') ?> &bull; Telp: <?= e($sup['phone'] ?: '-') ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 11px; color: var(--text-muted);">Harga Beli Pemasok</div>
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;"><?= formatRupiah($sup['supplier_price']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PANEL STATUS STOK & AGREGAT -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">Status Stok Fisik</h3>
            </div>
            <div class="card-body" style="text-align: center; padding: 28px 20px;">
                <div style="font-size: 48px; font-weight: 800; color: <?= ((int)$product['stock'] <= (int)$product['minimum_stock']) ? 'var(--warning)' : 'var(--success)' ?>; line-height: 1;">
                    <?= (int)$product['stock'] ?>
                </div>
                <div style="font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-top: 8px;">Unit Tersedia di Gudang</div>
                <div style="margin-top: 14px;">
                    <?= stockBadge((int)$product['stock'], (int)$product['minimum_stock']) ?>
                </div>

                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <div style="display: flex; justify-content: space-around; font-size: 13px;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 11px;">Batas Minimum</div>
                        <div style="font-weight: 700; color: var(--text-primary);"><?= (int)$product['minimum_stock'] ?> unit</div>
                    </div>
                    <div>
                        <div style="color: var(--text-muted); font-size: 11px;">Total Masuk</div>
                        <div style="font-weight: 700; color: var(--success); font-size: 14px;">+<?= $totalIn ?></div>
                    </div>
                    <div>
                        <div style="color: var(--text-muted); font-size: 11px;">Total Keluar</div>
                        <div style="font-weight: 700; color: var(--danger); font-size: 14px;">-<?= $totalOut ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">Navigasi Terkait</h3>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 8px;">
                <a href="index.php" class="btn btn-secondary" style="width: 100%;">
                    &larr; Kembali ke Katalog Produk
                </a>
                <a href="<?= $baseUrl ?>/public/stock/in.php?product_id=<?= $product['id'] ?>" class="btn btn-primary" style="width: 100%;">
                    Catat Barang Masuk untuk Produk Ini
                </a>
            </div>
        </div>
    </div>
</div>

<!-- AUDIT LOG / RIWAYAT TRANSAKSI PRODUK INI -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Histori Mutasi Stok (Stock Movements)
        </h3>
        <span class="badge badge-secondary"><?= count($movements) ?> Transaksi Tercatat</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Kejadian</th>
                    <th>Tipe Mutasi</th>
                    <th>Jumlah Unit</th>
                    <th>No. Referensi (PO / DO)</th>
                    <th>Catatan & Keterangan</th>
                    <th>Petugas Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            Belum ada histori transaksi barang masuk atau keluar untuk produk ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td style="font-weight: 600; font-size: 13px;"><?= formatDate($m['created_at']) ?></td>
                            <td><?= movementBadge($m['type']) ?></td>
                            <td style="font-weight: 700; font-size: 15px; color: <?= $m['type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $m['type'] === 'IN' ? '+' : '-' ?><?= (int)$m['quantity'] ?>
                            </td>
                            <td>
                                <span style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: #475569;"><?= e($m['reference_no'] ?: '-') ?></span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-secondary);"><?= e($m['notes'] ?: '-') ?></td>
                            <td>
                                <span style="font-size: 13px; font-weight: 500;"><?= e($m['recorded_by']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
