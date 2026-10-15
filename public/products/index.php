<?php
/**
 * Modul Produk - Listing Katalog Produk
 * Mendukung Search, Filter Kategori, Filter Status Stok, Sorting Dinamis Aman, dan Pagination
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Katalog Produk';
$pageSubtitle = 'Manajemen inventaris barang, spesifikasi harga, kategori, dan monitoring stok';
$baseUrl = getBaseUrl();

// 1. Ambil Parameter Filter, Search, Sorting, dan Pagination
$q = trim($_GET['q'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
$stockStatus = trim($_GET['status'] ?? 'all');

// Allowlist Sorting Columns (Slide 16 & 33: "Sorting hanya menggunakan kolom yang diizinkan")
$allowedSorts = [
    'name'       => 'p.name',
    'sku'        => 'p.sku',
    'price'      => 'p.price',
    'stock'      => 'p.stock',
    'created_at' => 'p.id'
];
$sortKey = array_key_exists($_GET['sort'] ?? '', $allowedSorts) ? $_GET['sort'] : 'created_at';
$sortColumn = $allowedSorts[$sortKey];
$sortOrder = validateSortDirection($_GET['order'] ?? '', 'DESC');

// Pagination Setup (Slide 17)
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// 2. Membangun WHERE Clauses secara Aman dengan Prepared Statements
$whereClauses = [];
$params = [];

if ($q !== '') {
    $whereClauses[] = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.description LIKE :q3)';
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}

if ($categoryId) {
    $whereClauses[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($stockStatus === 'low_stock') {
    $whereClauses[] = 'p.stock <= p.minimum_stock';
} elseif ($stockStatus === 'out_of_stock') {
    $whereClauses[] = 'p.stock = 0';
} elseif ($stockStatus === 'available') {
    $whereClauses[] = 'p.stock > 0';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 3. Hitung Total Data untuk Pagination
$countSql = "SELECT COUNT(p.id) FROM products p $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

// 4. Query Data Produk dengan Relasi JOIN Categories & Suppliers
$dataSql = "
    SELECT 
        p.id, p.sku, p.name, p.image, p.price, p.stock, p.minimum_stock, p.created_at,
        c.name AS category_name,
        (SELECT s.name FROM product_suppliers ps JOIN suppliers s ON s.id = ps.supplier_id WHERE ps.product_id = p.id AND ps.is_primary = 1 LIMIT 1) AS primary_supplier
    FROM products p
    JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY $sortColumn $sortOrder
    LIMIT :limit OFFSET :offset
";

$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $dataStmt->bindValue(':' . $k, $v);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$products = $dataStmt->fetchAll();

// 5. Ambil Daftar Kategori untuk Filter Dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<!-- FILTER & ACTION BAR -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap; flex: 1; align-items: center;">
                <!-- Pencarian SKU / Nama -->
                <div class="search-input-wrapper" style="min-width: 240px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama, SKU..." value="<?= e($q) ?>">
                </div>

                <!-- Filter Kategori -->
                <select name="category_id" class="form-control" style="width: auto; min-width: 170px;">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filter Status Stok -->
                <select name="status" class="form-control" style="width: auto; min-width: 160px;">
                    <option value="all" <?= $stockStatus === 'all' ? 'selected' : '' ?>>Semua Stok</option>
                    <option value="low_stock" <?= $stockStatus === 'low_stock' ? 'selected' : '' ?>>Stok Kritis (&le; Min)</option>
                    <option value="available" <?= $stockStatus === 'available' ? 'selected' : '' ?>>Tersedia (&gt; 0)</option>
                    <option value="out_of_stock" <?= $stockStatus === 'out_of_stock' ? 'selected' : '' ?>>Stok Habis (0)</option>
                </select>

                <!-- Sorting Column -->
                <select name="sort" class="form-control" style="width: auto; min-width: 140px;">
                    <option value="created_at" <?= $sortKey === 'created_at' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="name" <?= $sortKey === 'name' ? 'selected' : '' ?>>Nama Produk</option>
                    <option value="sku" <?= $sortKey === 'sku' ? 'selected' : '' ?>>SKU</option>
                    <option value="price" <?= $sortKey === 'price' ? 'selected' : '' ?>>Harga</option>
                    <option value="stock" <?= $sortKey === 'stock' ? 'selected' : '' ?>>Jumlah Stok</option>
                </select>

                <!-- Order Direction -->
                <select name="order" class="form-control" style="width: auto; min-width: 90px;">
                    <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>DESC</option>
                    <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>ASC</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Terapkan
                </button>

                <?php if ($q !== '' || $categoryId !== null || $stockStatus !== 'all' || $sortKey !== 'created_at'): ?>
                    <a href="index.php" class="btn btn-secondary btn-sm" title="Reset Semua Filter">Reset</a>
                <?php endif; ?>
            </div>

            <?php if (isAdmin()): ?>
                <a href="create.php" class="btn btn-success btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Produk Baru
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABEL PRODUK -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU & Produk</th>
                    <th>Kategori</th>
                    <th>Harga Satuan</th>
                    <th>Status Stok</th>
                    <th>Supplier Utama</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 48px; color: var(--text-muted);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            <div>Tidak ada data produk yang sesuai dengan kriteria filter atau pencarian Anda.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../../public/' . $p['image'])): ?>
                                        <img src="<?= $baseUrl ?>/public/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="product-thumb">
                                    <?php else: ?>
                                        <div class="product-thumb-placeholder">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;">
                                            <a href="detail.php?id=<?= $p['id'] ?>" style="color: inherit; text-decoration: none;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='inherit'">
                                                <?= e($p['name']) ?>
                                            </a>
                                        </div>
                                        <span class="sku-badge"><?= e($p['sku']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= e($p['category_name']) ?></span>
                            </td>
                            <td style="font-weight: 600; color: var(--text-primary);">
                                <?= formatRupiah($p['price']) ?>
                            </td>
                            <td>
                                <?= stockBadge((int)$p['stock'], (int)$p['minimum_stock']) ?>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">Min. <?= (int)$p['minimum_stock'] ?> unit</div>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--text-secondary);"><?= e($p['primary_supplier'] ?: 'Belum dihubungkan') ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-buttons" style="justify-content: flex-end;">
                                    <a href="detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Lihat Detail & Histori">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        Detail
                                    </a>
                                    
                                    <!-- Shortcut Transaksi -->
                                    <a href="<?= $baseUrl ?>/public/stock/in.php?product_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" title="Catat Barang Masuk">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                                    </a>

                                    <?php if (isAdmin()): ?>
                                        <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Data Produk">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>

                                        <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk <?= e(addslashes($p['name'])) ?>? Produk dengan histori transaksi tidak dapat dihapus.');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus Produk">
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

    <!-- PAGINATION FOOTER (Slide 17: Pertahankan parameter search, filter, dan sorting) -->
    <?php if ($totalProducts > 0): ?>
        <?php
        // Build base query string tanpa page
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $pageUrl = 'index.php?' . ($queryString ? $queryString . '&' : '') . 'page=';
        ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Menampilkan <strong><?= $offset + 1 ?></strong> &ndash; <strong><?= min($offset + $perPage, $totalProducts) ?></strong> dari total <strong><?= $totalProducts ?></strong> produk
            </div>
            <nav class="pagination-nav">
                <a href="<?= $pageUrl . max(1, $page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    &laquo; Prev
                </a>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                        <a href="<?= $pageUrl . $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                        <span style="padding: 0 4px; color: var(--text-muted);">&hellip;</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <a href="<?= $pageUrl . min($totalPages, $page + 1) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    Next &raquo;
                </a>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
