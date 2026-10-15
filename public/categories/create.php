<?php
/**
 * Modul Kategori - Tambah Kategori Baru (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$pageTitle = 'Tambah Kategori Produk';
$pageSubtitle = 'Buat kelompok kategori baru untuk mengorganisasi produk';
$baseUrl = getBaseUrl();

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid.';
    } else {
        $name = validateString($_POST['name'] ?? '', 2, 100);
        $description = trim($_POST['description'] ?? '');

        if (!$name) {
            $errors[] = 'Nama kategori wajib diisi (minimal 2 karakter).';
        } else {
            // Cek keunikan nama kategori
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name LIMIT 1");
            $chk->execute(['name' => $name]);
            if ($chk->fetch()) {
                $errors[] = "Kategori dengan nama '$name' sudah terdaftar.";
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
            $stmt->execute([
                'name'        => $name,
                'description' => $description
            ]);

            setFlash('success', "Kategori '$name' berhasil ditambahkan.");
            header("Location: index.php");
            exit;
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Formulir Tambah Kategori</h3>
        <a href="index.php" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
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
                <label class="form-label" for="name">Nama Kategori <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: Server & Perangkat Jaringan" required value="<?= e($_POST['name'] ?? $name) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Deskripsi Kategori</label>
                <textarea id="description" name="description" class="form-control" placeholder="Keterangan singkat cakupan kategori ini..."><?= e($_POST['description'] ?? $description) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
