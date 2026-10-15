<?php
/**
 * Modul Pengguna - Listing Akun & Profil Pengguna (Admin Only)
 * Menerapkan Relasi 1-to-1 dengan user_profiles dan Kontrol Akses Otorisasi Ketat (Slide 6 & 24)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin(); // Memastikan hanya Admin yang dapat mengakses. Staff akan mendapatkan HTTP 403 Forbidden!

$pageTitle = 'Manajemen Pengguna & Hak Akses';
$pageSubtitle = 'Pengelolaan akun login dan profil pengguna sistem (Relasi 1-to-1 users & user_profiles)';
$baseUrl = getBaseUrl();

// Query Relasi 1-to-1: users dan user_profiles (Slide 6)
$stmt = $pdo->query("
    SELECT 
        u.id, u.username, u.role, u.created_at,
        p.full_name, p.email, p.phone, p.address
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary);">Daftar Pengguna Sistem</h3>
        <p style="font-size: 13px; color: var(--text-secondary);">Data login diamankan dengan Bcrypt Hashing, dipisahkan dari data profil (Relasi 1-to-1)</p>
    </div>
    <a href="create.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        Tambah Pengguna Baru
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Username & Akun</th>
                    <th>Nama Lengkap (Profil 1:1)</th>
                    <th>Peran / Hak Akses</th>
                    <th>Email & Telepon</th>
                    <th>Waktu Dibuat</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar" style="width: 34px; height: 34px; font-size: 13px;">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 14px;">
                                        @<?= e($u['username']) ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);">ID: #<?= (int)$u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 600; font-size: 13.5px;"><?= e($u['full_name'] ?: '-') ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary);"><?= e($u['address'] ?: '-') ?></div>
                        </td>
                        <td>
                            <?= roleBadge($u['role']) ?>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 500; color: var(--text-primary);"><?= e($u['email'] ?: '-') ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);"><?= e($u['phone'] ?: '-') ?></div>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-secondary);">
                            <?= formatDate($u['created_at']) ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="action-buttons" style="justify-content: flex-end;">
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Akun & Profil">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>

                                <?php if ($u['id'] !== currentUser()['id']): ?>
                                    <form method="POST" action="delete.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?= e(addslashes($u['username'])) ?>?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Hapus Pengguna">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Akun Anda</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
