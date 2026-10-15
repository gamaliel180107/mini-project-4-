<?php
/**
 * Modul Pengguna - Edit Pengguna & Profil (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Pengguna tidak valid.');
    header("Location: index.php");
    exit;
}

// Ambil data user dan profil (1-to-1)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, p.full_name, p.email, p.phone, p.address
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'Pengguna tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$errors = [];
$pageTitle = 'Edit Pengguna: @' . $user['username'];
$pageSubtitle = 'Perbarui hak akses, kata sandi, atau biodata profil';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid.';
    } else {
        $role        = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : $user['role'];
        $fullName    = validateString($_POST['full_name'] ?? '', 2, 100);
        $email       = validateEmail($_POST['email'] ?? '');
        $phone       = validateString($_POST['phone'] ?? '', 0, 25);
        $address     = trim($_POST['address'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if (!$fullName) {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        if ($_POST['email'] !== '' && !$email) {
            $errors[] = 'Format email tidak valid.';
        }

        if ($newPassword !== '' && strlen($newPassword) < 6) {
            $errors[] = 'Kata sandi baru minimal 6 karakter.';
        }

        // Mencegah admin mengubah role dirinya sendiri menjadi staff jika dia admin yang sedang login
        if ($id === currentUser()['id'] && $role !== 'admin') {
            $errors[] = 'Anda tidak dapat menurunkan hak akses akun Anda sendiri dari Administrator.';
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // 1. Update tabel users
                if ($newPassword !== '') {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updUser = $pdo->prepare("UPDATE users SET role = :role, password_hash = :hash WHERE id = :id");
                    $updUser->execute(['role' => $role, 'hash' => $newHash, 'id' => $id]);
                } else {
                    $updUser = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
                    $updUser->execute(['role' => $role, 'id' => $id]);
                }

                // 2. Update atau Insert tabel user_profiles (1-to-1)
                $updProfile = $pdo->prepare("
                    INSERT INTO user_profiles (user_id, full_name, email, phone, address)
                    VALUES (:user_id, :full_name, :email, :phone, :address)
                    ON DUPLICATE KEY UPDATE 
                        full_name = VALUES(full_name),
                        email = VALUES(email),
                        phone = VALUES(phone),
                        address = VALUES(address)
                ");
                $updProfile->execute([
                    'user_id'   => $id,
                    'full_name' => $fullName,
                    'email'     => $email,
                    'phone'     => $phone,
                    'address'   => $address
                ]);

                $pdo->commit();

                // Jika mengedit akun diri sendiri, perbarui session
                if ($id === currentUser()['id']) {
                    $_SESSION['user']['full_name'] = $fullName;
                    $_SESSION['user']['email']     = $email;
                    $_SESSION['user']['role']      = $role;
                }

                setFlash('success', "Data pengguna '@{$user['username']}' berhasil diperbarui.");
                header("Location: index.php");
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memperbarui pengguna: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Edit Akun & Biodata Pengguna</h3>
        <a href="index.php" class="btn btn-secondary btn-sm">Kembali</a>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <div>
                    <strong>Terdapat kesalahan:</strong>
                    <ul style="margin-left: 18px; margin-top: 4px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 14px;">
                    1. Hak Akses & Kredensial
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                        <span class="form-text">Username bersifat permanen dan tidak dapat diubah.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="role">Hak Akses / Peran (Role) <span class="required">*</span></label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="staff" <?= ($_POST['role'] ?? $user['role']) === 'staff' ? 'selected' : '' ?>>Staff Gudang</option>
                            <option value="admin" <?= ($_POST['role'] ?? $user['role']) === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="new_password">Ganti Kata Sandi (Opsional)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 14px;">
                    2. Biodata & Profil Pengguna (1-to-1)
                </h4>

                <div class="form-group">
                    <label class="form-label" for="full_name">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="email">Alamat Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $user['email']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Nomor Telepon</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="address">Alamat / Lokasi</label>
                    <textarea id="address" name="address" class="form-control"><?= e($_POST['address'] ?? $user['address']) ?></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
