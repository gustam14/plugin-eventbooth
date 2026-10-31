<?php
/** @var string $appName */
/** @var string $appUrl */
/** @var string $version */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A0C0F">
<title>Kebijakan Privasi — Plugin Eventbooth</title>
<meta name="description" content="Kebijakan Privasi Plugin Eventbooth: data yang kami proses, cara penggunaannya, dan hak pengguna — termasuk kesesuaian dengan Google API Services User Data Policy.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230A0C0F'/%3E%3Cg fill='none' stroke='%23C9A24B' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 26h6l4-5h16l4 5h6a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='32' cy='37' r='7'/%3E%3C/g%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: #0A0C0F;
        --panel: #12151B;
        --line: rgba(255, 255, 255, .08);
        --line-strong: rgba(255, 255, 255, .14);
        --text: #EDEFF3;
        --muted: #9AA3AF;
        --muted-2: #6E7684;
        --gold: #C9A24B;
        --gold-2: #E7C878;
        --gold-grad: linear-gradient(135deg, #C9A24B 0%, #E7C878 100%);
        --gold-ink: #1A1405;
        --font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: var(--font);
        background: var(--bg);
        color: var(--text);
        line-height: 1.65;
        font-size: 16px;
        -webkit-font-smoothing: antialiased;
        overflow-x: clip;
    }
    img, svg { max-width: 100%; }
    ::selection { background: rgba(201, 162, 75, .35); color: #fff; }
    :focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 4px; }
    a { color: var(--gold-2); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .wrap { max-width: 1160px; margin: 0 auto; padding: 0 24px; }

    /* ---------- Kepala situs (pola sama dengan landing) ---------- */
    .site-head {
        position: sticky; top: 0; z-index: 50;
        background: rgba(10, 12, 15, .82);
        -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--line);
    }
    .site-head .wrap { display: flex; align-items: center; justify-content: space-between; gap: 20px; height: 68px; }
    .brandmark { display: flex; align-items: center; gap: 11px; }
    .brandmark > a { display: flex; align-items: center; gap: 11px; }
    .brandmark > a:hover { text-decoration: none; }
    .logo-mark {
        flex: none; display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px; border-radius: 10px;
        background: var(--gold-grad); box-shadow: 0 8px 20px rgba(201, 162, 75, .30);
    }
    .brandmark-text { font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; color: var(--text); line-height: 1.15; }
    .brandmark-text .accent { background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .brandmark-sub { display: block; font-size: 10.5px; font-weight: 600; color: var(--muted-2); letter-spacing: .06em; text-transform: uppercase; }
    .back-link { color: var(--muted); font-size: 14.5px; font-weight: 600; transition: color 150ms ease; white-space: nowrap; }
    .back-link:hover { color: var(--text); text-decoration: none; }

    /* ---------- Isi dokumen legal ---------- */
    .legal-wrap { max-width: 820px; margin: 0 auto; padding: 0 24px; }
    .legal { padding: 64px 0 8px; }
    .legal .kicker {
        font-size: 12.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold-2);
    }
    .legal h1 { font-size: clamp(30px, 4vw, 42px); font-weight: 800; letter-spacing: -.025em; line-height: 1.15; margin: 12px 0 10px; }
    .legal .updated { color: var(--muted-2); font-size: 14px; margin-bottom: 12px; }
    .legal h2 { font-size: 20px; font-weight: 700; letter-spacing: -.01em; margin: 38px 0 10px; }
    .legal p { color: var(--muted); margin-bottom: 14px; }
    .legal p strong, .legal li strong { color: var(--text); }
    .legal ul { margin: 0 0 14px; padding-left: 22px; color: var(--muted); }
    .legal li { margin-bottom: 8px; }
    .legal code {
        font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        font-size: .88em; color: var(--gold-2);
        background: rgba(201, 162, 75, .10); border: 1px solid rgba(201, 162, 75, .22);
        border-radius: 6px; padding: 1px 6px;
    }
    .legal .limited-use {
        background: var(--panel); border: 1px solid var(--line); border-left: 3px solid var(--gold);
        border-radius: 12px; padding: 18px 20px; margin: 18px 0 6px;
    }
    .legal .limited-use p { margin-bottom: 10px; }
    .legal .limited-use p:last-child { margin-bottom: 0; }
    .legal .limited-use .en { font-size: 14.5px; }

    /* ---------- Kaki (pola sama dengan landing) ---------- */
    footer.site-foot { border-top: 1px solid var(--line); margin-top: 72px; }
    footer.site-foot .wrap {
        display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        padding-top: 26px; padding-bottom: 30px;
    }
    footer.site-foot p { color: var(--muted-2); font-size: 13.5px; }
    footer.site-foot p a { color: var(--muted); }
    footer.site-foot p a:hover { color: var(--gold-2); }

    @media (max-width: 768px) {
        .legal { padding: 48px 0 4px; }
        footer.site-foot .wrap { justify-content: center; text-align: center; }
    }
    @media (max-width: 520px) {
        .wrap, .legal-wrap { padding: 0 16px; }
        .site-head .wrap { height: 60px; }
        .legal h2 { font-size: 18.5px; }
        footer.site-foot { margin-top: 56px; }
    }
