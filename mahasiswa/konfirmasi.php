<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/kelengkapan.php";

require_role('mahasiswa');

$draft = $_SESSION['peminjaman_draft'] ?? null;
$alat_id_url = filter_input(INPUT_GET, 'alat_id', FILTER_VALIDATE_INT);

/* Langkah sebelumnya belum lengkap: kembalikan ke langkah yang tepat */
if (empty($draft['alat_id'])) {
    header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
    exit;
}

$alat_id = (int) $draft['alat_id'];

if (empty($draft['keperluan']) || !is_array($draft['keperluan'])) {
    header("Location: " . BASE_URL . "/mahasiswa/keperluan.php?alat_id=" . $alat_id);
    exit;
}

if ($alat_id_url && $alat_id_url !== $alat_id) {
    header("Location: " . BASE_URL . "/mahasiswa/jadwal.php?alat_id=" . $alat_id_url);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* Batal: hapus draft lalu kembali ke katalog */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'batal'
) {
    $csrf = $_POST['csrf_token'] ?? '';

    if (is_string($csrf) && hash_equals($csrfToken, $csrf)) {
        unset($_SESSION['peminjaman_draft']);

        header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
        exit;
    }
}

$detail = $draft['keperluan'];
$user = $_SESSION['user'] ?? [];

$stmt = $pdo->prepare("
    SELECT a.id, a.nama, a.deskripsi, a.spesifikasi,
           a.stok_total, k.nama_kategori
    FROM alat a
    INNER JOIN kategori_alat k ON k.id = a.kategori_id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $alat_id]);
$alat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alat) {
    header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
    exit;
}

/* Kelengkapan tambahan yang dicentang di langkah Informasi Alat */
$kelengkapan = saring_kelengkapan($draft['kelengkapan'] ?? []);

$namaPemohon = $user['nama'] ?? '-';
$nimPemohon = $user['nim_nip'] ?? '-';

/* Tanggal dalam bahasa Indonesia (date('F') menghasilkan bahasa Inggris) */
$nama_bulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$tanggal_obj = DateTime::createFromFormat('!Y-m-d', (string) ($draft['tanggal'] ?? ''));

$tanggal = $tanggal_obj
    ? $tanggal_obj->format('j') . ' '
        . $nama_bulan[(int) $tanggal_obj->format('n')] . ' '
        . $tanggal_obj->format('Y')
    : '-';

$format_jam = static function (string $jam): string {
    return $jam === '' ? '-' : str_replace(':', '.', substr($jam, 0, 5));
};

$jamMulai = $format_jam((string) ($draft['jam_mulai'] ?? ''));
$jamSelesai = $format_jam((string) ($draft['jam_selesai'] ?? ''));
$ruangan = $draft['ruangan'] ?? '-';

$kelas = $detail['kelas'] ?? '';
$tujuan = $detail['tujuan'] ?? '-';
$keterangan = $detail['keterangan'] ?? '';

$mataKuliah = $detail['mata_kuliah'] ?? '';
$namaMK = $detail['nama_mata_kuliah'] ?? '';
$dosen = $detail['dosen_pengampu'] ?? '';
$organisasi = $detail['organisasi'] ?? '';
$dosenPJ = $detail['dosen_pj'] ?? '';

