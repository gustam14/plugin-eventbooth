<?php
declare(strict_types=1);

/**
 * Akses key-value ke tabel `app_settings` (pengaturan level platform
 * yang diubah dari dashboard Super Admin).
 *
 * Kolom tabel: `key` (PK), `value`, is_encrypted, updated_by,
 * updated_at. Kelas ini TIDAK melakukan enkripsi sendiri — pemanggil
 * yang memutuskan: nilai sensitif dienkripsi dulu dengan Crypto,
 * lalu disimpan dengan flag $encrypted = true agar jelas di database
 * bahwa isinya bukan teks biasa.
 */
final class Settings
{
    /** Nilai mentah sebuah pengaturan, atau null bila belum ada. */
    public static function get(PDO $pdo, string $key): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT `value` FROM app_settings WHERE `key` = :key LIMIT 1'
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        if ($row === false || $row['value'] === null) {
            return null;
        }

        return (string) $row['value'];
    }

    /**
     * Simpan (sisip atau perbarui) sebuah pengaturan.
     *
     * @param bool $encrypted Tandai baris is_encrypted (nilai sudah
     *                        dienkripsi pemanggil lewat Crypto).
     */
    public static function set(
        PDO $pdo,
        string $key,
        ?string $value,
        bool $encrypted = false,
        ?int $updatedBy = null
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, is_encrypted, updated_by)
             VALUES (:key, :value, :encrypted, :updated_by)
             ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                is_encrypted = VALUES(is_encrypted),
                updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'encrypted' => $encrypted ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
    }
}
