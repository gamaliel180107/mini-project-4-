<?php
/**
 * Modul Laporan Inventaris - Agregat SQL, GROUP BY, dan Business Intelligence
 * Mengimplementasikan Query Agregat Sesuai Slide 28, 14, dan 15
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireLogin();

$pageTitle = 'Laporan Inventaris & Analisis Agregat';
$pageSubtitle = 'Rekapitulasi perputaran barang masuk/keluar, valuasi aset, dan performa kategori';
$baseUrl = getBaseUrl();

// Filter Kategori
$selectedCat = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;

// Filter Minimum Valuasi (HAVING clause - Slide 15)
$minValuation = filter_input(INPUT_GET, 'min_val', FILTER_VALIDATE_FLOAT) ?: 0;

$whereSql = '';
$params = [];
if ($selectedCat) {
    $whereSql = 'WHERE p.category_id = :cat_id';
    $params['cat_id'] = $selectedCat;
}

// 1. QUERY LAPORAN PERPUTARAN PRODUK (SLIDE 28 + ENHANCED)
// Menghitung SUM(CASE WHEN sm.type='IN'...) dan SUM(CASE WHEN sm.type='OUT'...)
$havingClause = '';
if ($minValuation > 0) {
    $havingClause = 'HAVING total_valuation >= :min_val';
    $params['min_val'] = $minValuation;
}

$reportSql = "
    SELECT 
        p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
        c.name AS category_name,
        (p.stock * p.price) AS total_valuation,
        COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    $whereSql
    GROUP BY p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock, c.name
    $havingClause
    ORDER BY total_valuation DESC
";
$stmtReport = $pdo->prepare($reportSql);
$stmtReport->execute($params);
$productReports = $stmtReport->fetchAll();

// 2. QUERY LAPORAN AGREGAT KATEGORI (SLIDE 14: GROUP BY, COUNT, SUM, AVG)
$stmtCatReport = $pdo->query("
    SELECT 
        c.id, c.name,
        COUNT(p.id) AS count_products,
        COALESCE(SUM(p.stock), 0) AS sum_stock,
        COALESCE(AVG(p.price), 0) AS avg_price,
        COALESCE(SUM(p.stock * p.price), 0) AS category_valuation
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY category_valuation DESC
");
$catReports = $stmtCatReport->fetchAll();

// 3. Grand Total Akumulasi
$grandTotalStock = 0;
$grandTotalValue = 0;
$grandTotalIn = 0;
$grandTotalOut = 0;
foreach ($productReports as $pr) {
    $grandTotalStock += (int)$pr['stock'];
    $grandTotalValue += (float)$pr['total_valuation'];
    $grandTotalIn    += (int)$pr['total_in'];
    $grandTotalOut   += (int)$pr['total_out'];
}

$categoriesList = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<!-- FILTER & PRINT CONTROLS -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <label style="font-size: 13px; font-weight: 700; color: var(--text-secondary);">Filter Laporan:</label>

                <select name="category_id" class="form-control" style="width: auto; min-width: 180px;">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categoriesList as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $selectedCat === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= e($cl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filter HAVING: Minimum Valuasi Aset -->
                <select name="min_val" class="form-control" style="width: auto; min-width: 200px;">
                    <option value="0" <?= $minValuation == 0 ? 'selected' : '' ?>>Semua Nilai Valuasi</option>
                    <option value="5000000" <?= $minValuation == 5000000 ? 'selected' : '' ?>>Valuasi &ge; Rp 5.000.000</option>
                    <option value="15000000" <?= $minValuation == 15000000 ? 'selected' : '' ?>>Valuasi &ge; Rp 15.000.000</option>
                    <option value="50000000" <?= $minValuation == 50000000 ? 'selected' : '' ?>>Valuasi &ge; Rp 50.000.000</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>

                <?php if ($selectedCat || $minValuation > 0): ?>
                    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>

            <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / Ekspor PDF
            </button>
        </form>
    </div>
</div>

<!-- RINGKASAN AKUMULASI BISNIS -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-header">
            <span class="stat-label">Total Item Produk Difilter</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= count($productReports) ?> Item</div>
        <div class="stat-meta">Total <?= number_format($grandTotalStock) ?> unit stok fisik</div>
    </div>

    <div class="stat-card success">
        <div class="stat-header">
            <span class="stat-label">Total Valuasi Nilai Aset</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div class="stat-value" style="font-size: 22px;"><?= formatRupiah($grandTotalValue) ?></div>
        <div class="stat-meta">Kalkulasi SUM(stok &times; harga satuan)</div>
    </div>

    <div class="stat-card info">
        <div class="stat-header">
            <span class="stat-label">Akumulasi Masuk (IN)</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </div>
        </div>
        <div class="stat-value">+<?= number_format($grandTotalIn) ?></div>
        <div class="stat-meta">Unit barang masuk dari supplier</div>
    </div>

    <div class="stat-card danger">
        <div class="stat-header">
            <span class="stat-label">Akumulasi Keluar (OUT)</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </div>
        </div>
        <div class="stat-value">-<?= number_format($grandTotalOut) ?></div>
        <div class="stat-meta">Unit barang keluar terdistribusi</div>
    </div>
</div>

<!-- TABEL 1: LAPORAN PERPUTARAN STOK DAN VALUASI ASET PER PRODUK (SLIDE 28) -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Laporan Perputaran Mutasi & Valuasi Stok Produk
            </h3>
            <p class="card-subtitle">Query Agregat JOIN products dan stock_movements (GROUP BY p.id)</p>
        </div>
        <span class="badge badge-secondary"><?= count($productReports) ?> Rekod Produk</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>SKU & Produk</th>
                    <th>Kategori</th>
                    <th>Harga Satuan</th>
                    <th style="text-align: center; color: var(--success-text);">Total Masuk (IN)</th>
                    <th style="text-align: center; color: var(--danger-text);">Total Keluar (OUT)</th>
                    <th style="text-align: center;">Sisa Stok Fisik</th>
                    <th style="text-align: right;">Total Valuasi Aset</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productReports)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Tidak ada data produk yang memenuhi kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productReports as $pr): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 13.5px;">
                                    <a href="<?= $baseUrl ?>/public/products/detail.php?id=<?= $pr['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= e($pr['name']) ?>
                                    </a>
                                </div>
                                <span class="sku-badge"><?= e($pr['sku']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= e($pr['category_name']) ?></span>
                            </td>
                            <td><?= formatRupiah($pr['price']) ?></td>
                            <td style="text-align: center; font-weight: 700; color: var(--success);">
                                +<?= number_format($pr['total_in']) ?>
                            </td>
                            <td style="text-align: center; font-weight: 700; color: var(--danger);">
                                -<?= number_format($pr['total_out']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?= stockBadge((int)$pr['stock'], (int)$pr['minimum_stock']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: var(--text-primary); font-size: 14px;">
                                <?= formatRupiah($pr['total_valuation']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid var(--border-color);">
                    <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 12px; color: var(--text-secondary); padding: 16px;">
                        Grand Total Akumulasi:
                    </td>
                    <td style="text-align: center; color: var(--success); font-size: 15px;">
                        +<?= number_format($grandTotalIn) ?>
                    </td>
                    <td style="text-align: center; color: var(--danger); font-size: 15px;">
                        -<?= number_format($grandTotalOut) ?>
                    </td>
                    <td style="text-align: center; font-size: 15px;">
                        <?= number_format($grandTotalStock) ?> unit
                    </td>
                    <td style="text-align: right; font-size: 16px; color: var(--primary);">
                        <?= formatRupiah($grandTotalValue) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- TABEL 2: LAPORAN KATEGORI DAN AGREGAT KEUANGAN (SLIDE 14) -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                Ringkasan Agregasi per Kategori Produk
            </h3>
            <p class="card-subtitle">Implementasi COUNT(p.id), SUM(stock), AVG(price), SUM(stock * price) (Slide 14)</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th style="text-align: center;">Jumlah Item Produk (COUNT)</th>
                    <th style="text-align: center;">Total Unit Stok (SUM)</th>
                    <th>Rata-rata Harga Produk (AVG)</th>
                    <th style="text-align: right;">Total Valuasi Nilai (SUM &times; price)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catReports as $cr): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--text-primary);"><?= e($cr['name']) ?></td>
                        <td style="text-align: center;">
                            <span class="badge badge-primary"><?= (int)$cr['count_products'] ?> Produk</span>
                        </td>
                        <td style="text-align: center; font-weight: 600;">
                            <?= number_format($cr['sum_stock']) ?> unit
                        </td>
                        <td><?= formatRupiah($cr['avg_price']) ?></td>
                        <td style="text-align: right; font-weight: 800; color: var(--text-primary);">
                            <?= formatRupiah($cr['category_valuation']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
