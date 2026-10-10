<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/peminjaman_status.php";

require_role('mahasiswa');

$user_id = (int) $_SESSION['user']['id'];

/* ==========================================================
   1. Label dan warna status (sesuai enum di tabel peminjaman)
   ========================================================== */

$info_status = [
    'diajukan' => ['label' => 'Menunggu Dosen', 'kelas' => 'menunggu', 'ket' => 'Menunggu persetujuan dosen pengampu.'],
    'disetujui_dosen' => ['label' => 'Disiapkan Lab', 'kelas' => 'proses', 'ket' => 'Disetujui dosen, sedang disiapkan admin lab.'],
    'siap_diambil' => ['label' => 'Siap Diambil', 'kelas' => 'siap', 'ket' => 'Alat siap diambil di ruang alat.'],
    'dipinjam' => ['label' => 'Sedang Dipinjam', 'kelas' => 'dipinjam', 'ket' => 'Kembalikan paling lambat 17.00.'],
    'dikembalikan' => ['label' => 'Dikembalikan', 'kelas' => 'selesai', 'ket' => 'Alat sudah dikembalikan.'],
    'ditolak' => ['label' => 'Ditolak', 'kelas' => 'ditolak', 'ket' => 'Pengajuan tidak disetujui dosen.']
];

/* ==========================================================
   2. Ringkasan (kartu statistik)
   ========================================================== */

$stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS jumlah
    FROM peminjaman
    WHERE peminjam_id = :user_id
    GROUP BY status
");
$stmt->execute([':user_id' => $user_id]);

$jumlah_status = array_fill_keys(array_keys($info_status), 0);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $baris) {
    $jumlah_status[$baris['status']] = (int) $baris['jumlah'];
}

$total = array_sum($jumlah_status);
$selesai = $jumlah_status['dikembalikan'];
$ditolak = $jumlah_status['ditolak'];
$menunggu = $jumlah_status['diajukan'] + $jumlah_status['disetujui_dosen'];
$siap = $jumlah_status['siap_diambil'] + $jumlah_status['dipinjam'];
$berjalan = $menunggu + $siap;
$persen_selesai = $total > 0 ? round($selesai / $total * 100) : 0;

/* ==========================================================
   3. Filter
   ========================================================== */

$cari = trim((string) ($_GET['cari'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$periode = (string) ($_GET['periode'] ?? 'semua');
$kategori = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT) ?: 0;

if (!isset($info_status[$status])) {
    $status = '';
}

$pilihan_periode = [
    'semua' => 'Semua waktu',
    'bulan_ini' => 'Bulan ini',
    '30_hari' => '30 hari terakhir',
    'semester' => 'Semester ini'
];

if (!isset($pilihan_periode[$periode])) {
    $periode = 'semua';
}

/* Semester ganjil: Agustus–Januari, genap: Februari–Juli */
$bulan = (int) date('n');
$tahun = (int) date('Y');

if ($bulan >= 8) {
    $awal_semester = "$tahun-08-01";
} elseif ($bulan === 1) {
    $awal_semester = ($tahun - 1) . "-08-01";
} else {
    $awal_semester = "$tahun-02-01";
}

$where = ['p.peminjam_id = :user_id'];
$param = [':user_id' => $user_id];

if ($status !== '') {
    $where[] = 'p.status = :status';
    $param[':status'] = $status;
}

if ($periode === 'bulan_ini') {
    $where[] = 'p.tgl >= :dari';
    $param[':dari'] = date('Y-m-01');
} elseif ($periode === '30_hari') {
    $where[] = 'p.tgl >= :dari';
    $param[':dari'] = date('Y-m-d', strtotime('-30 days'));
} elseif ($periode === 'semester') {
    $where[] = 'p.tgl >= :dari';
    $param[':dari'] = $awal_semester;
}

if ($kategori > 0) {
    $where[] = 'EXISTS (
        SELECT 1
        FROM peminjaman_item fi
        INNER JOIN alat fa ON fa.id = fi.alat_id
        WHERE fi.peminjaman_id = p.id
          AND fa.kategori_id = :kategori
    )';
    $param[':kategori'] = $kategori;
}

if ($cari !== '') {
    /* Cari nama alat, atau nomor pengajuan (PMJ-2026-0001 / 0001 / 1) */
    $id_dicari = preg_match('/(\d+)\s*$/', $cari, $cocok) ? (int) $cocok[1] : 0;

    $where[] = '(
        p.id = :cari_id
        OR EXISTS (
            SELECT 1
            FROM peminjaman_item si
            INNER JOIN alat sa ON sa.id = si.alat_id
            WHERE si.peminjaman_id = p.id
              AND sa.nama LIKE :cari_nama
        )
    )';
    $param[':cari_id'] = $id_dicari;
    $param[':cari_nama'] = '%' . $cari . '%';
}