</style>
</head>
<body>

<header class="site-head">
    <div class="wrap">
        <div class="brandmark">
            <a href="/" aria-label="Plugin Eventbooth — ke halaman utama">
                <span class="logo-mark" aria-hidden="true">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
                </span>
                <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span><span class="brandmark-sub">by EventBooth</span></span>
            </a>
        </div>
        <a class="back-link" href="/">&larr; Kembali</a>
    </div>
</header>

<main class="legal">
    <div class="legal-wrap">
        <span class="kicker">Dokumen Legal</span>
        <h1>Kebijakan Privasi</h1>
        <p class="updated">Terakhir diperbarui: 31 Oktober 2026</p>

        <h2>Pendahuluan</h2>
        <p>
            Plugin Eventbooth adalah layanan pendamping DSLRBooth yang dioperasikan oleh
            <strong>EventBooth (SB Photobooth)</strong>, Indonesia. Layanan ini meliputi aplikasi
            pendamping di PC booth, website manajemen lisensi dan album event, serta pengiriman
            hasil foto kepada tamu. Kebijakan ini menjelaskan data apa yang kami proses, untuk
            apa, dan hak apa yang dimiliki pengguna atas datanya.
        </p>
        <p>
            Kontak kami: WhatsApp <strong>087762462482</strong> dan Instagram
            <strong>@photobooth.sb</strong>.
        </p>

        <h2>Data yang Kami Proses</h2>
        <ul>
            <li>
                <strong>Data akun operator/tenant</strong> — nama, alamat email, dan kata sandi.
                Kata sandi tidak pernah disimpan sebagai teks biasa; yang disimpan adalah
                <em>hash</em>-nya.
            </li>
            <li>
                <strong>Data akun Google yang dikaitkan pemilik layanan</strong> — alamat email
                akun dan informasi kuota penyimpanannya. Akses ke Google Drive menggunakan izin
                terbatas <code>drive.file</code>: aplikasi ini <strong>hanya</strong> dapat
                mengakses file dan folder yang dibuat oleh aplikasi ini sendiri, dan tidak dapat
                melihat isi Drive lainnya. Token akses yang diterbitkan Google disimpan
                terenkripsi di server kami.
            </li>
            <li>
                <strong>Data event dan sesi</strong> — nama event, kode sesi, waktu sesi, dan
                metadata foto, serta berkas foto hasil sesi yang diunggah oleh aplikasi booth ke
                penyimpanan layanan untuk ditampilkan di album event.
            </li>
            <li>
                <strong>Nomor WhatsApp tamu</strong> — nomor yang diisi tamu secara sukarela pada
                halaman sesi bila tamu meminta fotonya dikirim kepadanya. Nomor ini dipakai hanya
                untuk pengiriman foto tersebut.
            </li>
            <li>
                <strong>Data transaksi pembayaran</strong> — pembayaran diproses melalui Midtrans.
                Kami menerima status dan rincian transaksi yang diperlukan untuk mencatat
                pembayaran; kami <strong>tidak menyimpan nomor kartu atau kredensial
                pembayaran</strong> apa pun.
            </li>
        </ul>

        <h2>Penggunaan Data</h2>
        <p>Data di atas digunakan hanya untuk:</p>
        <ul>
            <li>mengoperasikan layanan — album event online, pengiriman foto ke tamu, serta manajemen lisensi dan pembayaran;</li>
            <li>menjaga keamanan akun dan layanan; dan</li>
            <li>memberikan dukungan kepada pengguna yang menghubungi kami.</li>
        </ul>
        <p><strong>Kami tidak menjual data pribadi</strong> kepada siapa pun.</p>

        <h2>Berbagi Data dengan Pihak Ketiga</h2>
        <p>
            Data hanya diteruskan kepada prosesor yang diperlukan agar layanan berfungsi, sebatas
            yang dibutuhkan fungsi masing-masing:
        </p>
        <ul>
            <li><strong>Google</strong> — penyimpanan berkas foto di Google Drive milik pemilik layanan;</li>
            <li><strong>Midtrans</strong> — pemrosesan pembayaran; dan</li>
            <li><strong>penyedia gateway WhatsApp</strong> — pengiriman pesan berisi foto/tautan album atas permintaan tamu.</li>
        </ul>
        <p>Di luar itu, data tidak dibagikan kepada pihak ketiga lain.</p>

        <h2>Kesesuaian dengan Kebijakan Google API</h2>
        <div class="limited-use">
            <p>
                Penggunaan dan pemindahan informasi yang diterima dari Google API oleh Plugin
                Eventbooth mematuhi Google API Services User Data Policy, termasuk persyaratan
                Limited Use.
            </p>
            <p class="en" lang="en">
                (Plugin Eventbooth&rsquo;s use and transfer of information received from Google
                APIs adheres to the Google API Services User Data Policy, including the Limited
                Use requirements.)
            </p>
        </div>

        <h2>Penyimpanan &amp; Keamanan</h2>
        <ul>
            <li>Token akses Google disimpan <strong>terenkripsi</strong> di server; kata sandi akun disimpan sebagai <em>hash</em>.</li>
            <li>Akses ke data dibatasi berdasarkan peran pengguna (Super Admin, admin tenant, staf tenant).</li>
            <li>Masa simpan (retensi) album mengikuti pengaturan plan dan event yang bersangkutan.</li>
            <li>Foto dan data terkait dapat dihapus atas permintaan pemilik event melalui kontak di bawah.</li>
        </ul>

        <h2>Hak Pengguna</h2>
        <p>
            Pengguna dapat meminta akses, koreksi, atau penghapusan data pribadinya dengan
            menghubungi kami melalui kontak di halaman ini. Permintaan akan kami tindak lanjuti
            sesuai ketentuan yang berlaku.
        </p>

        <h2>Perubahan Kebijakan</h2>
        <p>
            Bila kebijakan ini berubah, versi terbaru akan diumumkan di halaman ini beserta
            tanggal pembaruannya.
        </p>

        <h2>Kontak</h2>
        <p>
            Pertanyaan atau permintaan terkait privasi dapat disampaikan melalui
            WhatsApp <strong>087762462482</strong> atau Instagram <strong>@photobooth.sb</strong>.
        </p>
    </div>
</main>

<footer class="site-foot">
    <div class="wrap">
        <div class="brandmark">
            <span class="logo-mark" aria-hidden="true">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#1A1405" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h2.6L8.5 5.5h7L17.4 8H20a1.5 1.5 0 0 1 1.5 1.5V18A1.5 1.5 0 0 1 20 19.5H4A1.5 1.5 0 0 1 2.5 18V9.5A1.5 1.5 0 0 1 4 8z"/><circle cx="12" cy="13.2" r="3.4"/></svg>
            </span>
            <span class="brandmark-text">Plugin <span class="accent">Eventbooth</span></span>
        </div>
        <p>&copy; <?= date('Y') ?> <?= e($appName) ?> · Bagian dari EventBooth · <a href="/privacy">Kebijakan Privasi</a> · <span title="Versi aplikasi">v<?= e($version) ?></span></p>
    </div>
</footer>

</body>
</html>