/* Draft lama belum menyimpan nama mata kuliah: ambil dari database */
if ($tujuan === 'Penggunaan kelas' && $namaMK === '' && $mataKuliah !== '') {
    $stmtMK = $pdo->prepare("
        SELECT nama_mata_kuliah
        FROM mata_kuliah
        WHERE id = :id
        LIMIT 1
    ");
    $stmtMK->execute([':id' => (int) $mataKuliah]);
    $namaMK = (string) ($stmtMK->fetchColumn() ?: '');
}

/* Pesan error dari proses_peminjaman.php (jika pengiriman gagal) */
$flash_errors = $_SESSION['flash_konfirmasi'] ?? [];
unset($_SESSION['flash_konfirmasi']);

$page_title = "Konfirmasi Peminjaman";

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content konfirmasi-page">

        <!-- Judul halaman -->
        <div class="page-header borrowing-header">
            <div>
                <div class="page-label">PEMINJAMAN ALAT</div>
                <h1>Ajukan Peminjaman Alat</h1>
                <p>Silakan periksa kembali rincian peminjaman sebelum mengirim pengajuan.</p>
            </div>
        </div>

        <!-- Stepper 4 tahap (sama dengan halaman jadwal dan keperluan) -->
        <section class="borrowing-stepper">
            <div class="borrowing-step completed">
                <div class="step-circle"><i data-lucide="check"></i></div>
                <span>Informasi Alat</span>
            </div>

            <div class="step-line completed"></div>

            <div class="borrowing-step completed">
                <div class="step-circle"><i data-lucide="check"></i></div>
                <span>Jadwal Peminjaman</span>
            </div>

            <div class="step-line completed"></div>

            <div class="borrowing-step completed">
                <div class="step-circle"><i data-lucide="check"></i></div>
                <span>Keperluan</span>
            </div>

            <div class="step-line completed"></div>

            <div class="borrowing-step active">
                <div class="step-circle">4</div>
                <span>Konfirmasi</span>
            </div>
        </section>

        <?php if ($flash_errors): ?>
            <div class="alert alert-danger" role="alert">
                <strong>
                    <i data-lucide="alert-circle"></i>
                    Pengajuan belum bisa dikirim
                </strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($flash_errors as $pesan): ?>
                        <li><?= e($pesan) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Ringkasan pengajuan -->
        <div class="konfirmasi-layout">

            <!-- Kolom kiri: alat -->
            <section class="konfirmasi-card alat-card">

                <div class="card-section-heading">
                    <h2>Alat yang Dipilih</h2>

                    <span class="status-pill">
                        <span class="status-dot"></span>
                        Stok: <?= (int) $alat['stok_total'] ?> unit
                    </span>
                </div>

                <div class="alat-visual">
                    <div class="alat-placeholder">
                        <i data-lucide="package"></i>
                        <span>Peralatan Kampus</span>
                    </div>
                </div>

                <div class="alat-info">
                    <h3><?= e($alat['nama']) ?></h3>
                    <p><?= e($alat['nama_kategori']) ?></p>

                    <div class="jumlah-info">
                        <span>Jumlah diajukan</span>
                        <strong>1 unit</strong>
                    </div>
                </div>

                <!-- Kelengkapan tambahan dari langkah 1 -->
                <div class="kelengkapan-tambahan">
                    <div class="kelengkapan-tambahan-head">
                        <h4>Kelengkapan tambahan</h4>
                        <a href="<?= BASE_URL ?>/mahasiswa/ajukan.php?alat_id=<?= $alat_id ?>">
                            Ubah
                        </a>
                    </div>

                    <?php if ($kelengkapan): ?>
                        <ul>
                            <?php foreach ($kelengkapan as $item): ?>
                                <li>
                                    <i data-lucide="<?= e(ikon_kelengkapan($item)) ?>"></i>
                                    <span><?= e($item) ?></span>
                                    <small>1 pcs</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="kelengkapan-kosong">
                            Tidak ada. Hanya kelengkapan standar alat.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="alat-spec">
                    <h4>Spesifikasi alat</h4>
                    <p>
                        <?= nl2br(e($alat['spesifikasi'] ?: 'Spesifikasi belum tersedia.')) ?>
                    </p>
                </div>

                <div class="alat-description">
                    <h4>Deskripsi alat</h4>
                    <p>
                        <?= nl2br(e($alat['deskripsi'] ?: 'Deskripsi belum tersedia.')) ?>
                    </p>
                </div>

            </section>

            <!-- Kolom kanan -->
            <div class="konfirmasi-right">

                <!-- Jadwal dan lokasi -->
                <section class="konfirmasi-card">

                    <div class="card-section-heading">
                        <div class="section-title-group">
                            <div class="section-icon">
                                <i data-lucide="calendar-check"></i>
                            </div>

                            <div>
                                <h2>Jadwal &amp; Lokasi Penggunaan</h2>
                                <p>Rincian waktu dan tempat penggunaan alat</p>
                            </div>
                        </div>
                    </div>

                    <div class="jadwal-grid">

                        <div class="info-tile">
                            <span>Tanggal peminjaman</span>
                            <strong>
                                <i data-lucide="calendar"></i>
                                <?= e($tanggal) ?>
                            </strong>
                        </div>

                        <div class="info-tile">
                            <span>Waktu penggunaan</span>
                            <strong>
                                <i data-lucide="clock"></i>
                                <?= e($jamMulai) ?> – <?= e($jamSelesai) ?> WIB
                            </strong>
                        </div>

                        <div class="info-tile full-width">
                            <span>Gedung &amp; ruangan</span>
                            <strong>
                                <i data-lucide="map-pin"></i>
                                <?= e($ruangan) ?>
                            </strong>
                        </div>

                    </div>
                </section>

                <!-- Data akademik -->
                <section class="konfirmasi-card">

                    <div class="card-section-heading">
                        <div class="section-title-group">
                            <div class="section-icon academic-icon">
                                <i data-lucide="graduation-cap"></i>
                            </div>

                            <div>
                                <h2>Data Akademik &amp; Pengampu</h2>
                                <p>Informasi pemohon dan persetujuan penggunaan alat</p>
                            </div>
                        </div>
                    </div>

                    <div class="akademik-grid">

                        <div class="akademik-item">
                            <span>Pemohon</span>
                            <strong><?= e($namaPemohon) ?></strong>
                            <small>NIM/NIP: <?= e($nimPemohon) ?></small>
                        </div>

                        <div class="akademik-item">
                            <span>Kelas</span>
                            <strong><?= e($kelas ?: '-') ?></strong>
                        </div>

                        <div class="akademik-item">
                            <span>Tujuan penggunaan</span>
                            <strong><?= e($tujuan) ?></strong>
                        </div>

                        <?php if ($tujuan === 'Penggunaan kelas'): ?>

                            <div class="akademik-item">
                                <span>Mata kuliah</span>
                                <strong><?= e($namaMK ?: '-') ?></strong>
                            </div>

                            <div class="akademik-item full-width">
                                <span>Dosen yang dimintai persetujuan</span>
                                <strong><?= e($dosen ?: '-') ?></strong>
                            </div>

                        <?php else: ?>

                            <div class="akademik-item">
                                <span>Organisasi</span>
                                <strong><?= e($organisasi ?: '-') ?></strong>
                            </div>

                            <div class="akademik-item">
                                <span>Dosen penanggung jawab</span>
                                <strong><?= e($dosenPJ ?: '-') ?></strong>
                            </div>

                        <?php endif; ?>

                        <div class="akademik-item full-width">
                            <span>Deskripsi keperluan peminjaman</span>
                            <div class="keterangan-box">
                                <?= nl2br(e($keterangan ?: '-')) ?>
                            </div>
                        </div>

                    </div>
                </section>

                <!-- Alur persetujuan -->
                <section class="alur-persetujuan">
                    <i data-lucide="info"></i>

                    <div>
                        <h3>Alur Persetujuan Peminjaman</h3>
                        <p>
                            Pengajuan akan menunggu persetujuan dosen yang dipilih.
                            Setelah disetujui, Admin Lab akan memproses persiapan
                            alat untuk diambil sesuai ketentuan laboratorium.
                        </p>
                    </div>
                </section>

                <!-- Pernyataan -->
                <label class="konfirmasi-deklarasi">
                    <input
                        type="checkbox"
                        id="persetujuanPernyataan"
                        name="setuju"
                        value="1"
                        form="formKonfirmasi"
                        required
                    >

                    <span>
                        Saya menyatakan bahwa data yang diisi telah sesuai dan
                        bersedia mematuhi tata tertib peminjaman, perawatan,
                        serta batas pengembalian alat kampus.
                    </span>
                </label>

            </div>
        </div>

        <!-- Tombol aksi -->
        <div class="konfirmasi-footer">
            <a
                href="<?= BASE_URL ?>/mahasiswa/keperluan.php?alat_id=<?= $alat_id ?>"
                class="btn-konfirmasi btn-kembali"
            >
                <i data-lucide="arrow-left"></i>
                Kembali
            </a>

            <div class="footer-right">
                <!-- Batal: kirim ke halaman ini agar draft dihapus -->
                <form
                    method="post"
                    action="<?= BASE_URL ?>/mahasiswa/konfirmasi.php?alat_id=<?= $alat_id ?>"
                    onsubmit="return confirm('Batalkan pengajuan peminjaman ini?');"
                >
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="aksi" value="batal">

                    <button type="submit" class="btn-batal">Batal</button>
                </form>

                <form
                    id="formKonfirmasi"
                    method="post"
                    action="<?= BASE_URL ?>/mahasiswa/proses_peminjaman.php"
                >
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <button
                        type="submit"
                        id="btnKirimPengajuan"
                        class="btn-konfirmasi btn-kirim"
                        disabled
                    >
                        Kirim Pengajuan
                        <i data-lucide="send"></i>
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('persetujuanPernyataan');
    const tombol = document.getElementById('btnKirimPengajuan');
    const form = document.getElementById('formKonfirmasi');

    checkbox.addEventListener('change', function () {
        tombol.disabled = !checkbox.checked;
    });

    form.addEventListener('submit', function (event) {
        if (!checkbox.checked) {
            event.preventDefault();
            checkbox.reportValidity();
            return;
        }

        /* Cegah klik ganda yang membuat pengajuan dobel */
        tombol.disabled = true;
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
