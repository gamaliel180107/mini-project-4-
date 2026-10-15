<?php
/**
 * Sidebar Navigation & Top Navbar Component
 */
$currentUser = currentUser();
$role = $currentUser['role'] ?? 'staff';
$fullName = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';
$username = $currentUser['username'] ?? 'user';
$baseUrl = getBaseUrl();

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
function isNavActive(string $path, string $currentUri): bool {
    return strpos($currentUri, $path) !== false;
}
?>

<!-- SIDEBAR -->
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
        <div>
            <div class="brand-title">Inventory<span style="color:#818cf8;">Pro</span></div>
            <div class="brand-subtitle">Relational ERP v4.0</div>
        </div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-section">Ringkasan</div>
        <a href="<?= $baseUrl ?>/public/dashboard.php" class="nav-link <?= isNavActive('/dashboard.php', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
        </a>

        <div class="menu-section">Master Data</div>
        <a href="<?= $baseUrl ?>/public/products/index.php" class="nav-link <?= isNavActive('/products/', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Katalog Produk
        </a>
        <a href="<?= $baseUrl ?>/public/categories/index.php" class="nav-link <?= isNavActive('/categories/', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
            Kategori
        </a>
        <a href="<?= $baseUrl ?>/public/suppliers/index.php" class="nav-link <?= isNavActive('/suppliers/', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            Supplier
        </a>

        <div class="menu-section">Transaksi Stok</div>
        <a href="<?= $baseUrl ?>/public/stock/in.php" class="nav-link <?= isNavActive('/stock/in.php', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            Barang Masuk (IN)
        </a>
        <a href="<?= $baseUrl ?>/public/stock/out.php" class="nav-link <?= isNavActive('/stock/out.php', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            Barang Keluar (OUT)
        </a>
        <a href="<?= $baseUrl ?>/public/stock/history.php" class="nav-link <?= isNavActive('/stock/history.php', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Histori Transaksi
        </a>

        <div class="menu-section">Analisis & Laporan</div>
        <a href="<?= $baseUrl ?>/public/reports/index.php" class="nav-link <?= isNavActive('/reports/', $currentUri) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            Laporan Agregat
        </a>

        <?php if ($role === 'admin'): ?>
            <div class="menu-section">Sistem & Akses</div>
            <a href="<?= $baseUrl ?>/public/users/index.php" class="nav-link <?= isNavActive('/users/', $currentUri) ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Kelola Pengguna
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-snippet">
            <div class="user-avatar">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
            <div class="user-info">
                <span class="user-name" title="<?= e($fullName) ?>"><?= e($fullName) ?></span>
                <span class="user-role-label"><?= $role === 'admin' ? 'Administrator' : 'Staff Gudang' ?></span>
            </div>
        </div>
        <a href="<?= $baseUrl ?>/public/logout.php" class="btn-logout" title="Logout" onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
</aside>
