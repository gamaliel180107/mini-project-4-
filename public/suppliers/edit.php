<?php
/**
 * Modul Supplier - Edit Data Supplier (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    setFlash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$errors = [];
$pageTitle = 'Edit Supplier: ' . $supplier['name'];
$pageSubtitle = 'Perbarui profil kontak atau alamat vendor rekanan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid.';
    } else {
        $name        = validateString($_POST['name'] ?? '', 3, 150);
        $contactName = validateString($_POST['contact_name'] ?? '', 0, 100);
        $phone       = validateString($_POST['phone'] ?? '', 0, 25);
        $email       = validateEmail($_POST['email'] ?? '');
        $address     = trim($_POST['address'] ?? '');

        if (!$name) {
            $errors[] = 'Nama supplier wajib diisi (minimal 3 karakter).';
        }

        if ($_POST['email'] !== '' && !$email) {
            $errors[] = 'Format email tidak valid.';
        }

        if (empty($errors)) {
            $upd = $pdo->prepare("
                UPDATE suppliers 
                SET name = :name,
                    contact_name = :contact_name,
                    phone = :phone,
                    email = :email,
                    address = :address
                WHERE id = :id
            ");
            $upd->execute([
                'name'         => $name,
                'contact_name' => $contactName,
                'phone'        => $phone,
                'email'        => $email,
                'address'      => $address,
                'id'           => $id
            ]);

            setFlash('success', "Data supplier '$name' berhasil diperbarui.");
            header("Location: index.php");
            exit;
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Edit Profil Supplier</h3>
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

            <div class="form-group">
                <label class="form-label" for="name">Nama Perusahaan / Supplier <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= e($_POST['name'] ?? $supplier['name']) ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="contact_name">Kontak Person (PIC)</label>
                    <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= e($_POST['contact_name'] ?? $supplier['contact_name']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Nomor Telepon / WhatsApp</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $supplier['phone']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Alamat Email Resmi</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $supplier['email']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Alamat Kantor / Pergudangan</label>
                <textarea id="address" name="address" class="form-control"><?= e($_POST['address'] ?? $supplier['address']) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
