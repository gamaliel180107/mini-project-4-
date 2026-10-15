<?php
/**
 * Modul Kategori - Hapus Kategori (Admin Only)
 * Memvalidasi batasan Foreign Key & Referential Integrity (Slide 9 & 33)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Token keamanan CSRF tidak valid.');
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Kategori tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Cek keberadaan kategori
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$cat = $stmt->fetch();

if (!$cat) {
    setFlash('danger', 'Kategori tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// 2. Cek apakah ada produk yang masih terhubung ke kategori ini (Referential Integrity RESTRICT)
$chkProd = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
$chkProd->execute(['id' => $id]);
$productCount = (int)$chkProd->fetchColumn();

if ($productCount > 0) {
    setFlash('danger', "Gagal Menghapus: Kategori '{$cat['name']}' masih memiliki {$productCount} produk terkait. Demi integritas relasional, hapus atau pindahkan produk terlebih dahulu.");
    header("Location: index.php");
    exit;
}

// 3. Hapus kategori jika kosong
try {
    $del = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $del->execute(['id' => $id]);
    setFlash('success', "Kategori '{$cat['name']}' berhasil dihapus.");
} catch (PDOException $e) {
    setFlash('danger', "Gagal menghapus kategori: " . $e->getMessage());
}

header("Location: index.php");
exit;
