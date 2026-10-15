-- =============================================================================
-- Mini Project 4: Relational Database + Advanced PHP
-- Inventory Management System Database Schema
-- Penerus dari Mini Project 3 (CRUD Dasar -> Relasi, Transaksi, Laporan, Role)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_db;

-- 1. Tabel Users (Authentication & Authorization)
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS product_suppliers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabel User Profiles (Relasi 1-to-1 dengan users)
CREATE TABLE user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(25) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3. Tabel Categories (Relasi 1-to-Many dengan products)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Tabel Suppliers (Relasi Many-to-Many dengan products)
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100) NULL,
    phone VARCHAR(25) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Tabel Products (Tabel Inti Master Produk)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 6. Tabel Product Suppliers (Junction Table: Relasi Many-to-Many products <-> suppliers)
CREATE TABLE product_suppliers (
    product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    supplier_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, supplier_id),
    CONSTRAINT fk_ps_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ps_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 7. Tabel Stock Movements (Transaksi & Audit Trail: Relasi 1-to-Many dengan products & users)
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    reference_no VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movement_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_movement_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =============================================================================
-- SEED DATA (DATA AWAL UNTUK PENGUJIAN DAN DEMO)
-- =============================================================================

-- Seed Users (Password: admin123 untuk admin, staff123 untuk staff)
INSERT INTO users (id, username, password_hash, role) VALUES
(1, 'admin', '$2y$10$wKvh5Ta1Yzkj/gBz7/6/m.Zo7a40XeeJRqpsOHCcV9tq1UvEB2w1i', 'admin'),
(2, 'staff', '$2y$10$WqAxXe0KHhJsY9HqU..yuuQN0pwJPtIZgU5wtVKd0riOWHEGdvd5y', 'staff');

-- Seed Profiles (1-to-1)
INSERT INTO user_profiles (user_id, full_name, email, phone, address) VALUES
(1, 'Gamaliel Toto Sinulingga (Admin)', 'admin@inventory.local', '081234567890', 'Head Office Lt. 4, Jakarta'),
(2, 'Budi Santoso (Staff Gudang)', 'staff@inventory.local', '089876543210', 'Warehouse Central Blok B-12, Bekasi');

-- Seed Categories (1-to-Many parent)
INSERT INTO categories (id, name, description) VALUES
(1, 'Elektronik & Gadget', 'Perangkat elektronik dan gadget portabel'),
(2, 'Komputer & Laptop', 'Perangkat PC, laptop, dan komponen pendukung'),
(3, 'Aksesoris Komputer', 'Periferal seperti mouse, keyboard, headset, dan adaptor'),
(4, 'Jaringan & Server', 'Router, switch, access point, dan kabel ethernet'),
(5, 'Peralatan Kantor', 'Perlengkapan inventaris kantor dan ATK');

-- Seed Suppliers (Many-to-Many)
INSERT INTO suppliers (id, name, contact_name, phone, email, address) VALUES
(1, 'PT Surya Prima Teknologi', 'Hendra Wijaya', '021-5551234', 'sales@suryaprima.co.id', 'Kawasan Industri Pulogadung, Jakarta Timur'),
(2, 'CV Global Mandiri Logistik', 'Siti Rahmawati', '021-7778899', 'order@globalmandiri.com', 'Mangga Dua Square Blok F No. 10, Jakarta Utara'),
(3, 'PT Nusantara Distribusi Jaya', 'Eko Prasetyo', '022-4445566', 'distribusi@nusantarajaya.id', 'Jl. Asia Afrika No. 120, Bandung');

