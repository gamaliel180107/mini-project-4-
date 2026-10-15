<?php
/**
 * Header Layout Template
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/auth.php';

$pageTitle = $pageTitle ?? 'Inventory Management System';
$pageSubtitle = $pageSubtitle ?? '';
$pageType = $pageType ?? '';
$baseUrl = getBaseUrl();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="InventoryPro - Sistem Manajemen Inventaris Berbasis Database Relasional, Transaksi ACID, dan Kontrol Akses Berbasis Peran.">
    <title><?= e($pageTitle) ?> — InventoryPro</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/style.css">
</head>
<body data-page-type="<?= e($pageType) ?>">

<div class="app-wrapper">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        <header class="top-navbar">
            <div class="page-title-box">
                <h2><?= e($pageTitle) ?></h2>
                <?php if ($pageSubtitle): ?>
                    <p><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="top-actions">
                <?= roleBadge(currentUser()['role'] ?? 'staff') ?>
                <a href="<?= $baseUrl ?>/public/stock/in.php" class="btn btn-primary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
                    Barang Masuk
                </a>
                <a href="<?= $baseUrl ?>/public/stock/out.php" class="btn btn-secondary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14"/></svg>
                    Barang Keluar
                </a>
            </div>
        </header>

        <main class="content-body">
            <!-- Flash Notification -->
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                    <?php if ($flash['type'] === 'success'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php elseif ($flash['type'] === 'danger'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    <?php elseif ($flash['type'] === 'warning'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <?php endif; ?>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>
