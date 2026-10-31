<?php
declare(strict_types=1);

/**
 * Shim kompatibilitas — dipakai HANYA bila document root subdomain
 * menunjuk root repo ini (bukan folder public/), misalnya panel
 * hosting tidak mengizinkan mengganti document root.
 *
 * Bila document root sudah menunjuk public/ (cara yang disarankan),
 * berkas ini tidak pernah dipakai.
 */

require __DIR__ . '/public/index.php';