-- Seed Products
-- Catatan: SKU unik, gambar produk tersimpan di public/assets/images/products/
INSERT INTO products (id, category_id, sku, name, description, image, price, stock, minimum_stock) VALUES
(1, 2, 'PRD-LPT-001', 'MacBook Air M2 256GB', 'Laptop Apple Chip M2 Midnight Blue 8GB Unified Memory', 'assets/images/products/macbook_air_m2.jpg', 16500000.00, 12, 5),
(2, 2, 'PRD-LPT-002', 'Lenovo ThinkPad E14 Gen 4', 'Laptop Bisnis Intel Core i5-1235U 16GB RAM 512GB SSD', 'assets/images/products/thinkpad_e14.jpg', 12300000.00, 8, 5),
(3, 3, 'PRD-ACC-001', 'Logitech MX Master 3S', 'Wireless Performance Mouse Silent Clicks 8K DPI Dark Gray', 'assets/images/products/logitech_mx_master.jpg', 1599000.00, 25, 8),
(4, 3, 'PRD-ACC-002', 'Keychron K2 V2 Wireless', 'Mechanical Keyboard 75% Gateron G Pro Red Switch RGB Backlit', 'assets/images/products/keychron_k2.jpg', 1250000.00, 3, 5), -- Low stock alert!
(5, 3, 'PRD-ACC-003', 'Dell UltraSharp U2723QE 27"', '4K UHD IPS Black Monitor USB-C Hub Color Accurate', 'assets/images/products/dell_ultrasharp.jpg', 8900000.00, 6, 4),
(6, 4, 'PRD-NET-001', 'MikroTik RouterBOARD RB750Gr3', 'Router hEX 5x Gigabit Ethernet Dual Core 880MHz', 'assets/images/products/mikrotik_router.jpg', 850000.00, 15, 6),
(7, 4, 'PRD-NET-002', 'Ubiquiti UniFi U6-Pro', 'WiFi 6 Dual-Band Access Point High Performance', 'assets/images/products/unifi_u6_pro.jpg', 2850000.00, 2, 5), -- Low stock alert!
(8, 1, 'PRD-ELC-001', 'Anker 737 Power Bank 24000mAh', 'PowerCore 24K 140W Fast Charging Digital Display', 'assets/images/products/anker_737.jpg', 1899000.00, 18, 5),
(9, 5, 'PRD-OFC-001', 'Ergonomic Mesh Chair ErgoPro', 'Kursi Kerja Ergonomis Lumbar Support Adjustable Armrest', 'assets/images/products/ergonomic_chair.jpg', 2450000.00, 10, 4),
(10, 5, 'PRD-OFC-002', 'Standing Desk Electric 140x60', 'Meja Kerja Naik Turun Otomatis Dual Motor Memory Preset', 'assets/images/products/standing_desk.jpg', 3750000.00, 5, 3);

-- Seed Many-to-Many: Product Suppliers
INSERT INTO product_suppliers (product_id, supplier_id, supplier_price, is_primary) VALUES
(1, 1, 15200000.00, 1),
(1, 3, 15400000.00, 0),
(2, 2, 11200000.00, 1),
(3, 1, 1350000.00, 1),
(3, 2, 1380000.00, 0),
(4, 2, 1050000.00, 1),
(5, 1, 7900000.00, 1),
(6, 3, 720000.00, 1),
(7, 3, 2450000.00, 1),
(8, 2, 1600000.00, 1),
(9, 3, 2050000.00, 1),
(10, 3, 3150000.00, 1);

-- Seed Stock Movements (Histori Transaksi Masuk & Keluar)
INSERT INTO stock_movements (id, product_id, user_id, type, quantity, reference_no, notes, created_at) VALUES
(1, 1, 1, 'IN', 15, 'PO-2026-001', 'Penerimaan batch perdana unit MacBook dari PT Surya Prima', '2026-10-01 09:30:00'),
(2, 1, 2, 'OUT', 3, 'DO-2026-015', 'Pengeluaran 3 unit untuk Divisi Engineering', '2026-10-03 14:15:00'),
(3, 2, 1, 'IN', 10, 'PO-2026-002', 'Penerimaan stok laptop Lenovo ThinkPad dari CV Global Mandiri', '2026-10-02 11:00:00'),
(4, 2, 2, 'OUT', 2, 'DO-2026-022', 'Alokasi 2 unit untuk tim Finance & Accounting', '2026-10-05 16:45:00'),
(5, 3, 1, 'IN', 30, 'PO-2026-003', 'Penerimaan 30 unit Mouse MX Master 3S', '2026-10-04 10:20:00'),
(6, 3, 2, 'OUT', 5, 'DO-2026-031', 'Distribusi mouse ke tim Designer', '2026-10-07 13:00:00'),
(7, 4, 1, 'IN', 10, 'PO-2026-004', 'Restock keyboard Keychron K2', '2026-10-06 09:10:00'),
(8, 4, 2, 'OUT', 7, 'DO-2026-044', 'Pengambilan batch keyboard oleh tim IT Support', '2026-10-09 15:30:00'),
(9, 6, 1, 'IN', 20, 'PO-2026-005', 'Pengadaan router MikroTik untuk proyek cabang baru', '2026-10-08 10:00:00'),
(10, 6, 2, 'OUT', 5, 'DO-2026-050', 'Pemasangan router di kantor cabang Bandung', '2026-10-10 11:20:00'),
(11, 7, 1, 'IN', 5, 'PO-2026-006', 'Pengadaan access point UniFi U6-Pro', '2026-10-11 08:45:00'),
(12, 7, 2, 'OUT', 3, 'DO-2026-058', 'Instalasi AP di lantai 2 dan 3', '2026-10-12 14:00:00');
