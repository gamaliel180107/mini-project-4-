<?php
/**
 * Modul Pengguna - Tambah Pengguna Baru (Admin Only)
 * Mengimplementasikan Password Hashing & Relasi 1-to-1 users ke user_profiles (Slide 6 & 23)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$pageTitle = 'Tambah Pengguna Sistem';
$pageSubtitle = 'Buat kredensial login baru dan tautkan ke profil pengguna';
$baseUrl = getBaseUrl();

$errors = [];
$username = '';
$fullName = '';
$role = 'staff';
$email = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';
        $fullName = validateString($_POST['full_name'] ?? '', 2, 100);
        $email    = validateEmail($_POST['email'] ?? '');
        $phone    = validateString($_POST['phone'] ?? '', 0, 25);
        $address  = trim($_POST['address'] ?? '');

        // Validasi Syntactic & Semantic
        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username harus terdiri dari 3-50 karakter alfanumerik atau underscore.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $chk->execute(['u' => $username]);
            if ($chk->fetch()) {
                $errors[] = "Username '$username' sudah digunakan.";
            }
        }

        if (strlen($password) < 6) {
            $errors[] = 'Kata sandi (password) minimal 6 karakter.';
        }

        if (!$fullName) {
            $errors[] = 'Nama lengkap wajib diisi (minimal 2 karakter).';
        }

        if ($_POST['email'] !== '' && !$email) {
            $errors[] = 'Format alamat email tidak valid.';
        }

        if (empty($errors)) {
            // Password Hashing Bcrypt (Slide 23: Password tidak boleh disimpan sebagai teks biasa)
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Eksekusi Transaksi Database untuk Relasi 1-to-1 (Slide 6)
            $pdo->beginTransaction();
            try {
                // 1. Simpan ke tabel users
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role)
                    VALUES (:username, :password_hash, :role)
                ");
                $stmtUser->execute([
                    'username'      => $username,
                    'password_hash' => $passwordHash,
                    'role'          => $role
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                // 2. Simpan data profil ke user_profiles (FK + UNIQUE user_id)
                $stmtProfile = $pdo->prepare("
                    INSERT INTO user_profiles (user_id, full_name, email, phone, address)
                    VALUES (:user_id, :full_name, :email, :phone, :address)
                ");
                $stmtProfile->execute([
                    'user_id'   => $newUserId,
                    'full_name' => $fullName,
                    'email'     => $email,
                    'phone'     => $phone,
                    'address'   => $address
                ]);

                $pdo->commit();
                setFlash('success', "Akun pengguna '@{$username}' berhasil didaftarkan dengan peran {$role}.");
                header("Location: index.php");
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal menyimpan pengguna: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Formulir Tambah Pengguna & Profil</h3>
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
                    1. Informasi Kredensial Akun (Tabel users)
                </h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: agus_logistik" required value="<?= e($_POST['username'] ?? $username) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="role">Hak Akses / Peran (Role) <span class="required">*</span></label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="staff" <?= ($_POST['role'] ?? $role) === 'staff' ? 'selected' : '' ?>>Staff Gudang (Operasional & Transaksi)</option>
                            <option value="admin" <?= ($_POST['role'] ?? $role) === 'admin' ? 'selected' : '' ?>>Administrator (Akses Penuh)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="password">Kata Sandi (Password) <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                    <span class="form-text">Kata sandi akan otomatis dienkripsi dengan algoritma Bcrypt (Slide 23).</span>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 14px;">
                    2. Biodata & Profil Pengguna (Relasi 1-to-1: user_profiles)
                </h4>

                <div class="form-group">
                    <label class="form-label" for="full_name">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Contoh: Agus Setiawan, S.Kom" required value="<?= e($_POST['full_name'] ?? $fullName) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="email">Alamat Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Contoh: agus@inventory.local" value="<?= e($_POST['email'] ?? $email) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Nomor Telepon</label>
                        <input type="text" id="phone" name="phone" class="form-control" placeholder="Contoh: 08123456789" value="<?= e($_POST['phone'] ?? $phone) ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="address">Alamat / Lokasi Penugasan</label>
                    <textarea id="address" name="address" class="form-control" placeholder="Contoh: Warehouse Blok C-3, Cikarang..."><?= e($_POST['address'] ?? $address) ?></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Pengguna Baru</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
