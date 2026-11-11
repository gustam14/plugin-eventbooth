<?php

declare(strict_types=1);

/**
 * Kegagalan memilih akun Google Drive untuk sebuah event
 * (v0.27.0). $kind membedakan dua keadaan agar pemanggil API
 * dapat menjawab dengan kode yang tepat:
 *  - 'not_configured': belum ada akun penyimpanan yang dapat
 *    ditugaskan sama sekali (503 drive_not_configured).
 *  - 'token_failed': akun tersedia tetapi tokennya ditolak
 *    Google / tidak ada akun sehat yang aman dipakai event ini
 *    (502 drive_error).
 * Merupakan RuntimeException agar jalur dashboard (Kelola
 * Album) yang menangkap RuntimeException tetap bekerja.
 */
final class DriveResolutionException extends RuntimeException
{
    public function __construct(string $message, public readonly string $kind)
    {
        parent::__construct($message);
    }
}
