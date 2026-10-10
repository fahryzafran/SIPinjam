<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/akun.php";
require_once "../includes/peminjaman_status.php";

require_role('mahasiswa');

$user_id = (int) $_SESSION['user']['id'];
$akun = data_akun($pdo, $user_id);

/* ==========================================================
   1. Ringkasan peminjaman
   ========================================================== */

$stmt = $pdo->prepare("
    SELECT status, COUNT(*)
    FROM peminjaman
    WHERE peminjam_id = :id
    GROUP BY status
");
$stmt->execute([':id' => $user_id]);
$per_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total = array_sum($per_status);
$berjalan = (int) ($per_status['diajukan'] ?? 0)
    + (int) ($per_status['disetujui_dosen'] ?? 0)
    + (int) ($per_status['siap_diambil'] ?? 0)
    + (int) ($per_status['dipinjam'] ?? 0);
$dikembalikan = (int) ($per_status['dikembalikan'] ?? 0);
$ditolak = (int) ($per_status['ditolak'] ?? 0);

/* ==========================================================
   2. Kuota kelas hari ini (per kategori yang punya batas)
   ========================================================== */

$kuota = [];

if ($akun['kelas_id'] > 0) {
    $stmt = $pdo->prepare("
        SELECT
            k.id,
            k.nama_kategori,
            k.max_per_kelas,
            (
                SELECT COUNT(*)
                FROM peminjaman p
                INNER JOIN peminjaman_item pi ON pi.peminjaman_id = p.id
                INNER JOIN alat a ON a.id = pi.alat_id
                WHERE p.kelas_id = :kelas_id
                  AND a.kategori_id = k.id
                  AND p.tgl = :hari_ini
                  AND p.status NOT IN ('ditolak', 'dikembalikan')
            ) AS terpakai
        FROM kategori_alat k
        WHERE k.max_per_kelas > 0
        ORDER BY k.nama_kategori
    ");
    $stmt->execute([':kelas_id' => $akun['kelas_id'], ':hari_ini' => date('Y-m-d')]);
    $kuota = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ==========================================================
   3. Aktivitas terakhir (peminjaman + saran alat)
   ========================================================== */

$label_pinjam = [
    'diajukan' => ['Mengajukan peminjaman', 'netral'],
    'disetujui_dosen' => ['Pengajuan disetujui dosen', 'primary'],
    'siap_diambil' => ['Alat siap diambil', 'hijau'],
    'dipinjam' => ['Sedang meminjam alat', 'primary'],
    'dikembalikan' => ['Mengembalikan alat', 'hijau'],
    'ditolak' => ['Pengajuan ditolak', 'merah']
];

$aktivitas = [];

$stmt = $pdo->prepare("
    SELECT p.id, p.status, p.ruangan, p.waktu_diajukan,
           COALESCE(p.waktu_dikembalikan, p.waktu_diambil, p.waktu_disiapkan,
                    p.waktu_disetujui_dosen, p.waktu_diajukan) AS waktu,
           (
               SELECT a.nama
               FROM peminjaman_item pi
               INNER JOIN alat a ON a.id = pi.alat_id
               WHERE pi.peminjaman_id = p.id
               LIMIT 1
           ) AS nama_alat
    FROM peminjaman p
    WHERE p.peminjam_id = :id
    ORDER BY waktu DESC
    LIMIT 5
");
$stmt->execute([':id' => $user_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    [$judul, $warna] = $label_pinjam[$p['status']] ?? ['Peminjaman', 'netral'];

    $aktivitas[] = [
        'judul' => $judul . ($p['nama_alat'] ? ': ' . $p['nama_alat'] : ''),
        'sub' => kode_pengajuan((int) $p['id'], $p['waktu_diajukan']) . ' · ' . $p['ruangan'],
        'waktu' => $p['waktu'],
        'warna' => $warna,
        'link' => BASE_URL . '/mahasiswa/detail_peminjaman.php?id=' . (int) $p['id']
    ];
}

try {
    $stmt = $pdo->prepare("
        SELECT s.jenis, s.status, s.jumlah, s.created_at, s.nama_alat_baru, a.nama AS nama_alat
        FROM saran_alat s
        LEFT JOIN alat a ON a.id = s.alat_id
        WHERE s.user_id = :id
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $user_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $aktivitas[] = [
            'judul' => 'Mengirim saran alat: ' . ($s['jenis'] === 'baru' ? $s['nama_alat_baru'] : $s['nama_alat']),
            'sub' => ($s['jenis'] === 'baru' ? 'Alat baru' : 'Tambah stok') . ' · ' . (int) $s['jumlah'] . ' unit',
            'waktu' => $s['created_at'],
            'warna' => $s['status'] === 'ditolak' ? 'merah' : ($s['status'] === 'diterima' ? 'hijau' : 'primary'),
            'link' => BASE_URL . '/mahasiswa/saran.php'
        ];
    }
} catch (PDOException $e) {
    /* Kolom saran_alat belum ditambahkan: lewati aktivitas saran */
}

usort($aktivitas, static fn ($a, $b) => strcmp((string) $b['waktu'], (string) $a['waktu']));
$aktivitas = array_slice($aktivitas, 0, 5);

$page_title = 'Profil Saya';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content profil-page">

        <div>
            <nav class="riwayat-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php">Dashboard</a>
                <i data-lucide="chevron-right"></i>
                <span>Profil Saya</span>
            </nav>
            <h1 class="profil-title">Profil Saya</h1>
        </div>

        <!-- Kartu identitas -->
        <section class="profil-hero">
            <div class="profil-hero-main">
                <?php if ($akun['foto_url'] !== ''): ?>
                    <img src="<?= e($akun['foto_url']) ?>" alt="Foto profil <?= e($akun['nama']) ?>" class="profil-avatar">
                <?php else: ?>
                    <div class="profil-avatar"><?= e($akun['inisial']) ?></div>
                <?php endif; ?>

                <div>
                    <h2><?= e($akun['nama']) ?></h2>
                    <div class="profil-chips">
                        <span class="profil-chip is-primary"><?= e($akun['role_label']) ?></span>
                        <?php if ($akun['kelas'] !== ''): ?>
                            <span class="profil-chip"><?= e($akun['kelas']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($akun['nim_nip'])): ?>
                            <span class="profil-nim">NIM <?= e($akun['nim_nip']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/mahasiswa/pengaturan.php" class="btn btn-primary profil-btn-edit">
                <i data-lucide="pencil"></i>
                Edit profil
            </a>
        </section>

        <div class="profil-layout">

            <div class="profil-col">
                <!-- Data diri -->
                <section class="profil-card">
                    <div class="profil-card-head">
                        <span class="profil-card-icon"><i data-lucide="graduation-cap"></i></span>
                        <div>
                            <h3>Data Diri</h3>
                            <p>NIM, kelas, dan email terhubung dengan data kampus.</p>
                        </div>
                    </div>

                    <dl class="profil-data">
                        <div><dt>Nama lengkap</dt><dd><?= e($akun['nama']) ?></dd></div>
                        <div><dt>NIM</dt><dd><?= e($akun['nim_nip'] ?: '-') ?></dd></div>
                        <div><dt>Kelas</dt><dd><?= e($akun['kelas'] ?: 'Belum terdaftar') ?></dd></div>
                        <div><dt>Email</dt><dd><?= e($akun['email'] ?: '-') ?></dd></div>
                    </dl>
                </section>

                <!-- Aktivitas -->
                <section class="profil-card">
                    <div class="profil-card-head profil-card-head-split">
                        <div class="profil-card-head-left">
                            <span class="profil-card-icon"><i data-lucide="activity"></i></span>
                            <h3>Aktivitas Terakhir</h3>
                        </div>
                        <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="profil-link">Lihat riwayat</a>
                    </div>

                    <?php if ($aktivitas): ?>
                        <ol class="profil-timeline">
                            <?php foreach ($aktivitas as $a): ?>
                                <li>
                                    <span class="profil-dot is-<?= e($a['warna']) ?>"></span>
                                    <div>
                                        <a href="<?= e($a['link']) ?>"><?= e($a['judul']) ?></a>
                                        <span><?= e($a['sub']) ?> · <?= e(format_waktu_singkat($a['waktu'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="profil-empty">Belum ada aktivitas. Mulai dengan meminjam alat dari katalog.</p>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="profil-col">
                <!-- Ringkasan -->
                <section class="profil-card">
                    <h3 class="profil-card-title">Ringkasan Peminjaman</h3>
                    <div class="profil-stats">
                        <div><strong><?= $total ?></strong><span>Total pengajuan</span></div>
                        <div class="is-primary"><strong><?= $berjalan ?></strong><span>Sedang berjalan</span></div>
                        <div class="is-hijau"><strong><?= $dikembalikan ?></strong><span>Dikembalikan</span></div>
                        <div class="is-merah"><strong><?= $ditolak ?></strong><span>Ditolak</span></div>
                    </div>
                </section>

                <!-- Kuota kelas -->
                <?php if ($kuota): ?>
                    <section class="profil-card">
                        <h3 class="profil-card-title">Kuota Kelas Hari Ini</h3>
                        <p class="profil-card-sub">
                            Batas peminjaman <?= e($akun['kelas']) ?> per kategori pada slot waktu yang sama.
                        </p>

                        <div class="profil-kuota">
                            <?php foreach ($kuota as $k):
                                $maks = max(1, (int) $k['max_per_kelas']);
                                $pakai = min((int) $k['terpakai'], $maks);
                                $persen = (int) round($pakai / $maks * 100);
                            ?>
                                <div>
                                    <div class="profil-kuota-row">
                                        <span><?= e($k['nama_kategori']) ?></span>
                                        <strong class="<?= $pakai >= $maks ? 'is-penuh' : '' ?>">
                                            <?= (int) $k['terpakai'] ?> / <?= (int) $k['max_per_kelas'] ?> dipakai
                                        </strong>
                                    </div>
                                    <div class="profil-bar" role="progressbar" aria-valuenow="<?= $pakai ?>" aria-valuemin="0" aria-valuemax="<?= $maks ?>">
                                        <span style="width: <?= $persen ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="profil-note">
                    <i data-lucide="info"></i>
                    <p>
                        Foto profil, nama, dan kata sandi bisa diubah di
                        <a href="<?= BASE_URL ?>/mahasiswa/pengaturan.php">Pengaturan</a>.
                        NIM dan kelas hanya bisa diubah admin.
                    </p>
                </section>
            </aside>
        </div>

    </main>
</div>

<?php require_once "../includes/footer.php"; ?>
