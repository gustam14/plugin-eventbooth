<?php
declare(strict_types=1);

/**
 * Kegagalan operasi Google Drive pada jalur API (Milestone 5b).
 *
 * Metode-metode GoogleDrive untuk dashboard (M3) menjawab
 * null/false secara diam-diam; jalur /api/drive/* butuh beda
 * perlakuan: kegagalan Google harus bisa dipetakan pemanggil ke
 * respons JSON 502 {error: "drive_error"}. Untuk itu metode
 * varian "OrFail" di GoogleDrive melempar exception ini.
 *
 * Pesan exception bersifat generik dan TIDAK PERNAH memuat nilai
 * token (akses maupun refresh) — aman ditelan pemanggil dan
 * diganti pesan pengguna.
 */
final class GoogleDriveException extends RuntimeException
{
}
