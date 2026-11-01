/*
 * Plugin Eventbooth — sistem desain bersama (v0.3.0; disegarkan
 * v0.15.0: latar ambien emas, permukaan bergradasi dengan garis
 * cahaya atas, bayangan berlapis, pil status bertitik, aksen
 * emas yang lebih kaya — semua nama kelas tetap sama).
 *
 * Berkas ini berisi CSS MURNI (tanpa tag PHP) dan di-require di dalam
 * elemen <style> oleh partials/dashboard_top.php dan views/auth/login.php,
 * sehingga token & komponen di bawah dipakai bersama oleh layout
 * dashboard dan halaman masuk. Landing & halaman error memakai blok
 * gayanya sendiri dengan token yang selaras.
 */

/* ---------- Token ---------- */
:root {
    --bg: #0A0C0F;
    --panel: #12151B;
    --panel-2: #171B23;
    --elevated: #171B23;
    --line: rgba(255, 255, 255, .08);
    --line-strong: rgba(255, 255, 255, .14);
    --text: #EDEFF3;
    --muted: #9AA3AF;
    --muted-2: #6E7684;
    --gold: #C9A24B;
    --gold-2: #E7C878;
    --gold-grad: linear-gradient(135deg, #EBCB7E 0%, #C9A24B 52%, #A98534 100%);
    --gold-ink: #1A1405;
    --gold-soft: rgba(201, 162, 75, .12);
    --success: #34C77B;
    --danger: #E5605F;
    --danger-soft: #F09695;
    --info: #5EA8E8;
    --ok-bg: rgba(52, 199, 123, .10);
    --ok-line: rgba(52, 199, 123, .38);
    --err-bg: rgba(229, 96, 95, .10);
    --err-line: rgba(229, 96, 95, .40);
    --info-bg: rgba(94, 168, 232, .10);
    --info-line: rgba(94, 168, 232, .38);
    --r-sm: 8px;
    --r-md: 12px;
    --r-lg: 16px;
    --r-xl: 20px;
    --shadow: 0 10px 30px rgba(0, 0, 0, .35);
    --shadow-lg: 0 24px 60px rgba(0, 0, 0, .50);
    --font: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    --sidebar-w: 248px;
}

/* ---------- Dasar ---------- */
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font);
    /* Latar ambien (v0.15.0): cahaya emas sangat halus di dua
       sudut + kisi tipis, tetap di tempat saat halaman digulir —
       panel kini terasa melayang di atas kedalaman, bukan bidang
       datar. Halaman auth menimpanya dengan versi lebih kuat. */
    background:
        radial-gradient(880px 480px at 88% -10%, rgba(201, 162, 75, .075), transparent 62%),
        radial-gradient(720px 460px at -12% 108%, rgba(201, 162, 75, .05), transparent 60%),
        linear-gradient(rgba(255, 255, 255, .016) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .016) 1px, transparent 1px),
        var(--bg);
    background-size: auto, auto, 46px 46px, 46px 46px, auto;
    background-attachment: fixed;
    color: var(--text);
    line-height: 1.6;
    font-size: 15.5px;
    -webkit-font-smoothing: antialiased;
    overflow-x: clip;
}
img { max-width: 100%; height: auto; }
svg { max-width: 100%; }
::selection { background: rgba(201, 162, 75, .35); color: #fff; }
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-thumb { background: #262B36; border-radius: 8px; border: 2px solid var(--bg); }
::-webkit-scrollbar-thumb:hover { background: rgba(201, 162, 75, .45); }
::-webkit-scrollbar-track { background: transparent; }
a { color: var(--gold-2); text-decoration: none; transition: color 150ms ease; }
a:hover { color: var(--gold); text-decoration: underline; }
:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 4px; }
code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .9em; background: rgba(255, 255, 255, .06);
    padding: 1px 6px; border-radius: 6px;
}

/* ---------- Logo ---------- */
.logo-mark {
    flex: none;
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 11px;
    background: var(--gold-grad);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .35), 0 8px 20px rgba(201, 162, 75, .30);
}
.logo-mark.lg { width: 54px; height: 54px; border-radius: 15px; }