$where_sql = implode(' AND ', $where);

/* Daftar kategori untuk filter */
$daftar_kategori = $pdo
    ->query("SELECT id, nama_kategori FROM kategori_alat ORDER BY nama_kategori")
    ->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   4. Data tabel + halaman
   ========================================================== */

$per_halaman = 10;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman p WHERE $where_sql");
$stmt->execute($param);
$jumlah_hasil = (int) $stmt->fetchColumn();

$total_halaman = max(1, (int) ceil($jumlah_hasil / $per_halaman));
$halaman = min($total_halaman, max(1, (int) ($_GET['hal'] ?? 1)));
$offset = ($halaman - 1) * $per_halaman;

$stmt = $pdo->prepare("
    SELECT
        p.id, p.tgl, p.jam_mulai, p.jam_selesai, p.ruangan,
        p.status, p.catatan_dosen, p.waktu_diajukan,
        p.kelengkapan_tambahan,
        d.nama AS nama_dosen,
        d.nim_nip AS nip_dosen,
        r.lokasi AS lokasi_ruangan
    FROM peminjaman p
    LEFT JOIN users d ON d.id = p.dosen_id
    LEFT JOIN ruangan r ON r.nama_ruangan = p.ruangan
    WHERE $where_sql
    ORDER BY p.waktu_diajukan DESC, p.id DESC
    LIMIT $per_halaman OFFSET $offset
");
$stmt->execute($param);
$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Alat untuk baris yang tampil */
$alat_per_pinjam = [];

if ($daftar) {
    $ids = array_column($daftar, 'id');
    $tanda = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT pi.peminjaman_id, a.id AS alat_id, a.nama, k.nama_kategori
        FROM peminjaman_item pi
        INNER JOIN alat a ON a.id = pi.alat_id
        INNER JOIN kategori_alat k ON k.id = a.kategori_id
        WHERE pi.peminjaman_id IN ($tanda)
    ");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $alat_per_pinjam[(int) $item['peminjaman_id']][] = $item;
    }
}

/* Membuat link dengan filter yang sedang aktif */
$link_filter = static function (array $ubah = []) use ($cari, $status, $periode, $kategori): string {
    $q = array_filter(array_merge([
        'cari' => $cari,
        'status' => $status,
        'periode' => $periode === 'semua' ? '' : $periode,
        'kategori' => $kategori ?: ''
    ], $ubah), static fn ($v) => $v !== '' && $v !== null);

    /* Halaman 1 tidak perlu ditulis di URL */
    if (($q['hal'] ?? null) === 1) {
        unset($q['hal']);
    }

    return BASE_URL . '/mahasiswa/riwayat.php' . ($q ? '?' . http_build_query($q) : '');
};

$ada_filter = $cari !== '' || $status !== '' || $periode !== 'semua' || $kategori > 0;

