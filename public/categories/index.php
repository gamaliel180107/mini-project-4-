<?php
/**
 * Modul Kategori - Listing Kategori dengan Analisis Agregat Produk
 * Menampilkan GROUP BY, COUNT, SUM, dan AVG (Slide 14)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Master Kategori Produk';
$pageSubtitle = 'Pengelompokan barang dan agregasi nilai inventaris per kategori';
$baseUrl = getBaseUrl();

// Query SQL Agregat Sesuai Slide 14:
// SELECT c.name, COUNT(p.id) AS total_products, SUM(p.stock) AS total_stock, AVG(p.price) AS average_price FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name;
$stmt = $pdo->query("
    SELECT 
        c.id, c.name, c.description, c.created_at,
        COUNT(p.id) AS total_products,
        COALESCE(SUM(p.stock), 0) AS total_stock,
        COALESCE(AVG(p.price), 0) AS average_price,
        COALESCE(SUM(p.stock * p.price), 0) AS total_category_value
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary);">Daftar Kategori Terdaftar</h3>
        <p style="font-size: 13px; color: var(--text-secondary);">Dihitung secara dinamis dengan relasi 1-to-Many ke tabel produk</p>
    </div>
    <?php if (isAdmin()): ?>
        <a href="create.php" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
            Tambah Kategori Baru
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Kategori & Deskripsi</th>
                    <th style="text-align: center;">Jumlah Item Produk</th>
                    <th style="text-align: center;">Total Unit Stok</th>
                    <th>Rata-rata Harga Satuan</th>
                    <th>Total Nilai Aset Kategori</th>
                    <?php if (isAdmin()): ?>
                        <th style="text-align: right;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Belum ada kategori terdaftar. Silakan tambahkan kategori baru.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;"><?= e($cat['name']) ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                                    <?= e($cat['description'] ?: 'Tidak ada deskripsi tambahan') ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?= $baseUrl ?>/public/products/index.php?category_id=<?= $cat['id'] ?>" class="badge badge-primary" style="text-decoration: none;">
                                    <?= (int)$cat['total_products'] ?> Produk
                                </a>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= number_format($cat['total_stock']) ?> unit
                            </td>
                            <td style="font-size: 13.5px; color: var(--text-secondary);">
                                <?= formatRupiah($cat['average_price']) ?>
                            </td>
                            <td style="font-weight: 700; color: var(--text-primary);">
                                <?= formatRupiah($cat['total_category_value']) ?>
                            </td>
                            <?php if (isAdmin()): ?>
                                <td style="text-align: right;">
                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Kategori">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>

                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori <?= e(addslashes($cat['name'])) ?>?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus Kategori">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