/* ---------- Tombol ---------- */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600;
    line-height: 1.3; color: var(--text);
    background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, 0)), var(--panel-2);
    border: 1px solid var(--line-strong);
    border-radius: var(--r-md); padding: 9px 18px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .05);
    transition: border-color 150ms ease, background 150ms ease, color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
}
.btn:hover { border-color: rgba(201, 162, 75, .55); color: var(--text); text-decoration: none; }
.btn:active { transform: translateY(1px); }
.btn-primary {
    background: var(--gold-grad); border-color: transparent; color: var(--gold-ink);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .35), 0 8px 22px rgba(201, 162, 75, .22);
}
.btn-primary:hover { color: var(--gold-ink); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .35), 0 12px 28px rgba(201, 162, 75, .34); filter: brightness(1.05); transform: translateY(-1px); }
.btn-ghost { background: transparent; border-color: var(--line); color: var(--muted); }
.btn-ghost:hover { color: var(--text); border-color: var(--line-strong); }
.btn-danger { background: transparent; border-color: var(--err-line); color: var(--danger-soft); }
.btn-danger:hover { background: var(--err-bg); border-color: var(--danger); color: var(--danger-soft); }
.btn-small { padding: 6px 13px; font-size: 13px; border-radius: var(--r-sm); }
.btn-block { width: 100%; }
.btn:disabled, button:disabled { opacity: .45; cursor: not-allowed; transform: none; }

/* ---------- Badge ---------- */
.role-badge {
    display: inline-flex; align-items: center;
    border: 1px solid rgba(201, 162, 75, .45); color: var(--gold-2);
    background: var(--gold-soft);
    border-radius: 999px; padding: 3px 12px; font-size: 11.5px; font-weight: 700;
    letter-spacing: .07em; text-transform: uppercase; white-space: nowrap;
}

