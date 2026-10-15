<?php
/**
 * Halaman Logout Pengguna
 */
require_once __DIR__ . '/../helpers/auth.php';

ensureSession();

// Kosongkan array session
$_SESSION = [];

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session server
session_destroy();

// Buka session baru untuk flash message feedback
ensureSession();
setFlash('info', 'Anda telah berhasil keluar dari sistem.');

$baseUrl = getBaseUrl();
header("Location: " . $baseUrl . "/public/login.php");
exit;
