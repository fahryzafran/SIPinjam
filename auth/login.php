<?php

require_once "../config/database.php";
require_once "../config/app.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Halaman tujuan setelah login, sesuai role */
function tujuan_login(string $role): ?string
{
    $peta = [
        'mahasiswa' => '/mahasiswa/dashboard.php',
        'dosen' => '/dosen/dashboard.php',
        'adminlab' => '/adminlab/dashboard.php',
        'superadmin' => '/superadmin/dashboard.php'
    ];

    return isset($peta[$role]) ? BASE_URL . $peta[$role] : null;
}

if (isset($_SESSION['user'])) {
    header("Location: " . (tujuan_login((string) $_SESSION['user']['role']) ?? BASE_URL));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$error_field = "";
$nim_nip = "";

/* Permintaan dari login.js (fetch) dijawab JSON, tanpa JS tetap redirect biasa */
$is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim_nip = trim((string) ($_POST['nim_nip'] ?? ""));
    $password = (string) ($_POST['password'] ?? "");
    $csrf = $_POST['csrf_token'] ?? '';

    /* Batasi percobaan: 5 kali gagal, tunggu 60 detik */
    $kunci_sampai = (int) ($_SESSION['login_kunci_sampai'] ?? 0);

    if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = "Sesi halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.";
    } elseif ($kunci_sampai > time()) {
        $error = "Terlalu banyak percobaan. Coba lagi dalam " . ($kunci_sampai - time()) . " detik.";
    } elseif ($nim_nip === "" && $password === "") {
        $error = "Isi NIM/NIP dan kata sandi terlebih dahulu.";
        $error_field = "semua";
    } elseif ($nim_nip === "") {
        $error = "NIM/NIP belum diisi.";
        $error_field = "nim_nip";
    } elseif ($password === "") {
        $error = "Kata sandi belum diisi.";
        $error_field = "password";
    } elseif (strpos($nim_nip, '@') !== false) {
        $error = "Login hanya bisa memakai NIM atau NIP, bukan email.";
        $error_field = "nim_nip";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, nama, email, password_hash, role, nim_nip
            FROM users
            WHERE nim_nip = :nim_nip
            LIMIT 1
        ");
        $stmt->execute([':nim_nip' => $nim_nip]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $tujuan = $user ? tujuan_login((string) $user['role']) : null;

        if ($user && password_verify($password, $user['password_hash']) && $tujuan !== null) {
            session_regenerate_id(true);

            unset($_SESSION['login_gagal'], $_SESSION['login_kunci_sampai']);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
                'nim_nip' => $user['nim_nip']
            ];

            if ($is_ajax) {
                $kata = preg_split('/\s+/', trim((string) $user['nama'])) ?: [''];
                $inisial = mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
                $label_role = [
                    'mahasiswa' => 'Mahasiswa',
                    'dosen' => 'Dosen',
                    'adminlab' => 'Admin Lab',
                    'superadmin' => 'Super Admin'
                ];

                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'redirect' => $tujuan,
                    'nama' => $user['nama'],
                    'inisial' => $inisial,
                    'role' => $label_role[$user['role']] ?? ''
                ]);
                exit;
            }

            header("Location: " . $tujuan);
            exit;
        }

        $_SESSION['login_gagal'] = (int) ($_SESSION['login_gagal'] ?? 0) + 1;

        if ($_SESSION['login_gagal'] >= 5) {
            $_SESSION['login_gagal'] = 0;
            $_SESSION['login_kunci_sampai'] = time() + 60;
        }

        $error = "NIM/NIP atau kata sandi salah. Coba lagi.";
        $error_field = "password";
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error, 'field' => $error_field]);
        exit;
    }
}

