<?php
/**
 * Modul Kategori - Edit Kategori (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Kategori tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$category = $stmt->fetch();

if (!$category) {
    setFlash('danger', 'Kategori tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$errors = [];
$pageTitle = 'Edit Kategori: ' . $category['name'];
$pageSubtitle = 'Perbarui nama atau deskripsi kategori';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid.';
    } else {
        $name = validateString($_POST['name'] ?? '', 2, 100);
        $description = trim($_POST['description'] ?? '');

        if (!$name) {
            $errors[] = 'Nama kategori wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM categories WHERE name = :name AND id != :id LIMIT 1");
            $chk->execute(['name' => $name, 'id' => $id]);
            if ($chk->fetch()) {
                $errors[] = "Kategori dengan nama '$name' sudah digunakan.";
            }
        }

        if (empty($errors)) {
            $upd = $pdo->prepare("UPDATE categories SET name = :name, description = :description WHERE id = :id");
            $upd->execute([
                'name'        => $name,
                'description' => $description,
                'id'          => $id
            ]);

            setFlash('success', "Kategori '$name' berhasil diperbarui.");
            header("Location: index.php");
            exit;
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Edit Data Kategori</h3>
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
                <label class="form-label" for="name">Nama Kategori <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= e($_POST['name'] ?? $category['name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea id="description" name="description" class="form-control"><?= e($_POST['description'] ?? $category['description']) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
