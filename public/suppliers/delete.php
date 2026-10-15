<?php
/**
 * Modul Supplier - Hapus Supplier (Admin Only)
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
    setFlash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$sup = $stmt->fetch();

if (!$sup) {
    setFlash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Cek apakah supplier masih terhubung ke produk di product_suppliers
$chk = $pdo->prepare("SELECT COUNT(*) FROM product_suppliers WHERE supplier_id = :id");
$chk->execute(['id' => $id]);
$linkedProducts = (int)$chk->fetchColumn();

if ($linkedProducts > 0) {
    setFlash('danger', "Gagal Menghapus: Supplier '{$sup['name']}' masih terhubung sebagai pemasok {$linkedProducts} produk. Silakan ubah data produk terkait terlebih dahulu.");
    header("Location: index.php");
    exit;
}

try {
    $del = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
    $del->execute(['id' => $id]);
    setFlash('success', "Supplier '{$sup['name']}' berhasil dihapus.");
} catch (PDOException $e) {
    setFlash('danger', "Gagal menghapus supplier: " . $e->getMessage());
}

header("Location: index.php");
exit;
