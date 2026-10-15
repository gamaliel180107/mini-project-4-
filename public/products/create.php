<?php
/**
 * Modul Produk - Tambah Produk Baru (Admin Only)
 * Menerapkan Validasi Syntactic & Semantic, Relasi Many-to-Many Supplier, dan Database Transaction
 */
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';
requireAdmin();

$pageTitle = 'Tambah Produk Baru';
$pageSubtitle = 'Daftarkan item barang baru ke dalam katalog dan tentukan relasi supplier';
$baseUrl = getBaseUrl();

// Ambil data kategori dan supplier untuk pilihan formulir
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$formData = [
    'sku'            => '',
    'name'           => '',
    'category_id'    => '',
    'price'          => '',
    'stock'          => '0',
    'minimum_stock'  => '5',
    'description'    => '',
    'image'          => '',
    'supplier_id'    => '',
    'supplier_price' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        // Ambil dan bersihkan data
        $formData['sku']            = validateSku($_POST['sku'] ?? '');
        $formData['name']           = validateString($_POST['name'] ?? '', 3, 150);
        $formData['category_id']    = validateInt($_POST['category_id'] ?? 0);
        $formData['price']          = validateFloat($_POST['price'] ?? 0);
        $formData['stock']          = validateInt($_POST['stock'] ?? 0, 0); // stok >= 0
        $formData['minimum_stock']  = validateInt($_POST['minimum_stock'] ?? 5, 0);
        $formData['description']    = trim($_POST['description'] ?? '');
        $formData['image']          = trim($_POST['image'] ?? '');
        $formData['supplier_id']    = validateInt($_POST['supplier_id'] ?? 0);
        $formData['supplier_price'] = validateFloat($_POST['supplier_price'] ?? 0);

        // Validasi Syntactic & Semantic (Slide 21 & 33)
        if (!$formData['sku']) {
            $errors[] = 'SKU wajib diisi dengan format alfanumerik (3-50 karakter).';
        } else {
            // Cek keunikan SKU di database
            $chkSku = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
            $chkSku->execute(['sku' => $formData['sku']]);
            if ($chkSku->fetch()) {
                $errors[] = "SKU '{$formData['sku']}' sudah terdaftar pada produk lain. SKU harus unik.";
            }
        }

        if (!$formData['name']) {
            $errors[] = 'Nama produk wajib diisi (minimal 3 karakter).';
        }

        if (!$formData['category_id']) {
            $errors[] = 'Pilih kategori produk yang valid.';
        }

        if ($formData['price'] === null || $formData['price'] <= 0) {
            $errors[] = 'Harga produk harus berupa angka positif dan lebih dari nol.';
        }

        if ($formData['stock'] === null) {
            $errors[] = 'Stok awal tidak boleh bernilai negatif.';
        }

        if ($formData['minimum_stock'] === null) {
            $errors[] = 'Batas minimum stok tidak boleh negatif.';
        }

        // Jika tidak ada error, eksekusi transaksi database (Slide 25 & 27)
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // 1. Simpan data produk ke tabel products
                $stmtProd = $pdo->prepare("
                    INSERT INTO products (category_id, sku, name, description, image, price, stock, minimum_stock)
                    VALUES (:category_id, :sku, :name, :description, :image, :price, :stock, :minimum_stock)
                ");
                $stmtProd->execute([
                    'category_id'   => $formData['category_id'],
                    'sku'           => $formData['sku'],
                    'name'          => $formData['name'],
                    'description'   => $formData['description'],
                    'image'         => $formData['image'] ?: null,
                    'price'         => $formData['price'],
                    'stock'         => $formData['stock'],
                    'minimum_stock' => $formData['minimum_stock']
                ]);
                $newProductId = (int)$pdo->lastInsertId();

                // 2. Jika supplier dipilih, hubungkan ke junction table product_suppliers (Relasi Many-to-Many)
                if ($formData['supplier_id']) {
                    $supPrice = ($formData['supplier_price'] !== null && $formData['supplier_price'] > 0)
                        ? $formData['supplier_price']
                        : $formData['price'];

                    $stmtPS = $pdo->prepare("
                        INSERT INTO product_suppliers (product_id, supplier_id, supplier_price, is_primary)
                        VALUES (:product_id, :supplier_id, :supplier_price, 1)
                    ");
                    $stmtPS->execute([
                        'product_id'     => $newProductId,
                        'supplier_id'    => $formData['supplier_id'],
                        'supplier_price' => $supPrice
                    ]);
                }

                // 3. Jika ada stok awal > 0, catat otomatis histori barang masuk di stock_movements
                if ($formData['stock'] > 0) {
                    $stmtMove = $pdo->prepare("
                        INSERT INTO stock_movements (product_id, user_id, type, quantity, reference_no, notes)
                        VALUES (:product_id, :user_id, 'IN', :quantity, 'INIT-STOCK', 'Saldo awal saat pendaftaran produk baru')
                    ");
                    $stmtMove->execute([
                        'product_id' => $newProductId,
                        'user_id'    => currentUser()['id'],
                        'quantity'   => $formData['stock']
                    ]);
                }

                $pdo->commit();
                setFlash('success', "Produk '{$formData['name']}' berhasil ditambahkan ke inventaris!");
                header("Location: index.php");
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Formulir Tambah Produk Baru
        </h3>
        <a href="index.php" class="btn btn-secondary btn-sm">Batal / Kembali</a>
    </div>

    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <div>
                    <strong>Terdapat kesalahan validasi:</strong>
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
                    <input type="text" id="sku" name="sku" class="form-control" placeholder="Contoh: PRD-ELC-001" required value="<?= e($_POST['sku'] ?? $formData['sku']) ?>">
                    <span class="form-text">Identifier unik produk (wajib unik).</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Nama Produk <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: Laptop ThinkPad T14 Gen 3" required value="<?= e($_POST['name'] ?? $formData['name']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="category_id">Kategori Produk <span class="required">*</span></label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? $formData['category_id']) === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price">Harga Jual Satuan (Rp) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" step="1000" min="1000" class="form-control" placeholder="Contoh: 1500000" required value="<?= e($_POST['price'] ?? $formData['price']) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="stock">Stok Awal Fisik</label>
                    <input type="number" id="stock" name="stock" min="0" class="form-control" value="<?= e($_POST['stock'] ?? $formData['stock']) ?>">
                    <span class="form-text">Jika &gt; 0, otomatis dicatat pada log barang masuk.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="minimum_stock">Ambang Batas Minimum Stok <span class="required">*</span></label>
                    <input type="number" id="minimum_stock" name="minimum_stock" min="0" class="form-control" value="<?= e($_POST['minimum_stock'] ?? $formData['minimum_stock']) ?>" required>
                    <span class="form-text">Alert muncul di dashboard saat stok &le; angka ini.</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Deskripsi & Spesifikasi Produk</label>
                <textarea id="description" name="description" class="form-control" placeholder="Catatan detail spesifikasi atau keterangan produk..."><?= e($_POST['description'] ?? $formData['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="image">Jalur File Gambar Produk (Relatif)</label>
                <input type="text" id="image" name="image" class="form-control" placeholder="Contoh: assets/images/products/nama_gambar.jpg" value="<?= e($_POST['image'] ?? $formData['image']) ?>">
                <span class="form-text">Gambar diletakkan di dalam folder <code>public/assets/images/products/</code>.</span>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--primary);">
                    Relasi Supplier (Many-to-Many)
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="supplier_id">Pemasok / Supplier Utama</label>
                        <select id="supplier_id" name="supplier_id" class="form-control">
                            <option value="">-- Pilih Supplier (Opsional) --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" <?= ((int)($_POST['supplier_id'] ?? $formData['supplier_id']) === (int)$sup['id']) ? 'selected' : '' ?>>
                                    <?= e($sup['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="supplier_price">Harga Beli dari Supplier (Rp)</label>
                        <input type="number" id="supplier_price" name="supplier_price" step="1000" min="0" class="form-control" placeholder="Kosongkan jika sama dengan harga jual" value="<?= e($_POST['supplier_price'] ?? $formData['supplier_price']) ?>">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <a href="index.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
