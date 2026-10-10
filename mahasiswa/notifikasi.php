<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/notifikasi.php";

require_role('mahasiswa');

$user_id = (int) $_SESSION['user']['id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================================================
   1. Tandai semua dibaca (dari halaman ini atau dropdown topbar)
   ========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'baca_semua') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (is_string($csrf) && hash_equals($_SESSION['csrf_token'], $csrf)) {
        $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE user_id = :id AND dibaca = 0")
            ->execute([':id' => $user_id]);
    }

    /* Kembali ke halaman asal jika masih di aplikasi ini */
    $kembali = (string) ($_POST['kembali'] ?? '');
    $path_app = (string) parse_url(BASE_URL, PHP_URL_PATH);

    $aman = $kembali !== ''
        && $kembali[0] === '/'
        && strpos($kembali, '//') !== 0
        && strpos($kembali, '\\') === false
        && ($path_app === '' || strpos($kembali, $path_app . '/') === 0);

    header("Location: " . ($aman ? $kembali : BASE_URL . "/mahasiswa/notifikasi.php"));
    exit;
}

/* ==========================================================
   2. Ambil notifikasi
   ========================================================== */

$tab = (string) ($_GET['tab'] ?? 'semua');
$daftar_tab = [
    'semua' => 'Semua',
    'belum' => 'Belum dibaca',
    'peminjaman' => 'Peminjaman',
    'saran' => 'Saran Alat',
    'pengingat' => 'Pengingat'
];

if (!isset($daftar_tab[$tab])) {
    $tab = 'semua';
}

$stmt = $pdo->prepare("
    SELECT id, pesan, link, dibaca, created_at
    FROM notifikasi
    WHERE user_id = :id
    ORDER BY created_at DESC, id DESC
    LIMIT 100
");
$stmt->execute([':id' => $user_id]);
$semua = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jumlah = array_fill_keys(array_keys($daftar_tab), 0);
$tampil = [];

foreach ($semua as $n) {
    $n['info'] = info_notifikasi($n);
    $belum = (int) $n['dibaca'] === 0;

    $jumlah['semua']++;
    $jumlah[$n['info']['jenis']]++;

    if ($belum) {
        $jumlah['belum']++;
    }

    $cocok = $tab === 'semua'
        || ($tab === 'belum' && $belum)
        || $n['info']['jenis'] === $tab;

    if ($cocok) {
        $tampil[grup_notifikasi($n['created_at'])][] = $n;
    }
}

/* Pengajuan yang masih berjalan, untuk kartu ringkasan */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM peminjaman
    WHERE peminjam_id = :id
      AND status IN ('diajukan', 'disetujui_dosen', 'siap_diambil', 'dipinjam')
");
$stmt->execute([':id' => $user_id]);
$pengajuan_aktif = (int) $stmt->fetchColumn();

$page_title = 'Notifikasi';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content notif-page">

        <div class="notif-header">
            <div>
                <nav class="riwayat-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php">Dashboard</a>
                    <i data-lucide="chevron-right"></i>
                    <span>Notifikasi</span>
                </nav>
                <h1>Notifikasi</h1>
                <p>Kabar terbaru soal pengajuan, saran alat, dan pengingat pengembalian.</p>
            </div>

            <?php if ($jumlah['belum'] > 0): ?>
                <form method="POST" action="<?= BASE_URL ?>/mahasiswa/notifikasi.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi" value="baca_semua">
                    <button type="submit" class="btn notif-btn-outline">
                        <i data-lucide="check-check"></i>
                        Tandai semua dibaca
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <nav class="notif-tabs" aria-label="Filter notifikasi">
            <?php foreach ($daftar_tab as $kunci => $label): ?>
                <a
                    href="<?= BASE_URL ?>/mahasiswa/notifikasi.php<?= $kunci === 'semua' ? '' : '?tab=' . $kunci ?>"
                    class="notif-tab <?= $tab === $kunci ? 'is-active' : '' ?>"
                    <?= $tab === $kunci ? 'aria-current="page"' : '' ?>
                >
                    <?= e($label) ?>
                    <?php if ($jumlah[$kunci] > 0 && in_array($kunci, ['semua', 'belum'], true)): ?>
                        <span><?= $jumlah[$kunci] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="notif-layout">

            <section class="notif-list-card">
                <?php if (!$tampil): ?>
                    <div class="notif-empty">
                        <i data-lucide="bell-off"></i>
                        <strong><?= $tab === 'belum' ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi' ?></strong>
                        <p>Kabar tentang pengajuan dan saran alatmu akan muncul di sini.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($tampil as $grup => $items): ?>
                    <div class="notif-group"><?= e($grup) ?></div>

                    <?php foreach ($items as $n):
                        $info = $n['info'];
                        $belum = (int) $n['dibaca'] === 0;
                        $label_jenis = ['peminjaman' => 'Peminjaman', 'saran' => 'Saran Alat', 'pengingat' => 'Pengingat'][$info['jenis']];
                    ?>
                        <article class="notif-item <?= $belum ? 'is-unread' : '' ?>">
                            <span class="notif-icon is-<?= e($info['warna']) ?>">
                                <i data-lucide="<?= e($info['ikon']) ?>"></i>
                            </span>

                            <div class="notif-item-body">
                                <div class="notif-item-title">
                                    <strong><?= e($info['judul']) ?></strong>
                                    <span class="notif-chip"><?= e($label_jenis) ?></span>
                                </div>

                                <p><?= e($n['pesan']) ?></p>

                                <div class="notif-item-meta">
                                    <span><?= e(waktu_notifikasi($n['created_at'])) ?></span>
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?= e(url_buka_notifikasi((int) $n['id'])) ?>">Lihat detail</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($belum): ?>
                                <span class="notif-unread-dot" aria-label="Belum dibaca"></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </section>

            <aside class="notif-side">
                <section class="notif-summary">
                    <h2>Ringkasan</h2>
                    <div class="notif-summary-grid">
                        <div class="is-primary">
                            <strong><?= $jumlah['belum'] ?></strong>
                            <span>Belum dibaca</span>
                        </div>
                        <div>
                            <strong><?= $pengajuan_aktif ?></strong>
                            <span>Pengajuan aktif</span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="notif-summary-link">
                        Lihat riwayat peminjaman
                        <i data-lucide="chevron-right"></i>
                    </a>
                </section>
            </aside>
        </div>

    </main>
</div>

<?php require_once "../includes/footer.php"; ?>
