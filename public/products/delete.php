<?php
/**
 * Modul Produk - Hapus Produk (Admin Only)
 * Menerapkan Business Rule & Referential Integrity:
 * "Produk yang memiliki histori transaksi tidak boleh dihapus sembarangan."
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
    setFlash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Cek apakah produk ada di database
$stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('danger', 'Produk tidak ditemukan atau telah dihapus sebelumnya.');
    header("Location: index.php");
    exit;
}

// 2. Cek apakah produk memiliki riwayat transaksi di stock_movements (Slide 9 & 33)
$chkMovements = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id");
$chkMovements->execute(['id' => $id]);
$hasMovements = (int)$chkMovements->fetchColumn();

if ($hasMovements > 0) {
    setFlash('danger', "Gagal Menghapus: Produk '{$product['name']}' memiliki {$hasMovements} catatan transaksi (histori barang masuk/keluar). Sesuai aturan bisnis dan integritas referensial (Slide 9 & 33), produk yang memiliki histori tidak boleh dihapus.");
    header("Location: index.php");
    exit;
}

// 3. Jika tidak ada histori mutasi, eksekusi penghapusan
try {
    $del = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $del->execute(['id' => $id]);

    setFlash('success', "Produk '{$product['name']}' berhasil dihapus dari inventaris.");
} catch (PDOException $e) {
    setFlash('danger', "Terjadi kesalahan integritas database: " . $e->getMessage());
}

header("Location: index.php");
exit;