/* ---------- Layout dashboard (sidebar) ---------- */
.layout { display: flex; min-height: 100vh; }
.sidebar {
    position: fixed; inset: 0 auto 0 0; z-index: 40;
    width: var(--sidebar-w);
    display: flex; flex-direction: column;
    padding: 22px 16px 18px;
    background: linear-gradient(180deg, #141821 0%, #10131A 58%, #0C0F14 100%);
    border-right: 1px solid var(--line);
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, .02);
}
.brandmark { display: flex; align-items: center; gap: 11px; padding: 2px 6px; }
.brandmark-text { font-size: 16.5px; font-weight: 800; letter-spacing: -.01em; line-height: 1.2; color: var(--text); }
.brandmark-text .accent {
    background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent;
}
.brandmark-sub { display: block; font-size: 11px; font-weight: 600; color: var(--muted-2); letter-spacing: .05em; text-transform: uppercase; }
.side-nav { display: flex; flex-direction: column; gap: 3px; margin-top: 28px; }
.side-heading {
    font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
    color: var(--muted-2); padding: 0 12px; margin-bottom: 8px;
}
.side-link {
    position: relative;
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    color: var(--muted); font-size: 14.5px; font-weight: 500;
    transition: background 150ms ease, color 150ms ease;
}
.side-link:hover { color: var(--text); background: rgba(255, 255, 255, .045); text-decoration: none; }
.side-link.active {
    color: var(--gold-2); font-weight: 600;
    background: linear-gradient(90deg, rgba(201, 162, 75, .17), rgba(201, 162, 75, .05) 72%, transparent);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
}
.side-link.active::before {
    content: ""; position: absolute; left: -16px; top: 9px; bottom: 9px;
    width: 3px; border-radius: 3px; background: var(--gold-grad);
}
.side-soon {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 10px 12px; border-radius: 10px;
    color: var(--muted-2); font-size: 14.5px; cursor: default;
}
.side-soon em {
    font-style: normal; font-size: 10px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; color: var(--muted-2);
    border: 1px solid var(--line); border-radius: 999px; padding: 1px 8px; white-space: nowrap;
}
.sidebar-user { margin-top: auto; border-top: 1px solid var(--line); padding-top: 16px; }
.user-row { display: flex; align-items: center; gap: 11px; padding: 0 4px; margin-bottom: 14px; min-width: 0; }
.avatar {
    flex: none; width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--gold-grad); color: var(--gold-ink);
    font-size: 15px; font-weight: 800;
}
.user-meta { min-width: 0; line-height: 1.35; }
.user-meta .name { display: block; font-size: 14px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-meta .email { display: block; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logout-form { display: block; }

.main { flex: 1; min-width: 0; margin-left: var(--sidebar-w); display: flex; flex-direction: column; }
.topbar {
    position: sticky; top: 0; z-index: 30;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 17px 32px;
    background: rgba(10, 12, 15, .78);
    -webkit-backdrop-filter: blur(16px) saturate(1.5); backdrop-filter: blur(16px) saturate(1.5);
    border-bottom: 1px solid var(--line);
}
.page-title { font-size: 21px; font-weight: 800; letter-spacing: -.02em; line-height: 1.25; }
.content { width: 100%; max-width: 1120px; margin: 0 auto; padding: 30px 32px 56px; flex: 1; }
.content h1 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin-bottom: 6px; }
.content .sub { color: var(--muted); font-size: 15px; margin-bottom: 24px; max-width: 720px; }
footer.dash {
    border-top: 1px solid var(--line); color: var(--muted-2);
    font-size: 13px; text-align: center; padding: 20px 16px 28px;
}

/* ---------- Flash & pemberitahuan ---------- */
.flash {
    border: 1px solid var(--line); border-left: 4px solid var(--muted);
    background: var(--panel-2); color: var(--text);
    border-radius: var(--r-md); padding: 12px 16px; margin-bottom: 14px; font-size: 14.5px;
    box-shadow: 0 8px 22px rgba(0, 0, 0, .25);
}
.flash.success { background: var(--ok-bg); border-color: var(--ok-line); border-left-color: var(--success); }
.flash.error { background: var(--err-bg); border-color: var(--err-line); border-left-color: var(--danger); }
.flash.info { background: var(--info-bg); border-color: var(--info-line); border-left-color: var(--info); }
.notice {
    border: 1px dashed var(--err-line); background: var(--err-bg);
    border-radius: var(--r-md); padding: 14px 18px; margin-bottom: 22px; font-size: 14.5px;
}

/* ---------- Statistik & kartu ---------- */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 26px; }
.stats-3 { grid-template-columns: repeat(3, 1fr); }
.stat {
    background: linear-gradient(180deg, rgba(255, 255, 255, .03), rgba(255, 255, 255, 0) 46%), var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--r-lg); padding: 20px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), 0 10px 26px rgba(0, 0, 0, .28);
    transition: border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}
.stat:hover { border-color: rgba(201, 162, 75, .35); transform: translateY(-2px); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), 0 18px 36px rgba(0, 0, 0, .38); }
.stat .icon {
    width: 38px; height: 38px; border-radius: 11px; margin-bottom: 14px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(160deg, rgba(201, 162, 75, .22), rgba(201, 162, 75, .08));
    border: 1px solid rgba(201, 162, 75, .22);
    color: var(--gold-2);
}
.stat .num { font-size: 30px; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; color: var(--text); }
.stat .lbl { color: var(--muted); font-size: 13.5px; margin-top: 3px; }

.cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.card {
    background: linear-gradient(180deg, rgba(255, 255, 255, .03), rgba(255, 255, 255, 0) 46%), var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--r-lg); padding: 22px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), 0 10px 26px rgba(0, 0, 0, .28);
    transition: border-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}
