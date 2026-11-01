<?php
declare(strict_types=1);

/**
 * Kegagalan operasi Midtrans. $gatewayResponse menyimpan respons
 * mentah terakhir dari Midtrans (bila ada) agar alasan penolakan
 * gateway dapat ditampilkan apa adanya di dashboard untuk
 * diagnosis — tanpa tebak-tebakan.
 */
final class MidtransException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?array $gatewayResponse = null
    ) {
        parent::__construct($message);
    }
}