function e_login(string $teks): string
{
    return htmlspecialchars($teks, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?= e_login(APP_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
</head>
<body class="login-body">

<div class="login-page">

    <!-- ===================== PANEL KIRI ===================== -->
    <section class="login-hero" id="loginHero">
        <div class="login-hero-bg" aria-hidden="true"></div>

        <div class="login-brand">
            <div class="login-brand-logo">S</div>
            <div>
                <div class="login-brand-name">SIPAKA</div>
                <div class="login-brand-sub">Sistem Peminjaman Alat Kampus</div>
            </div>
        </div>

        <div class="login-hero-text">
            <h1>Pinjam alat kampus tanpa antre di lab.</h1>
            <p class="login-ketik"><span id="loginKetik"></span><span class="login-kursor"></span></p>
        </div>

        <!-- Adegan proyektor -->
        <div class="login-scene-wrap" aria-hidden="true">
            <div class="login-scene" id="loginScene">

                <div class="ls-layer ls-far">
                    <div class="ls-rod"></div>
                    <div class="ls-screen">
                        <div class="ls-screen-inner">
                            <div class="ls-screen-on">

                                <div class="ls-slide ls-slide-chart" data-slide="0">
                                    <div class="ls-line ls-line-title"></div>
                                    <div class="ls-line ls-line-sub"></div>
                                    <div class="ls-bars">
                                        <span style="height: 50%"></span>
                                        <span style="height: 78%"></span>
                                        <span style="height: 42%"></span>
                                        <span style="height: 96%"></span>
                                        <span style="height: 66%"></span>
                                    </div>
                                </div>

                                <div class="ls-slide ls-slide-code" data-slide="1">
                                    <div class="ls-code-dots"><i></i><i></i><i></i></div>
                                    <div class="ls-code"><b class="w44 c1"></b><b class="w120 c3"></b></div>
                                    <div class="ls-code in1"><b class="w70 c2"></b><b class="w150 c4"></b></div>
                                    <div class="ls-code in1"><b class="w96 c4"></b><b class="w60 c1"></b></div>
                                    <div class="ls-code in2"><b class="w130 c2"></b></div>
                                    <div class="ls-code in1"><b class="w50 c3"></b><b class="w110 c4"></b></div>
                                    <div class="ls-code"><b class="w30 c1"></b><b class="ls-code-cursor"></b></div>
                                </div>

                                <div class="ls-slide ls-slide-done" data-slide="2">
                                    <span class="ls-done-icon">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </span>
                                    <span class="ls-done-text">Kelas siap dimulai</span>
                                </div>

                                <div class="ls-dots"><span></span><span></span><span></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ls-layer ls-mid">
                    <div class="ls-beam"></div>
                    <span class="ls-dust" style="left: 236px; top: 296px; animation-delay: .2s"></span>
                    <span class="ls-dust" style="left: 262px; top: 300px; animation-delay: 1.1s; animation-duration: 4s"></span>
                    <span class="ls-dust" style="left: 214px; top: 282px; animation-delay: 2s; animation-duration: 3.8s"></span>
                    <span class="ls-dust" style="left: 288px; top: 286px; animation-delay: .7s; animation-duration: 3.2s"></span>
                    <span class="ls-dust" style="left: 250px; top: 270px; animation-delay: 1.6s; animation-duration: 4.4s"></span>
                    <span class="ls-dust" style="left: 310px; top: 268px; animation-delay: 2.6s; animation-duration: 3.6s"></span>
                </div>

                <div class="ls-layer ls-near">
                    <svg class="ls-cable" width="520" height="400" viewBox="0 0 520 400">
                        <path d="M404 368 C 384 396, 346 394, 326 352" class="ls-cable-base"/>
                        <path d="M404 368 C 384 396, 346 394, 326 352" class="ls-cable-flow"/>
                    </svg>
                    <div class="ls-lens"><span></span></div>
                    <div class="ls-projector">
                        <span class="ls-led"></span>
                        <span class="ls-vent" style="top: 16px"></span>
                        <span class="ls-vent" style="top: 24px"></span>
                        <span class="ls-vent" style="top: 32px"></span>
                        <span class="ls-vent" style="top: 40px"></span>
                        <span class="ls-projector-label">SIPAKA</span>
                    </div>
                    <span class="ls-leg" style="left: 210px"></span>
                    <span class="ls-leg" style="left: 296px"></span>
                    <div class="ls-laptop"><div class="ls-laptop-screen"></div></div>
                    <div class="ls-laptop-base"></div>
                </div>

            </div>
        </div>

        <div class="login-hero-foot">Laboratorium Kampus · SIPAKA</div>
    </section>

    <!-- ===================== PANEL KANAN ===================== -->
    <section class="login-side">
        <div class="login-box" id="loginBox">
            <h2>Selamat datang kembali</h2>
            <p class="login-lead">Masuk dengan NIM (mahasiswa) atau NIP (dosen &amp; staf).</p>

            <div class="login-alert" id="loginAlert" role="alert" <?= $error === "" ? 'hidden' : '' ?>>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                <span id="loginAlertText"><?= e_login($error) ?></span>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/auth/login.php" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e_login($_SESSION['csrf_token']) ?>">

                <div class="login-field">
                    <label for="nim_nip">NIM / NIP</label>
                    <div class="login-input">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0M15 10h3M15 14h3"/></svg>
                        <input
                            type="text"
                            id="nim_nip"
                            name="nim_nip"
                            value="<?= e_login($nim_nip) ?>"
                            placeholder="Masukkan NIM atau NIP"
                            autocomplete="username"
                            class="<?= in_array($error_field, ['nim_nip', 'semua'], true) ? 'is-error' : '' ?>"
                            <?= $nim_nip === "" ? 'autofocus' : '' ?>
                        >
                    </div>
                </div>

                <div class="login-field">
                    <div class="login-label-row">
                        <label for="password">Kata sandi</label>
                        <span class="login-hint">Lupa? Hubungi admin lab</span>
                    </div>
                    <div class="login-input">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan kata sandi"
                            autocomplete="current-password"
                            class="has-toggle <?= in_array($error_field, ['password', 'semua'], true) ? 'is-error' : '' ?>"
                            <?= $nim_nip !== "" ? 'autofocus' : '' ?>
                        >
                        <button type="button" class="login-eye" id="loginEye" aria-label="Tampilkan kata sandi">
                            <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.6 5.1A9.8 9.8 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-3 3.6M6.6 6.6A17 17 0 0 0 2 12s4 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    <div class="login-caps" id="loginCaps" hidden>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 4-8 8h5v6h6v-6h5z"/></svg>
                        Caps Lock sedang aktif
                    </div>
                </div>

                <button type="submit" class="login-submit" id="loginSubmit">
                    <span class="ls-state ls-idle">
                        Masuk
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </span>
                    <span class="ls-state ls-loading"><span class="login-spinner"></span>Memeriksa akun…</span>
                    <span class="ls-state ls-done">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        Berhasil masuk
                    </span>
                </button>
            </form>

            <div class="login-divider"><span></span>Belum punya akun?<span></span></div>
            <p class="login-note">Akun dibuat oleh admin lab. Hubungi admin lab untuk mendapatkan akun.</p>
        </div>
    </section>
</div>

<!-- Transisi setelah login berhasil -->
<div class="login-transisi" id="loginTransisi" aria-live="polite" hidden>
    <div class="lt-content">
        <div class="lt-avatar" id="ltAvatar"></div>
        <div class="lt-hello">Selamat datang,</div>
        <div class="lt-name" id="ltNama"></div>
        <div class="lt-sub" id="ltSub">Menyiapkan dashboard…</div>
        <div class="lt-bar"><span></span></div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/login.js"></script>
</body>
</html>