.card:hover { border-color: rgba(201, 162, 75, .35); transform: translateY(-2px); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), 0 18px 36px rgba(0, 0, 0, .38); }
.card h2 { font-size: 16.5px; font-weight: 700; letter-spacing: -.01em; margin-bottom: 8px; }
.card p { color: var(--muted); font-size: 14px; }
.card.soon { opacity: .62; }
.card.soon:hover { transform: none; }
.soon-tag {
    display: inline-block; font-size: 10.5px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: var(--gold-2);
    background: var(--gold-soft);
    border: 1px solid rgba(201, 162, 75, .4); border-radius: 999px; padding: 2px 10px;
    margin-bottom: 12px;
}
.panel-box {
    background: linear-gradient(180deg, rgba(255, 255, 255, .028), rgba(255, 255, 255, 0) 42%), var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--r-lg); padding: 26px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), var(--shadow);
}
.form-panel { max-width: 860px; }

/* ---------- Tabel ---------- */
.table-wrap {
    background: linear-gradient(180deg, rgba(255, 255, 255, .028), rgba(255, 255, 255, 0) 42%), var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--r-lg); overflow-x: auto;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04), var(--shadow);
    -webkit-overflow-scrolling: touch;
}
table.list { width: 100%; border-collapse: collapse; min-width: 880px; }
table.list th, table.list td {
    padding: 13px 16px; text-align: left; font-size: 14px;
    border-bottom: 1px solid var(--line); vertical-align: top;
}
table.list th {
    font-size: 11.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--muted); background: rgba(255, 255, 255, .035); white-space: nowrap;
}
table.list tbody tr { transition: background 150ms ease; }
table.list tbody tr:hover { background: rgba(201, 162, 75, .045); }
table.list tr:last-child td { border-bottom: none; }
.status-on, .status-off {
    display: inline-flex; align-items: center; gap: 6px;
    border-radius: 999px; padding: 3px 11px; font-size: 12px; font-weight: 700; white-space: nowrap;
}
.status-on::before, .status-off::before, .status-warn::before {
    content: ""; flex: none; width: 6px; height: 6px; border-radius: 50%;
    background: currentColor;
}
.status-on { color: var(--success); background: var(--ok-bg); border: 1px solid var(--ok-line); }
.status-off { color: var(--danger-soft); background: var(--err-bg); border: 1px solid var(--err-line); }
.status-warn {
    display: inline-flex; align-items: center; gap: 6px;
    border-radius: 999px; padding: 3px 11px; font-size: 12px; font-weight: 700; white-space: nowrap;
    color: var(--gold-2); background: var(--gold-soft); border: 1px solid rgba(201, 162, 75, .4);
}
.row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.row-actions form { display: inline; }

/* ---------- Kunci lisensi teks penuh ---------- */
.key-full {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    letter-spacing: .04em; user-select: all; word-break: break-all;
}

/* ---------- Bilah kuota (Google Drive) ---------- */
.quota-bar {
    width: 170px; max-width: 100%; height: 8px;
    background: rgba(255, 255, 255, .08);
    border-radius: 999px; overflow: hidden; margin-bottom: 5px;
}
.quota-fill { height: 100%; background: var(--gold-grad); border-radius: 999px; }
.quota-text { font-size: 12.5px; color: var(--muted); white-space: nowrap; }

