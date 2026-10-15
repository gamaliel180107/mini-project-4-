<?php
/**
 * 403 Forbidden Error Template
 */
$pageTitle = '403 — Akses Ditolak';
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — InventoryPro</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/style.css">
    <style>
        .forbidden-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            padding: 24px;
        }
        .forbidden-card {
            max-width: 520px;
            text-align: center;
            background: #ffffff;
            padding: 48px 36px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }
        .forbidden-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background-color: var(--danger-light);
            color: var(--danger);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="forbidden-container">
        <div class="forbidden-card">
            <div class="forbidden-icon">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
            </div>
            <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">403 — Akses Ditolak</h1>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                Mohon maaf, peran akun Anda saat ini (<strong><?= e(currentUser()['role'] ?? 'guest') ?></strong>) tidak memiliki izin otorisasi untuk mengakses halaman atau fitur administratif ini.
            </p>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 12px; margin-bottom: 24px; font-size: 13px; color: #991b1b; text-align: left;">
                <strong>Business Rule:</strong> Modul administrasi sistem dan manajemen akun pengguna hanya dapat diakses oleh peran <code>Administrator</code>.
            </div>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="<?= $baseUrl ?>/public/dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
                <a href="<?= $baseUrl ?>/public/logout.php" class="btn btn-secondary">Ganti Akun (Login)</a>
            </div>
        </div>
    </div>
</body>
</html>
