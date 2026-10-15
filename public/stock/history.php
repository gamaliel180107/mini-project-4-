<?php
/**
 * Modul Transaksi Stok - Histori Mutasi Stok (Audit Trail)
 * Menyediakan filter tipe (IN/OUT), pencarian produk/referensi, dan pagination
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Histori Mutasi Stok';
$pageSubtitle = 'Buku catatan audit log pergerakan barang masuk dan pengeluaran barang';
$baseUrl = getBaseUrl();

// 1. Parameter Filter
$type = strtoupper(trim($_GET['type'] ?? 'ALL'));
$productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: null;
$q = trim($_GET['q'] ?? '');
$order = validateSortDirection($_GET['order'] ?? 'DESC', 'DESC');

// Pagination Setup
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 2. Membangun Query WHERE
$whereClauses = [];
$params = [];

if (in_array($type, ['IN', 'OUT'], true)) {
    $whereClauses[] = 'sm.type = :type';
    $params['type'] = $type;
}

if ($productId) {
    $whereClauses[] = 'sm.product_id = :product_id';
    $params['product_id'] = $productId;
}

if ($q !== '') {
    $whereClauses[] = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR sm.reference_no LIKE :q3 OR sm.notes LIKE :q4)';
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
    $params['q4'] = '%' . $q . '%';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 3. Hitung Total Data untuk Pagination
$countSql = "
    SELECT COUNT(sm.id)
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    $whereSql
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// 4. Query Data Transaksi dengan JOIN Products & Users
$dataSql = "
    SELECT 
        sm.id, sm.type, sm.quantity, sm.reference_no, sm.notes, sm.created_at,
        p.id AS product_id, p.name AS product_name, p.sku,
        u.username, COALESCE(up.full_name, u.username) AS recorded_by
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    $whereSql
    ORDER BY sm.id $order
    LIMIT :limit OFFSET :offset
";
$stmtData = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmtData->bindValue(':' . $k, $v);
}
$stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtData->execute();
$movements = $stmtData->fetchAll();

// 5. Query Ringkasan Akumulasi IN dan OUT
$summarySql = "
    SELECT 
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS sum_in,
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS sum_out
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    $whereSql
";
$stmtSummary = $pdo->prepare($summarySql);
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch();

// Daftar Produk untuk Filter Dropdown
$productsList = $pdo->query("SELECT id, name, sku FROM products ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<!-- FILTER BAR -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap; flex: 1; align-items: center;">
                <div class="search-input-wrapper" style="min-width: 220px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="q" class="form-control" placeholder="Cari ref, SKU, produk..." value="<?= e($q) ?>">
                </div>

                <select name="type" class="form-control" style="width: auto; min-width: 140px;">
                    <option value="ALL" <?= $type === 'ALL' ? 'selected' : '' ?>>Semua Tipe</option>
                    <option value="IN" <?= $type === 'IN' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
                    <option value="OUT" <?= $type === 'OUT' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
                </select>

                <select name="product_id" class="form-control" style="width: auto; min-width: 200px;">
                    <option value="">Semua Produk</option>
                    <?php foreach ($productsList as $pl): ?>
                        <option value="<?= $pl['id'] ?>" <?= $productId === (int)$pl['id'] ? 'selected' : '' ?>>
                            [<?= e($pl['sku']) ?>] <?= e($pl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="order" class="form-control" style="width: auto; min-width: 120px;">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Terlama</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Filter</button>

                <?php if ($type !== 'ALL' || $productId !== null || $q !== '' || $order !== 'DESC'): ?>
                    <a href="history.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="in.php" class="btn btn-success btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                    Input IN
                </a>
                <a href="out.php" class="btn btn-danger btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14"/></svg>
                    Input OUT
                </a>
            </div>
        </form>
    </div>
</div>

<!-- STATS STRIP -->
<div style="display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 180px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Barang Masuk (IN)</div>
            <div style="font-size: 20px; font-weight: 800; color: var(--success); margin-top: 2px;">+<?= number_format($summary['sum_in'] ?? 0) ?> unit</div>
        </div>
        <span class="badge badge-success">IN</span>
    </div>

    <div style="flex: 1; min-width: 180px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Barang Keluar (OUT)</div>
            <div style="font-size: 20px; font-weight: 800; color: var(--danger); margin-top: 2px;">-<?= number_format($summary['sum_out'] ?? 0) ?> unit</div>
        </div>
        <span class="badge badge-danger">OUT</span>
    </div>

    <div style="flex: 1; min-width: 180px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Catatan Transaksi</div>
            <div style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-top: 2px;"><?= number_format($totalRecords) ?> kali</div>
        </div>
        <span class="badge badge-secondary">AUDIT LOG</span>
    </div>
</div>

<!-- TABEL HISTORI TRANSAKSI -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Kejadian</th>
                    <th>Tipe Mutasi</th>
                    <th>Nomor Referensi</th>
                    <th>SKU & Produk</th>
                    <th style="text-align: center;">Jumlah Unit</th>
                    <th>Catatan & Tujuan</th>
                    <th>Petugas Pencatat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-muted);">
                            Tidak ada riwayat pergerakan stok yang cocok dengan filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);"><?= formatDate($m['created_at']) ?></div>
                            </td>
                            <td><?= movementBadge($m['type']) ?></td>
                            <td>
                                <span style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                    <?= e($m['reference_no'] ?: '-') ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13.5px;">
                                    <a href="<?= $baseUrl ?>/public/products/detail.php?id=<?= $m['product_id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= e($m['product_name']) ?>
                                    </a>
                                </div>
                                <span class="sku-badge"><?= e($m['sku']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-weight: 800; font-size: 15px; color: <?= $m['type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= $m['type'] === 'IN' ? '+' : '-' ?><?= (int)$m['quantity'] ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-secondary); max-width: 280px;">
                                <?= e($m['notes'] ?: '-') ?>
                            </td>
                            <td>
                                <span style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);"><?= e($m['recorded_by']) ?></span>
                                <div style="font-size: 11px; color: var(--text-muted);">@<?= e($m['username']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalRecords > 0): ?>
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams);
        $pageUrl = 'history.php?' . ($queryString ? $queryString . '&' : '') . 'page=';
        ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Menampilkan <strong><?= $offset + 1 ?></strong> &ndash; <strong><?= min($offset + $perPage, $totalRecords) ?></strong> dari total <strong><?= $totalRecords ?></strong> mutasi
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
