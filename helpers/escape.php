<?php
/**
 * Helper Fungsi Sanitasi & Formatting Output (Pencegahan XSS)
 */

if (!function_exists('e')) {
    /**
     * Escape output HTML untuk mencegah Stored & Reflected XSS
     */
    function e(?string $value): string {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatRupiah')) {
    /**
     * Format angka ke Rupiah
     */
    function formatRupiah($amount): string {
        $num = (float)$amount;
        return 'Rp ' . number_format($num, 0, ',', '.');
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format tanggal Indonesia
     */
    function formatDate(?string $datetime, bool $withTime = true): string {
        if (!$datetime) {
            return '-';
        }
        $time = strtotime($datetime);
        if (!$time) {
            return e($datetime);
        }
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $d = date('j', $time);
        $m = $bulan[(int)date('n', $time)];
        $y = date('Y', $time);
        
        if ($withTime) {
            $hi = date('H:i', $time);
            return "$d $m $y, $hi WIB";
        }
        return "$d $m $y";
    }
}

if (!function_exists('stockBadge')) {
    /**
     * Generate badge HTML status stok
     */
    function stockBadge(int $stock, int $minStock): string {
        if ($stock <= 0) {
            return '<span class="badge badge-danger"><span class="badge-dot"></span>Habis (0)</span>';
        } elseif ($stock <= $minStock) {
            return '<span class="badge badge-warning"><span class="badge-dot"></span>Stok Menipis (' . $stock . ')</span>';
        } else {
            return '<span class="badge badge-success"><span class="badge-dot"></span>Tersedia (' . $stock . ')</span>';
        }
    }
}

if (!function_exists('movementBadge')) {
    /**
     * Generate badge HTML jenis transaksi masuk / keluar
     */
    function movementBadge(string $type): string {
        $typeUpper = strtoupper($type);
        if ($typeUpper === 'IN') {
            return '<span class="badge badge-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg> MASUK (IN)</span>';
        } elseif ($typeUpper === 'OUT') {
            return '<span class="badge badge-danger"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg> KELUAR (OUT)</span>';
        }
        return '<span class="badge badge-secondary">' . e($type) . '</span>';
    }
}

if (!function_exists('roleBadge')) {
    /**
     * Generate badge HTML untuk Role Pengguna
     */
    function roleBadge(string $role): string {
        $roleLower = strtolower($role);
        if ($roleLower === 'admin') {
            return '<span class="badge badge-primary">Administrator</span>';
        } elseif ($roleLower === 'staff') {
            return '<span class="badge badge-info">Staff Gudang</span>';
        }
        return '<span class="badge badge-secondary">' . e($role) . '</span>';
    }
}
