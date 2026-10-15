<?php
/**
 * Halaman Login Pengguna (Authentication)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';

$baseUrl = getBaseUrl();

// Jika sudah login, langsung arahkan ke dashboard
if (isLoggedIn()) {
    header("Location: " . $baseUrl . "/public/dashboard.php");
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Token keamanan CSRF tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            // Prepared statement PDO untuk mencegah SQL Injection
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.password_hash, u.role, p.full_name, p.email 
                FROM users u
                LEFT JOIN user_profiles p ON p.user_id = u.id
                WHERE u.username = :username
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerasi session ID untuk mencegah Session Fixation attack (Slide 22)
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'username'  => $user['username'],
                    'role'      => $user['role'],
                    'full_name' => $user['full_name'] ?: $user['username'],
                    'email'     => $user['email']
                ];

                setFlash('success', 'Selamat datang kembali, ' . ($user['full_name'] ?: $user['username']) . '!');

                $target = $redirect ? $redirect : $baseUrl . '/public/dashboard.php';
                header("Location: $target");
                exit;
            } else {
                $error = 'Username atau kata sandi tidak cocok. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Masuk — InventoryPro</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div style="text-align: center; margin-bottom: 28px;">
        <div class="brand-icon" style="margin: 0 auto 16px; width: 48px; height: 48px; font-size: 24px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
        <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 6px;">Inventory<span style="color:var(--primary);">Pro</span></h1>
        <p style="font-size: 13px; color: var(--text-secondary);">Masuk ke panel manajemen inventaris relasional</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 14px;">
        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Akun Demo Cepat (Klik untuk Mengisi):</span>
        <div class="demo-account-pills">
            <div class="demo-pill" data-user="admin" data-pass="admin123">
                <div class="demo-pill-title">Demo Admin</div>
                <div class="demo-pill-cred">admin / admin123</div>
            </div>
            <div class="demo-pill" data-user="staff" data-pass="staff123">
                <div class="demo-pill-title">Demo Staff</div>
                <div class="demo-pill-cred">staff / staff123</div>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="username">Nama Pengguna (Username)</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Kata Sandi (Password)</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            Masuk ke Sistem
        </button>
    </form>

    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color); text-align: center; font-size: 12px; color: var(--text-muted);">
        Mini Project 4 — Sistem Inventory Relasional & Transaksi ACID
    </div>
</div>

<script src="<?= $baseUrl ?>/public/assets/js/main.js"></script>
</body>
</html>
