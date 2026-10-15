<?php
/**
 * Modul Supplier - Tambah Supplier Baru (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$pageTitle = 'Tambah Rekanan Supplier';
$pageSubtitle = 'Mendaftarkan vendor/distributor rekanan pasokan barang';
$baseUrl = getBaseUrl();

$errors = [];
$name = '';
$contactName = '';
$phone = '';
$email = '';
$address = '';

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
            $errors[] = 'Nama supplier/perusahaan wajib diisi (minimal 3 karakter).';
        }

        if ($_POST['email'] !== '' && !$email) {
            $errors[] = 'Format alamat email tidak valid.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, contact_name, phone, email, address)
                VALUES (:name, :contact_name, :phone, :email, :address)
            ");
            $stmt->execute([
                'name'         => $name,
                'contact_name' => $contactName,
                'phone'        => $phone,
                'email'        => $email,
                'address'      => $address
            ]);

            setFlash('success', "Supplier '$name' berhasil ditambahkan.");
            header("Location: index.php");
            exit;
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Formulir Pendaftaran Supplier Baru</h3>
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
                <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: PT Surya Prima Distribusi" required value="<?= e($_POST['name'] ?? $name) ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="contact_name">Kontak Person (PIC)</label>
                    <input type="text" id="contact_name" name="contact_name" class="form-control" placeholder="Nama PIC Penjualan" value="<?= e($_POST['contact_name'] ?? $contactName) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Nomor Telepon / WhatsApp</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="Contoh: 021-5551234 / 0812345678" value="<?= e($_POST['phone'] ?? $phone) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Alamat Email Resmi</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Contoh: sales@perusahaan.co.id" value="<?= e($_POST['email'] ?? $email) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Alamat Kantor / Pergudangan</label>
                <textarea id="address" name="address" class="form-control" placeholder="Alamat lengkap lokasi distributor..."><?= e($_POST['address'] ?? $address) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
