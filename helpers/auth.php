<?php
/**
 * Helper Autentikasi, Otorisasi Role, Proteksi CSRF, dan Flash Messages
 */

require_once __DIR__ . '/../config/database.php';

function ensureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Parameter cookie session aman
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        session_set_cookie_params($cookieParams);
        session_start();
    }
}

ensureSession();

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isAdmin(): bool {
    return isLoggedIn() && (currentUser()['role'] ?? '') === 'admin';
}

function isStaff(): bool {
    return isLoggedIn() && (currentUser()['role'] ?? '') === 'staff';
}

function requireLogin(string $redirect = null): void {
    if (!isLoggedIn()) {
        $loginUrl = getBaseUrl() . '/public/login.php';
        if ($redirect) {
            $loginUrl .= '?redirect=' . urlencode($redirect);
        }
        header("Location: $loginUrl");
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        include __DIR__ . '/../views/forbidden.php';
        exit;
    }
}

/**
 * Mendeteksi base url relatif aplikasi
 */
function getBaseUrl(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Temukan posisi folder mini project 4
    if (preg_match('#^(.*?mini\s*project\s*4)#i', $scriptName, $matches)) {
        return $matches[1];
    }
    // Fallback jika berada langsung di public atau root
    $dir = dirname($scriptName);
    if (strpos($dir, '/public') !== false) {
        return substr($dir, 0, strpos($dir, '/public'));
    }
    return '';
}

/**
 * CSRF Protection
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Flash Messaging
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
