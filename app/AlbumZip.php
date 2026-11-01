<?php
declare(strict_types=1);

/**
 * AlbumZip (v0.17.0): ZIP berisi semua berkas satu sesi
 * untuk diunduh tenant dari Kelola Album (cadangan sebelum
 * retensi membersihkan sesi, atau arsip pribadi tenant).
 *
 * Ditulis tangan dengan metode STORE (tanpa kompresi —
 * JPEG/MP4 memang nyaris tidak memampat) agar tidak
 * bergantung ekstensi zip PHP di shared hosting. Berkas
 * diunduh dari Drive ke berkas sementara lebih dulu
 * (prepare), baru dialirkan (send): kegagalan unduhan
 * tidak pernah menghasilkan ZIP setengah jadi.
 */
final class AlbumZip
{
    public const MAX_FILES = 200;
    public const MAX_BYTES = 314572800; // 300 MB

    /**
     * Unduh + siapkan semua berkas sesi. Token akses per
     * akun Drive disediakan pemanggil.
     *
     * @param array<string, mixed>              $session Baris sessionWithEvent.
     * @param array<int, array<string, mixed>>  $media
     * @param callable(int): string             $tokenFor
     * @return array{entries: array<int, array{path: string, name: string}>}
     */
    public static function prepare(array $session, array $media, callable $tokenFor): array
    {
        if ($media === []) {
            throw new RuntimeException('Sesi ini belum memiliki berkas untuk diunduh.');
        }
        if (count($media) > self::MAX_FILES) {
            throw new RuntimeException(
                'Sesi ini memiliki lebih dari ' . self::MAX_FILES
                . ' berkas — unduh per berkas dari galeri.'
            );
        }
        $total = 0;
        foreach ($media as $m) {
            $total += (int) ($m['size_bytes'] ?? 0);
        }
        if ($total > self::MAX_BYTES) {
            throw new RuntimeException(
                'Ukuran album sesi ini (' . (int) round($total / 1048576)
                . ' MB) melebihi batas unduh ZIP 300 MB — unduh per berkas dari galeri.'
            );
        }

        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($session['code'] ?? 'sesi')) ?? 'sesi';
        $fallbackAccount = (int) ($session['event_drive_account_id'] ?? 0);

        $entries = [];
        $usedNames = [];
        try {
            foreach ($media as $m) {
                $accountId = (int) ($m['drive_account_id'] ?? 0);
                if ($accountId <= 0) {
                    $accountId = $fallbackAccount;
                }
                $tmp = self::downloadToTemp(
                    $tokenFor($accountId),
                    (string) ($m['drive_file_id'] ?? '')
                );
                $base = (string) ($m['kind'] ?? 'final') . '_' . self::cleanName((string) ($m['file_name'] ?? 'berkas'));
                $name = $code . '/' . $base;
                $i = 2;
                while (isset($usedNames[$name])) {
                    $name = $code . '/' . $i . '_' . $base;
                    $i++;
                }
                $usedNames[$name] = true;
                $entries[] = ['path' => $tmp, 'name' => $name];
            }
        } catch (Throwable $e) {
            foreach ($entries as $entry) {
                @unlink($entry['path']);
            }
            throw $e;
        }

        return ['entries' => $entries];
    }

    /**
     * Alirkan ZIP yang sudah disiapkan ke keluaran dan
     * bersihkan berkas sementaranya.
     *
     * @param array{entries: array<int, array{path: string, name: string}>} $prepared
     */
    public static function send(array $prepared): void
    {
        $entries = $prepared['entries'];
        [$dosTime, $dosDate] = self::dosTime(time());
        $offset = 0;
        $central = '';

        foreach ($entries as $entry) {
            $size = (int) @filesize($entry['path']);
            $crc = (int) hexdec(hash_file('crc32b', $entry['path']));
            $nameLen = strlen($entry['name']);
            $header = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0
            );
            $entryOffset = $offset;
            echo $header . $entry['name'];
            $offset += strlen($header) + $nameLen;
            $fh = @fopen($entry['path'], 'rb');
            if ($fh !== false) {
                while (!feof($fh)) {
                    $chunk = fread($fh, 65536);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $offset += strlen($chunk);
                }
                fclose($fh);
            }
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size,
                $nameLen, 0, 0, 0, 0, 0, $entryOffset
            ) . $entry['name'];
            @unlink($entry['path']);
        }

        echo $central;
        echo pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0
        );
    }

    /** Unduh satu berkas Drive (alt=media) ke berkas sementara. */
    private static function downloadToTemp(string $token, string $fileId): string
    {
        if ($fileId === '') {
            throw new RuntimeException('Ada berkas tanpa ID penyimpanan, ZIP tidak dapat dibuat.');
        }
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
            . '?alt=media&supportsAllDrives=true';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\n",
                'ignore_errors' => true,
                'timeout' => 120,
            ],
        ]);
        $in = @fopen($url, 'rb', false, $context);
        if ($in === false) {
            throw new RuntimeException('Unduhan dari penyimpanan gagal dimulai.');
        }
        $status = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', (string) $h, $mm) === 1) {
                $status = (int) $mm[1];
            }
        }
        if ($status !== 200) {
            fclose($in);
            throw new RuntimeException('Penyimpanan menolak unduhan salah satu berkas (HTTP ' . $status . ').');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ebzip');
        if ($tmp === false) {
            fclose($in);
            throw new RuntimeException('Berkas sementara tidak dapat dibuat di server.');
        }
        $out = @fopen($tmp, 'wb');
        if ($out === false) {
            fclose($in);
            @unlink($tmp);
            throw new RuntimeException('Berkas sementara tidak dapat ditulis di server.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        return $tmp;
    }

    /** Nama berkas aman di dalam ZIP (tanpa pemisah jalur). */
    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', '_', $name) ?? '';
        $name = trim($name, ' .');
        return $name !== '' ? $name : 'berkas';
    }

    /** @return array{0: int, 1: int} [waktu DOS, tanggal DOS] */
    private static function dosTime(int $ts): array
    {
        $d = getdate($ts);
        $time = ((int) $d['hours'] << 11) | ((int) $d['minutes'] << 5) | intdiv((int) $d['seconds'], 2);
        $date = (((int) $d['year'] - 1980) << 9) | ((int) $d['mon'] << 5) | (int) $d['mday'];
        return [$time, $date];
    }
}