$page_title = 'Riwayat Peminjaman';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content riwayat-page">

        <!-- Judul -->
        <div class="riwayat-header">
            <div>
                <nav class="riwayat-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php">Dashboard</a>
                    <i data-lucide="chevron-right"></i>
                    <span>Riwayat Peminjaman</span>
                </nav>

                <h1>Riwayat Peminjaman Alat</h1>
                <p>Daftar seluruh pengajuan peminjaman alat, status persetujuan, dan pengembaliannya.</p>
            </div>

            <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary riwayat-btn-baru">
                <i data-lucide="plus-circle"></i>
                Ajukan Baru
            </a>
        </div>

        <!-- Statistik -->
        <section class="riwayat-stats">
            <div class="riwayat-stat">
                <div>
                    <span class="riwayat-stat-label">Total Peminjaman</span>
                    <div class="riwayat-stat-angka">
                        <strong><?= $total ?></strong>
                        <span>pengajuan</span>
                    </div>
                    <small>Sejak akun dibuat</small>
                </div>
                <div class="riwayat-stat-icon"><i data-lucide="package"></i></div>
            </div>

            <div class="riwayat-stat">
                <div>
                    <span class="riwayat-stat-label">Selesai &amp; Dikembalikan</span>
                    <div class="riwayat-stat-angka">
                        <strong><?= $selesai ?></strong>
                        <span class="riwayat-persen"><?= $persen_selesai ?>%</span>
                    </div>
                    <small>Dari seluruh pengajuan</small>
                </div>
                <div class="riwayat-stat-icon is-hijau"><i data-lucide="clipboard-check"></i></div>
            </div>

            <div class="riwayat-stat">
                <div>
                    <span class="riwayat-stat-label">Sedang Berjalan</span>
                    <div class="riwayat-stat-angka">
                        <strong class="is-primary"><?= $berjalan ?></strong>
                        <span>aktif</span>
                    </div>
                    <small class="riwayat-stat-rinci">
                        <span><i class="dot dot-menunggu"></i><?= $menunggu ?> menunggu</span>
                        <span><i class="dot dot-siap"></i><?= $siap ?> siap / dipinjam</span>
                    </small>
                </div>
                <div class="riwayat-stat-icon"><i data-lucide="clock-3"></i></div>
            </div>

            <div class="riwayat-stat">
                <div>
                    <span class="riwayat-stat-label">Ditolak</span>
                    <div class="riwayat-stat-angka">
                        <strong><?= $ditolak ?></strong>
                        <span>pengajuan</span>
                    </div>
                    <small>Tidak disetujui dosen</small>
                </div>
                <div class="riwayat-stat-icon is-merah"><i data-lucide="x-circle"></i></div>
            </div>
        </section>

        <!-- Filter -->
        <form method="GET" action="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="riwayat-filter" id="filterForm">
            <div class="riwayat-search">
                <i data-lucide="search"></i>
                <input
                    type="text"
                    name="cari"
                    value="<?= e($cari) ?>"
                    placeholder="Cari nomor pengajuan atau nama alat..."
                    class="form-control"
                >
            </div>

            <select name="status" class="form-select" data-auto>
                <option value="">Semua status</option>
                <?php foreach ($info_status as $kunci => $info): ?>
                    <option value="<?= e($kunci) ?>" <?= $status === $kunci ? 'selected' : '' ?>>
                        <?= e($info['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="periode" class="form-select" data-auto>
                <?php foreach ($pilihan_periode as $kunci => $label): ?>
                    <option value="<?= e($kunci) ?>" <?= $periode === $kunci ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="kategori" class="form-select" data-auto>
                <option value="">Semua kategori</option>
                <?php foreach ($daftar_kategori as $kat): ?>
                    <option value="<?= (int) $kat['id'] ?>" <?= $kategori === (int) $kat['id'] ? 'selected' : '' ?>>
                        <?= e($kat['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php" class="riwayat-reset <?= $ada_filter ? '' : 'is-off' ?>">
                <i data-lucide="rotate-ccw"></i>
                Reset
            </a>
        </form>

        <!-- Tabel -->
        <section class="riwayat-table-card">
            <div class="riwayat-table-wrap">
                <table class="riwayat-table">
                    <thead>
                        <tr>
                            <th>No. Pengajuan &amp; Waktu</th>
                            <th>Nama Alat &amp; Kategori</th>
                            <th>Jadwal &amp; Lokasi</th>
                            <th>Dosen</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$daftar): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="riwayat-kosong">
                                        <i data-lucide="inbox"></i>
                                        <?php if ($ada_filter): ?>
                                            <strong>Tidak ada pengajuan yang cocok</strong>
                                            <p>Coba ubah kata kunci atau reset filter.</p>
                                        <?php else: ?>
                                            <strong>Belum ada riwayat peminjaman</strong>
                                            <p>Pengajuan yang kamu kirim akan tampil di sini.</p>
                                            <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary">Lihat Katalog Alat</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($daftar as $p):
                            $info = $info_status[$p['status']] ?? ['label' => $p['status'], 'kelas' => 'menunggu', 'ket' => ''];
                            $items = $alat_per_pinjam[(int) $p['id']] ?? [];
                            $alat_utama = $items[0] ?? null;
                            $kode = kode_pengajuan((int) $p['id'], $p['waktu_diajukan']);
                            $url_detail = BASE_URL . '/mahasiswa/detail_peminjaman.php?id=' . (int) $p['id'];
                        ?>
                            <tr class="<?= $p['status'] === 'ditolak' ? 'is-ditolak' : '' ?>">
                                <td>
                                    <a href="<?= e($url_detail) ?>" class="riwayat-kode">#<?= e($kode) ?></a>
                                    <span class="riwayat-sub">Diajukan: <?= e(format_waktu_singkat($p['waktu_diajukan']) ?: '-') ?></span>
                                </td>

                                <td>
                                    <div class="riwayat-alat">
                                        <div class="riwayat-alat-icon"><i data-lucide="package"></i></div>
                                        <div>
                                            <strong><?= e($alat_utama['nama'] ?? '-') ?></strong>
                                            <?php if (count($items) > 1): ?>
                                                <span class="riwayat-sub">+<?= count($items) - 1 ?> alat lain</span>
                                            <?php endif; ?>
                                            <span class="riwayat-chip"><?= e($alat_utama['nama_kategori'] ?? '-') ?></span>
                                            <?php if (!empty($p['kelengkapan_tambahan'])): ?>
                                                <span class="riwayat-sub riwayat-extra">
                                                    <i data-lucide="plug"></i>
                                                    <?= e($p['kelengkapan_tambahan']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="riwayat-baris riwayat-baris-utama">
                                        <i data-lucide="calendar"></i>
                                        <?= e(format_tanggal_indonesia($p['tgl'])) ?>
                                    </span>
                                    <span class="riwayat-baris">
                                        <i data-lucide="clock"></i>
                                        <?= e(format_jam_titik($p['jam_mulai'])) ?> – <?= e(format_jam_titik($p['jam_selesai'])) ?> WIB
                                    </span>
                                    <span class="riwayat-baris">
                                        <i data-lucide="map-pin"></i>
                                        <?= e($p['ruangan']) ?><?= $p['lokasi_ruangan'] ? ', ' . e($p['lokasi_ruangan']) : '' ?>
                                    </span>
                                </td>

                                <td>
                                    <strong class="riwayat-dosen"><?= e($p['nama_dosen'] ?? '-') ?></strong>
                                    <?php if (!empty($p['nip_dosen'])): ?>
                                        <span class="riwayat-sub">NIP. <?= e($p['nip_dosen']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="riwayat-status is-<?= e($info['kelas']) ?>">
                                        <i class="dot"></i><?= e($info['label']) ?>
                                    </span>

                                    <?php if ($p['status'] === 'ditolak' && !empty($p['catatan_dosen'])): ?>
                                        <span class="riwayat-alasan">
                                            <i data-lucide="alert-triangle"></i>
                                            <?= e($p['catatan_dosen']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="riwayat-sub"><?= e($info['ket']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <div class="riwayat-aksi">
                                        <a href="<?= e($url_detail) ?>" class="riwayat-aksi-btn" title="Lihat detail">
                                            <i data-lucide="eye"></i>
                                        </a>

                                        <?php if ($p['status'] === 'ditolak' && $alat_utama): ?>
                                            <a
                                                href="<?= BASE_URL ?>/mahasiswa/ajukan.php?alat_id=<?= (int) $alat_utama['alat_id'] ?>"
                                                class="riwayat-aksi-btn is-muted"
                                                title="Ajukan ulang"
                                            >
                                                <i data-lucide="refresh-cw"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($jumlah_hasil > 0): ?>
                <div class="riwayat-footer">
                    <span>
                        Menampilkan
                        <strong><?= $offset + 1 ?>–<?= min($offset + $per_halaman, $jumlah_hasil) ?></strong>
                        dari <strong><?= $jumlah_hasil ?></strong> riwayat peminjaman
                    </span>

                    <?php if ($total_halaman > 1): ?>
                        <nav class="riwayat-pagination" aria-label="Halaman">
                            <a
                                class="riwayat-page-btn <?= $halaman <= 1 ? 'is-disabled' : '' ?>"
                                href="<?= e($link_filter(['hal' => $halaman - 1])) ?>"
                            >
                                <i data-lucide="chevron-left"></i> Sebelumnya
                            </a>

                            <?php for ($i = 1; $i <= $total_halaman; $i++): ?>
                                <a
                                    class="riwayat-page-num <?= $i === $halaman ? 'is-active' : '' ?>"
                                    href="<?= e($link_filter(['hal' => $i])) ?>"
                                ><?= $i ?></a>
                            <?php endfor; ?>

                            <a
                                class="riwayat-page-btn <?= $halaman >= $total_halaman ? 'is-disabled' : '' ?>"
                                href="<?= e($link_filter(['hal' => $halaman + 1])) ?>"
                            >
                                Berikutnya <i data-lucide="chevron-right"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Info -->
        <div class="riwayat-info">
            <i data-lucide="info"></i>
            <p>
                Klik nomor pengajuan atau ikon <strong>mata</strong> untuk melihat status
                terbaru. Status di halaman detail diperbarui otomatis tanpa perlu refresh.
            </p>
        </div>

    </main>
</div>

<script>
/* Filter dropdown langsung diterapkan saat diganti */
document.querySelectorAll('#filterForm select[data-auto]').forEach(function (select) {
    select.addEventListener('change', function () {
        select.form.submit();
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