/* ---------- Formulir ---------- */
form .field { margin-bottom: 18px; }
form label { display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 7px; color: var(--text); }
form .hint, .hint { font-size: 12.5px; color: var(--muted); font-weight: 400; }
div.hint { margin-top: 6px; }
form input[type="text"], form input[type="email"], form input[type="password"],
form input[type="number"], form textarea, form select {
    width: 100%; padding: 11px 13px; font-size: 15px; font-family: inherit;
    background: rgba(5, 6, 8, .42); color: var(--text);
    border: 1px solid var(--line-strong); border-radius: var(--r-md);
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
form textarea { min-height: 76px; resize: vertical; }
form input::placeholder, form textarea::placeholder { color: var(--muted-2); }
form input:focus, form textarea:focus, form select:focus {
    outline: none; border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 162, 75, .18);
}
form input:disabled, form textarea:disabled { opacity: .5; cursor: not-allowed; }
.field-error { color: var(--danger-soft); font-size: 13px; margin-top: 6px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
.check { display: flex; align-items: center; gap: 10px; font-size: 14.5px; font-weight: 500; margin-bottom: 11px; cursor: pointer; }
.check input { width: 17px; height: 17px; accent-color: var(--gold); flex: none; }
fieldset { border: 1px solid var(--line); border-radius: var(--r-md); padding: 16px 18px 8px; margin-bottom: 18px; }
legend { font-size: 13.5px; font-weight: 700; padding: 0 7px; }
.form-actions { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
dl.profile { display: grid; grid-template-columns: 170px 1fr; gap: 9px 18px; font-size: 15px; }
dl.profile dt { color: var(--muted); }
dl.profile dd { color: var(--text); }

/* ---------- Layout masuk (auth) ---------- */
body.auth {
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 40px 16px;
    background:
        radial-gradient(640px 420px at 50% -6%, rgba(201, 162, 75, .17), transparent 62%),
        linear-gradient(rgba(255, 255, 255, .028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .028) 1px, transparent 1px),
        var(--bg);
    background-size: auto, 46px 46px, 46px 46px, auto;
}
.auth-shell { width: min(420px, 100%); max-width: 420px; }
.auth-logo { display: flex; flex-direction: column; align-items: center; gap: 13px; margin-bottom: 24px; text-align: center; }
.auth-logo .brandmark-text { font-size: 19px; }
.auth-card {
    background: linear-gradient(180deg, rgba(255, 255, 255, .035), rgba(255, 255, 255, 0) 42%), var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--r-xl); padding: 32px 30px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .05), 0 0 0 1px rgba(201, 162, 75, .06), var(--shadow-lg);
}
.auth-card .card-title { font-size: 19px; font-weight: 800; letter-spacing: -.01em; text-align: center; }
.auth-card .sub { text-align: center; color: var(--muted); font-size: 14px; margin: 5px 0 24px; }
.auth-foot { text-align: center; margin-top: 20px; font-size: 13.5px; color: var(--muted); }
.auth-foot a { color: var(--muted); }
.auth-foot a:hover { color: var(--gold-2); }
.auth-version { text-align: center; margin-top: 10px; font-size: 12px; color: var(--muted-2); }

/* ---------- Responsif ---------- */
@media (max-width: 900px) {
    .layout { flex-direction: column; }
    .sidebar {
        position: static; width: 100%;
        padding: 16px 16px 14px; border-right: none; border-bottom: 1px solid var(--line);
    }
    .side-nav { flex-direction: row; overflow-x: auto; margin-top: 16px; padding-bottom: 4px; scrollbar-width: none; }
    .side-nav::-webkit-scrollbar { display: none; }
    .side-heading { display: none; }
    .side-link, .side-soon { white-space: nowrap; }
    .side-link.active::before { left: 12px; right: 12px; top: auto; bottom: -1px; width: auto; height: 3px; }
    .sidebar-user {
        margin-top: 14px; padding-top: 14px;
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
    }
    .user-row { margin-bottom: 0; }
    .logout-form { flex: none; }
    .main { margin-left: 0; }
    .topbar { padding: 14px 20px; gap: 12px; }
    .page-title {
        font-size: 18.5px; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .content { padding: 24px 20px 48px; }
    .stats, .stats-3 { grid-template-columns: repeat(2, 1fr); }
    .cards { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .panel-box { padding: 20px; }
    dl.profile { grid-template-columns: 1fr; gap: 2px 0; }
    dl.profile dd { margin-bottom: 9px; }
}
@media (max-width: 520px) {
    .stats, .stats-3 { grid-template-columns: 1fr; }
    .stat .num { font-size: 26px; }
    .topbar { padding: 12px 16px; }
    .content { padding: 20px 16px 40px; }
    .content h1 { font-size: 20px; }
    body.auth { padding: 28px 12px; }
    .auth-card { padding: 26px 22px; }
    .form-actions .btn { flex: 1 1 auto; }
    .flash { padding: 11px 13px; }
}

/* ---------- Gerakan dikurangi (aksesibilitas) ---------- */
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    *, *::before, *::after { transition: none !important; }
    .btn-primary:hover, .card:hover, .stat:hover { transform: none; }
}
