<?php
/**
 * Modul Pengguna - Hapus Pengguna (Admin Only)
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
    setFlash('danger', 'ID Pengguna tidak valid.');
    header("Location: index.php");
    exit;
}

// Mencegah admin menghapus akunnya sendiri
if ($id === currentUser()['id']) {
    setFlash('danger', 'Tindakan Ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$u = $stmt->fetch();

if (!$u) {
    setFlash('danger', 'Pengguna tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Cek apakah user memiliki riwayat transaksi di stock_movements
$chk = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE user_id = :id");
$chk->execute(['id' => $id]);
$moves = (int)$chk->fetchColumn();

if ($moves > 0) {
    setFlash('danger', "Gagal Menghapus: Pengguna '@{$u['username']}' tercatat sebagai petugas pada {$moves} transaksi barang masuk/keluar. Demi integritas audit trail, akun berhistori tidak dapat dihapus.");
    header("Location: index.php");
    exit;
}

try {
    $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $del->execute(['id' => $id]);
    setFlash('success', "Akun pengguna '@{$u['username']}' berhasil dihapus.");
} catch (PDOException $e) {
    setFlash('danger', "Gagal menghapus pengguna: " . $e->getMessage());
}

header("Location: index.php");
exit;
