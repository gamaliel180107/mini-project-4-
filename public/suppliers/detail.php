<?php
/**
 * Modul Supplier - Detail Supplier & Produk yang Dipasok (Many-to-Many)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    setFlash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Query Produk yang Terhubung ke Supplier ini melalui Junction Table product_suppliers
$stmtProds = $pdo->prepare("
    SELECT 
        p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
        c.name AS category_name,
        ps.supplier_price, ps.is_primary
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE ps.supplier_id = :id
    ORDER BY p.name ASC
");
$stmtProds->execute(['id' => $id]);
$suppliedProducts = $stmtProds->fetchAll();

$pageTitle = 'Detail Supplier: ' . $supplier['name'];
$pageSubtitle = 'Rincian kontak rekanan dan katalog produk yang dipasok';
include __DIR__ . '/../../views/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 24px;">
    <!-- INFORMASI KONTAK SUPPLIER -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">Profil Perusahaan</h3>
            <?php if (isAdmin()): ?>
                <a href="edit.php?id=<?= $supplier['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h4 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;"><?= e($supplier['name']) ?></h4>

            <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13.5px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">Kontak Person (PIC)</span>
                    <span style="font-weight: 600; color: var(--text-primary);"><?= e($supplier['contact_name'] ?: '-') ?></span>
                </div>

                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">Telepon</span>
                    <span style="font-weight: 600; color: var(--text-primary);"><?= e($supplier['phone'] ?: '-') ?></span>
                </div>

                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">Email</span>
                    <span style="font-weight: 500; color: var(--primary);"><?= e($supplier['email'] ?: '-') ?></span>
                </div>

                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); display: block;">Alamat</span>
                    <span style="color: var(--text-secondary); line-height: 1.5;"><?= nl2br(e($supplier['address'] ?: '-')) ?></span>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color);">
                <a href="index.php" class="btn btn-secondary" style="width: 100%;">
                    &larr; Kembali ke Daftar Supplier
                </a>
            </div>
        </div>
    </div>

    <!-- DAFTAR PRODUK YANG DIPASOK -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Daftar Produk yang Dipasok (<?= count($suppliedProducts) ?>)
            </h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>SKU & Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga Pasokan (Beli)</th>
                        <th>Harga Jual Toko</th>
                        <th>Stok Saat Ini</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliedProducts)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                                Belum ada produk yang dihubungkan ke pemasok ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliedProducts as $sp): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 13.5px;">
                                        <a href="<?= $baseUrl ?>/public/products/detail.php?id=<?= $sp['id'] ?>" style="color: inherit; text-decoration: none;">
                                            <?= e($sp['name']) ?>
                                        </a>
                                        <?php if ($sp['is_primary']): ?>
                                            <span class="badge badge-primary" style="font-size: 10px; margin-left: 4px;">Utama</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="sku-badge"><?= e($sp['sku']) ?></span>
                                </td>
                                <td><?= e($sp['category_name']) ?></td>
                                <td style="font-weight: 700; color: var(--text-primary);"><?= formatRupiah($sp['supplier_price']) ?></td>
                                <td style="color: var(--text-secondary);"><?= formatRupiah($sp['price']) ?></td>
                                <td><?= stockBadge((int)$sp['stock'], (int)$sp['minimum_stock']) ?></td>
                                <td style="text-align: right;">
                                    <a href="<?= $baseUrl ?>/public/stock/in.php?product_id=<?= $sp['id'] ?>" class="btn btn-primary btn-sm" title="Catat Barang Masuk dari Supplier Ini">
                                        Restock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
