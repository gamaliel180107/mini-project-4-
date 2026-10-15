<?php
/**
 * Modul Supplier - Listing Pemasok
 * Mengimplementasikan Junction Table dan Relasi Many-to-Many (Slide 8)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Master Pemasok (Suppliers)';
$pageSubtitle = 'Manajemen rekanan vendor pemasok barang dan katalog produk yang dipasok';
$baseUrl = getBaseUrl();

// Query Supplier beserta Agregat Jumlah Produk yang Dipasok (Many-to-Many Junction Table)
$stmt = $pdo->query("
    SELECT 
        s.id, s.name, s.contact_name, s.phone, s.email, s.address, s.created_at,
        COUNT(ps.product_id) AS total_supplied_products
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id, s.name, s.contact_name, s.phone, s.email, s.address, s.created_at
    ORDER BY s.name ASC
");
$suppliers = $stmt->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary);">Daftar Supplier Mitra</h3>
        <p style="font-size: 13px; color: var(--text-secondary);">Relasi Many-to-Many dengan produk melalui junction table <code>product_suppliers</code></p>
    </div>
    <?php if (isAdmin()): ?>
        <a href="create.php" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
            Tambah Supplier Baru
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Perusahaan</th>
                    <th>Kontak Person</th>
                    <th>Telepon & Email</th>
                    <th>Alamat Kantor / Gudang</th>
                    <th style="text-align: center;">Produk Dipasok</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Belum ada supplier yang terdaftar.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;">
                                    <a href="detail.php?id=<?= $sup['id'] ?>" style="color: inherit; text-decoration: none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='inherit'">
                                        <?= e($sup['name']) ?>
                                    </a>
                                </div>
                            </td>
                            <td style="font-weight: 500; font-size: 13.5px;"><?= e($sup['contact_name'] ?: '-') ?></td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);"><?= e($sup['phone'] ?: '-') ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?= e($sup['email'] ?: '-') ?></div>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-secondary); max-width: 250px;">
                                <?= e($sup['address'] ?: '-') ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="detail.php?id=<?= $sup['id'] ?>" class="badge badge-info" style="text-decoration: none;">
                                    <?= (int)$sup['total_supplied_products'] ?> Produk
                                </a>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-buttons" style="justify-content: flex-end;">
                                    <a href="detail.php?id=<?= $sup['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Produk yang Dipasok">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        Detail
                                    </a>

                                    <?php if (isAdmin()): ?>
                                        <a href="edit.php?id=<?= $sup['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Data Supplier">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>

                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier <?= e(addslashes($sup['name'])) ?>?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $sup['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus Supplier">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
