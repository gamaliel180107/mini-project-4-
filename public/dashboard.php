<?php
/**
 * Dashboard Utama Inventory Management System
 * Menampilkan ringkasan KPI bisnis, alert stok rendah, dan transaksi terkini
 */
require_once __DIR__ . '/../helpers/auth.php';
requireLogin();

$pageTitle = 'Dashboard Ringkasan';
$pageSubtitle = 'Statistik persediaan barang, indikator stok kritis, dan aktivitas gudang terkini';
$pageType = 'dashboard';

include __DIR__ . '/../views/header.php';

// 1. Eksekusi Query Agregat untuk KPI Cards (Slide 29)
// Total Produk & Total Nilai Inventory
$stmtStats = $pdo->query("
    SELECT 
        COUNT(id) AS total_products,
        COALESCE(SUM(stock * price), 0) AS total_inventory_value,
        COALESCE(SUM(stock), 0) AS total_stock_units,
        COUNT(CASE WHEN stock <= minimum_stock THEN 1 END) AS low_stock_count,
        COUNT(CASE WHEN stock = 0 THEN 1 END) AS out_of_stock_count
    FROM products
");
$kpi = $stmtStats->fetch();

// Total Barang Masuk & Barang Keluar dari Histori Transaksi
$stmtMovements = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in_qty,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out_qty,
        COUNT(id) AS total_transactions
    FROM stock_movements
");
$movKpi = $stmtMovements->fetch();

// 2. Query Daftar Produk dengan Stok Rendah (stock <= minimum_stock)
$stmtLowStock = $pdo->query("
    SELECT p.id, p.sku, p.name, p.image, p.stock, p.minimum_stock, p.price, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC, p.name ASC
    LIMIT 10
");
$lowStockProducts = $stmtLowStock->fetchAll();

// 3. Query Aktivitas Transaksi Terbaru (Stock Movements)
$stmtRecent = $pdo->query("
    SELECT sm.id, sm.type, sm.quantity, sm.reference_no, sm.notes, sm.created_at,
           p.id AS product_id, p.name AS product_name, p.sku, p.image,
           u.username, COALESCE(up.full_name, u.username) AS recorded_by
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY sm.id DESC
    LIMIT 7
");
$recentMovements = $stmtRecent->fetchAll();
?>

<!-- KPI STATS CARDS GRID -->
<div class="stats-grid">
    <!-- Card 1: Total Produk -->
    <div class="stat-card primary">
        <div class="stat-header">
            <span class="stat-label">Total Item Produk</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($kpi['total_products'] ?? 0) ?></div>
        <div class="stat-meta">Total <?= number_format($kpi['total_stock_units'] ?? 0) ?> unit fisik tersimpan</div>
    </div>

    <!-- Card 2: Nilai Total Inventory -->
    <div class="stat-card success">
        <div class="stat-header">
            <span class="stat-label">Nilai Total Aset</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div class="stat-value" style="font-size: 22px;"><?= formatRupiah($kpi['total_inventory_value'] ?? 0) ?></div>
        <div class="stat-meta">Kalkulasi SUM(stok &times; harga satuan)</div>
    </div>

    <!-- Card 3: Stok Rendah Alert -->
    <div class="stat-card <?= ($kpi['low_stock_count'] > 0) ? 'warning' : 'info' ?>">
        <div class="stat-header">
            <span class="stat-label">Stok Kritis / Menipis</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($kpi['low_stock_count'] ?? 0) ?></div>
        <div class="stat-meta">
            <?php if ($kpi['out_of_stock_count'] > 0): ?>
                <strong style="color:var(--danger);"><?= $kpi['out_of_stock_count'] ?> item stok habis (0)!</strong>
            <?php else: ?>
                <?= $kpi['low_stock_count'] > 0 ? 'Perlu segera dilakukan restock' : 'Semua stok dalam batas aman' ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card 4: Barang Masuk (IN) -->
    <div class="stat-card info">
        <div class="stat-header">
            <span class="stat-label">Total Barang Masuk</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($movKpi['total_in_qty'] ?? 0) ?></div>
        <div class="stat-meta">Unit masuk dari supplier / restock</div>
    </div>

    <!-- Card 5: Barang Keluar (OUT) -->
    <div class="stat-card danger">
        <div class="stat-header">
            <span class="stat-label">Total Barang Keluar</span>
            <div class="stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= number_format($movKpi['total_out_qty'] ?? 0) ?></div>
        <div class="stat-meta">Unit keluar terdistribusi</div>
    </div>
</div>

<!-- SECTION GRID: LOW STOCK ALERT & RECENT MOVEMENTS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">

    <!-- WIDGET 1: PERINGATAN STOK RENDAH -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <span style="color: var(--warning);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </span>
                    Peringatan Stok Kritis (Threshold &le; Minimum)
                </h3>
                <p class="card-subtitle">Produk yang memerlukan pengadaan / barang masuk segera</p>
            </div>
            <a href="<?= $baseUrl ?>/public/products/index.php?status=low_stock" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>SKU & Produk</th>
                        <th>Kategori</th>
                        <th>Sisa Stok</th>
                        <th>Min. Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockProducts)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                <div>Semua stok barang berada dalam kuantitas aman di atas batas minimum.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lowStockProducts as $lp): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <?php if (!empty($lp['image']) && file_exists(__DIR__ . '/../public/' . $lp['image'])): ?>
                                            <img src="<?= $baseUrl ?>/public/<?= e($lp['image']) ?>" alt="<?= e($lp['name']) ?>" class="product-thumb" style="width: 40px; height: 40px;">
                                        <?php else: ?>
                                            <div class="product-thumb-placeholder" style="width: 40px; height: 40px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary); font-size: 13.5px;"><?= e($lp['name']) ?></div>
                                            <span class="sku-badge" style="font-size: 11px;"><?= e($lp['sku']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($lp['category_name']) ?></td>
                                <td>
                                    <?= stockBadge((int)$lp['stock'], (int)$lp['minimum_stock']) ?>
                                </td>
                                <td style="font-weight: 600; color: var(--text-secondary);"><?= (int)$lp['minimum_stock'] ?></td>
                                <td>
                                    <a href="<?= $baseUrl ?>/public/stock/in.php?product_id=<?= (int)$lp['id'] ?>" class="btn btn-primary btn-sm" title="Catat Restock Barang Masuk">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
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

    <!-- WIDGET 2: AKTIVITAS TRANSAKSI TERAKHIR (STOCK MOVEMENTS) -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    <span style="color: var(--primary);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </span>
                    Aktivitas Gudang Terkini
                </h3>
                <p class="card-subtitle">Log audit real-time barang masuk dan pengeluaran barang</p>
            </div>
            <a href="<?= $baseUrl ?>/public/stock/history.php" class="btn btn-secondary btn-sm">Histori Lengkap</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu & Ref</th>
                        <th>Tipe</th>
                        <th>Produk</th>
                        <th>Jumlah</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                Belum ada riwayat transaksi barang masuk atau keluar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $rm): ?>
                            <tr>
                                <td>
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text-primary);"><?= formatDate($rm['created_at']) ?></div>
                                    <span style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);"><?= e($rm['reference_no'] ?: '-') ?></span>
                                </td>
                                <td><?= movementBadge($rm['type']) ?></td>
                                <td>
                                    <div style="font-weight: 500;"><?= e($rm['product_name']) ?></div>
                                    <span class="sku-badge" style="font-size: 10px;"><?= e($rm['sku']) ?></span>
                                </td>
                                <td style="font-weight: 700; font-size: 14px; color: <?= $rm['type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= $rm['type'] === 'IN' ? '+' : '-' ?><?= (int)$rm['quantity'] ?>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 500; color: var(--text-secondary);"><?= e($rm['recorded_by']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- QUICK ACTION BANNER -->
<div class="card" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: #ffffff; border: none; padding: 24px 32px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 4px; color:#ffffff;">Aksi Cepat Inventaris</h3>
            <p style="font-size: 13.5px; opacity: 0.85;">Pilih operasi yang ingin Anda lakukan untuk memperbarui stok barang secara konsisten.</p>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="<?= $baseUrl ?>/public/stock/in.php" class="btn" style="background: #ffffff; color: #4338ca; font-weight: 700;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                Catat Barang Masuk (IN)
            </a>
            <a href="<?= $baseUrl ?>/public/stock/out.php" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14"/></svg>
                Catat Barang Keluar (OUT)
            </a>
            <?php if (isAdmin()): ?>
                <a href="<?= $baseUrl ?>/public/products/create.php" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Tambah Produk Baru
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
