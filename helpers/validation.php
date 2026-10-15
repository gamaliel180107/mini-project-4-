<?php
/**
 * Helper Validasi Input (Syntactic & Semantic Validation)
 */

function validateInt($value, int $min = 1, ?int $max = null): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) {
        return null;
    }
    if ($filtered < $min) {
        return null;
    }
    if ($max !== null && $filtered > $max) {
        return null;
    }
    return $filtered;
}

function validateFloat($value, float $min = 0.0): ?float {
    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($filtered === false) {
        return null;
    }
    if ($filtered < $min) {
        return null;
    }
    return $filtered;
}

function validateString(?string $value, int $minLength = 1, int $maxLength = 255): ?string {
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    $len = mb_strlen($trimmed);
    if ($len < $minLength || $len > $maxLength) {
        return null;
    }
    return $trimmed;
}

function validateSku(?string $sku): ?string {
    $clean = validateString($sku, 3, 50);
    if (!$clean) {
        return null;
    }
    // Format SKU: Huruf, angka, strip, underscore, atau titik
    if (!preg_match('/^[A-Za-z0-9\-\_\.]+$/', $clean)) {
        return null;
    }
    return strtoupper($clean);
}

function validateEmail(?string $email): ?string {
    if (!$email) {
        return null;
    }
    $filtered = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    return $filtered !== false ? $filtered : null;
}

/**
 * Validasi allowlist untuk kolom sorting
 */
function validateSortColumn(string $column, array $allowedColumns, string $default): string {
    return in_array($column, $allowedColumns, true) ? $column : $default;
}

/**
 * Validasi allowlist untuk arah sorting
 */
function validateSortDirection(string $direction, string $default = 'DESC'): string {
    $upper = strtoupper($direction);
    return in_array($upper, ['ASC', 'DESC'], true) ? $upper : $default;
}
