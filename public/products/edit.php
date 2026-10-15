<?php
/**
 * Modul Produk - Ubah Data Produk (Admin Only)
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$baseUrl = getBaseUrl();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// Ambil data produk saat ini
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('danger', 'Produk yang dicari tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Ambil supplier utama saat ini jika ada
$stmtSup = $pdo->prepare("SELECT supplier_id, supplier_price FROM product_suppliers WHERE product_id = :id AND is_primary = 1 LIMIT 1");
$stmtSup->execute(['id' => $id]);
$currentPS = $stmtSup->fetch() ?: ['supplier_id' => '', 'supplier_price' => ''];

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$pageTitle = 'Edit Produk: ' . $product['name'];
$pageSubtitle = 'Perbarui atribut produk, kategori, harga, atau konfigurasi supplier';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid. Silakan coba lagi.';
    } else {
        $sku           = validateSku($_POST['sku'] ?? '');
        $name          = validateString($_POST['name'] ?? '', 3, 150);
        $categoryId    = validateInt($_POST['category_id'] ?? 0);
        $price         = validateFloat($_POST['price'] ?? 0);
        $minimumStock  = validateInt($_POST['minimum_stock'] ?? 5, 0);
        $description   = trim($_POST['description'] ?? '');
        $image         = trim($_POST['image'] ?? '');
        $supplierId    = validateInt($_POST['supplier_id'] ?? 0);
        $supplierPrice = validateFloat($_POST['supplier_price'] ?? 0);

        if (!$sku) {
            $errors[] = 'SKU wajib berupa kode alfanumerik (3-50 karakter).';
        } else {
            // Cek apakah SKU dipakai produk lain
            $chk = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
            $chk->execute(['sku' => $sku, 'id' => $id]);
            if ($chk->fetch()) {
                $errors[] = "SKU '$sku' sudah digunakan oleh produk lain.";
            }
        }

        if (!$name) {
            $errors[] = 'Nama produk wajib diisi (minimal 3 karakter).';
        }

        if (!$categoryId) {
            $errors[] = 'Kategori produk wajib dipilih.';
        }

        if ($price === null || $price <= 0) {
            $errors[] = 'Harga produk harus berupa angka positif lebih dari nol.';
        }

        if ($minimumStock === null) {
            $errors[] = 'Batas minimum stok tidak boleh negatif.';
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Update tabel products
                $upd = $pdo->prepare("
                    UPDATE products 
                    SET category_id = :category_id,
                        sku = :sku,
                        name = :name,
                        description = :description,
                        image = :image,
                        price = :price,
                        minimum_stock = :minimum_stock
                    WHERE id = :id
                ");
                $upd->execute([
                    'category_id'   => $categoryId,
                    'sku'           => $sku,
                    'name'          => $name,
                    'description'   => $description,
                    'image'         => $image ?: null,
                    'price'         => $price,
                    'minimum_stock' => $minimumStock,
                    'id'            => $id
                ]);

                // Update relasi supplier di product_suppliers
                if ($supplierId) {
                    $sPrice = ($supplierPrice !== null && $supplierPrice > 0) ? $supplierPrice : $price;
                    $updPS = $pdo->prepare("
                        INSERT INTO product_suppliers (product_id, supplier_id, supplier_price, is_primary)
                        VALUES (:product_id, :supplier_id, :supplier_price, 1)
                        ON DUPLICATE KEY UPDATE supplier_price = :upd_price, is_primary = 1
                    ");
                    $updPS->execute([
                        'product_id'     => $id,
                        'supplier_id'    => $supplierId,
                        'supplier_price' => $sPrice,
                        'upd_price'      => $sPrice
                    ]);
                }

                $pdo->commit();
                setFlash('success', "Data produk '{$name}' berhasil diperbarui.");
                header("Location: index.php");
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memperbarui produk: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Ubah Data Produk
        </h3>
        <a href="index.php" class="btn btn-secondary btn-sm">Batal / Kembali</a>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <div>
                    <strong>Terdapat kesalahan:</strong>
                    <ul style="margin-left: 18px; margin-top: 6px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="sku">Kode SKU <span class="required">*</span></label>
                    <input type="text" id="sku" name="sku" class="form-control" required value="<?= e($_POST['sku'] ?? $product['sku']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Nama Produk <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required value="<?= e($_POST['name'] ?? $product['name']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="category_id">Kategori Produk <span class="required">*</span></label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? $product['category_id']) === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price">Harga Jual Satuan (Rp) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" step="1000" min="1000" class="form-control" required value="<?= e($_POST['price'] ?? (int)$product['price']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Stok Fisik Saat Ini</label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: center;">
                        <span><?= (int)$product['stock'] ?> unit</span>
                        <span style="font-size: 12px; font-weight: normal; color: var(--text-muted);">Diubah via menu Transaksi Stok</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="minimum_stock">Ambang Minimum Stok <span class="required">*</span></label>
                    <input type="number" id="minimum_stock" name="minimum_stock" min="0" class="form-control" value="<?= e($_POST['minimum_stock'] ?? $product['minimum_stock']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Deskripsi Produk</label>
                <textarea id="description" name="description" class="form-control"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="image">Jalur File Gambar Produk (Relatif)</label>
                <?php if (!empty($product['image']) && file_exists(__DIR__ . '/../../public/' . $product['image'])): ?>
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 10px;">
                        <img src="<?= $baseUrl ?>/public/<?= e($product['image']) ?>" alt="Preview" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            <strong>Gambar Aktif:</strong> <?= e($product['image']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="text" id="image" name="image" class="form-control" placeholder="Contoh: assets/images/products/nama_gambar.jpg" value="<?= e($_POST['image'] ?? $product['image']) ?>">
                <span class="form-text">Gambar diletakkan di dalam folder <code>public/assets/images/products/</code>.</span>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--primary);">
                    Relasi Supplier
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="supplier_id">Pemasok / Supplier Utama</label>
                        <select id="supplier_id" name="supplier_id" class="form-control">
                            <option value="">-- Tidak Terhubung / Kosongkan --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" <?= ((int)($_POST['supplier_id'] ?? $currentPS['supplier_id']) === (int)$sup['id']) ? 'selected' : '' ?>>
                                    <?= e($sup['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="supplier_price">Harga Beli Supplier (Rp)</label>
                        <input type="number" id="supplier_price" name="supplier_price" step="1000" min="0" class="form-control" value="<?= e($_POST['supplier_price'] ?? (int)$currentPS['supplier_price']) ?>">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
