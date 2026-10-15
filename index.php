<?php
/**
 * Root Entry Point - Inventory Management System
 * Mengarahkan pengunjung ke halaman login atau dashboard
 */
require_once __DIR__ . '/helpers/auth.php';

$baseUrl = getBaseUrl();
if (isLoggedIn()) {
    header("Location: " . $baseUrl . "/public/dashboard.php");
} else {
    header("Location: " . $baseUrl . "/public/login.php");
}
exit;
