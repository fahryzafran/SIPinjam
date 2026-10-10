<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/peminjaman_status.php";
require_once "../includes/kelengkapan.php";

require_role('mahasiswa');

$peminjaman_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user']['id'];

$peminjaman = $peminjaman_id
    ? ambil_peminjaman($pdo, $peminjaman_id, $user_id)
    : null;

if (!$peminjaman) {
    http_response_code(404);
    exit('Pengajuan tidak ditemukan.');
}

$status_awal = susun_status_peminjaman($peminjaman);
$alat = $peminjaman['items'][0] ?? null;

$spesifikasi = array_filter(array_map(
    'trim',
    preg_split("/\r\n|\r|\n/", (string) ($alat['spesifikasi'] ?? ''))
));

$kelengkapan = urai_kelengkapan($peminjaman['kelengkapan_tambahan'] ?? null);

$jenis_keperluan = strpos((string) $peminjaman['keperluan'], 'Organisasi') === 0
    ? 'Organisasi'
    : 'Penggunaan kelas';

$page_title = 'Status Pengajuan ' . $status_awal['kode'];

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content status-page">

        <!-- Breadcrumb dan indikator realtime -->
        <div class="status-topline">
            <nav class="status-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>/mahasiswa/katalog.php">Peminjaman Alat</a>
                <span>/</span>
                <strong>Detail Pengajuan</strong>
            </nav>

            <div class="status-live" id="statusLive">
                <span class="status-live-dot"></span>
                <span id="statusLiveText">Status diperbarui otomatis</span>
            </div>
        </div>

        <!-- Kartu utama -->
        <section class="status-hero" id="statusHero" data-jenis="<?= e($status_awal['hero']['jenis']) ?>">
            <div class="status-hero-main">
                <div class="status-hero-icon" id="heroIcon"></div>

                <div class="status-hero-text">
                    <div class="status-hero-meta">
                        <span class="status-chip"><?= e($jenis_keperluan) ?></span>
                        <span>Tercatat: <?= e(format_waktu_singkat($peminjaman['waktu_diajukan'])) ?></span>
                    </div>

                    <h1 id="heroTitle"><?= e($status_awal['hero']['judul']) ?></h1>
                    <p id="heroDesc"><?= e($status_awal['hero']['deskripsi']) ?></p>

                    <div class="status-note" id="catatanDosen" hidden>
                        <i data-lucide="message-square-text"></i>
                        <div>
                            <strong>Catatan dosen</strong>
                            <p id="catatanDosenText"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="status-code">
                <span>Kode Pengajuan</span>
                <strong><?= e($status_awal['kode']) ?></strong>
                <small id="codeHint">Tunjukkan saat mengambil alat</small>
            </div>
        </section>

        <!-- Tracker 4 tahap -->
        <section class="status-card status-tracker">
            <div class="status-tracker-head">
                <div>
                    <span class="status-eyebrow">Alur pelacakan</span>
                    <h2>Status Pengajuan Peralatan</h2>
                </div>

                <span class="status-stage-pill" id="stagePill">
                    <i data-lucide="hourglass"></i>
                    <span id="stagePillText"></span>
                </span>
            </div>

            <div class="status-steps" id="statusSteps"></div>
        </section>

        <!-- Tiga kartu ringkasan -->
        <section class="status-grid">

            <!-- Alat -->
            <article class="status-card">
                <div class="status-card-head">
                    <span class="status-card-title">
                        <i data-lucide="package"></i>
                        Peralatan Dipinjam
                    </span>
                    <span class="status-chip status-chip-muted">1 unit</span>
                </div>

                <div class="status-alat">
                    <div class="status-alat-icon">
                        <i data-lucide="package"></i>
                    </div>

                    <div>
                        <h3><?= e($alat['nama'] ?? '-') ?></h3>
                        <p><?= e($alat['nama_kategori'] ?? '-') ?></p>
                        <p class="status-unit" id="unitText" hidden></p>
                    </div>
                </div>

                <div class="status-box status-box-extra">
                    <span class="status-box-label">Kelengkapan tambahan</span>

                    <?php if ($kelengkapan): ?>
                        <ul class="status-list">
                            <?php foreach ($kelengkapan as $item): ?>
                                <li>
                                    <i data-lucide="<?= e(ikon_kelengkapan($item)) ?>"></i>
                                    <span><?= e($item) ?> (1 pcs)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="status-box-text mt-1">Tidak ada, hanya kelengkapan standar.</p>
                    <?php endif; ?>
                </div>

                <?php if ($spesifikasi): ?>
                    <div class="status-box">
                        <span class="status-box-label">Spesifikasi</span>
                        <ul class="status-list">
                            <?php foreach ($spesifikasi as $baris): ?>
                                <li>
                                    <i data-lucide="check"></i>
                                    <span><?= e($baris) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Jadwal & lokasi -->
            <article class="status-card">
                <div class="status-card-head">
                    <span class="status-card-title">
                        <i data-lucide="calendar-days"></i>
                        Jadwal &amp; Lokasi
                    </span>
                </div>

                <div class="status-info">
                    <div class="status-info-icon"><i data-lucide="calendar"></i></div>
                    <div>
                        <span class="status-box-label">Tanggal reservasi</span>
                        <strong><?= e(format_tanggal_indonesia($peminjaman['tgl'], true)) ?></strong>
                        <small>
                            Pukul <?= e(format_jam_titik($peminjaman['jam_mulai'])) ?>
                            – <?= e(format_jam_titik($peminjaman['jam_selesai'])) ?> WIB
                        </small>
                    </div>
                </div>

                <div class="status-info">
                    <div class="status-info-icon"><i data-lucide="map-pin"></i></div>
                    <div>
                        <span class="status-box-label">Lokasi pemakaian</span>
                        <strong><?= e($peminjaman['ruangan']) ?></strong>
                        <?php if (!empty($peminjaman['lokasi_ruangan'])): ?>
                            <small><?= e($peminjaman['lokasi_ruangan']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="status-foot-note">
                    <i data-lucide="info"></i>
                    Pengembalian paling lambat 17.00, toleransi sampai 17.15.
                </p>
            </article>

            <!-- Keperluan akademik -->
            <article class="status-card">
                <div class="status-card-head">
                    <span class="status-card-title">
                        <i data-lucide="file-text"></i>
                        Keperluan Akademik
                    </span>
                    <?php if (!empty($peminjaman['nama_kelas'])): ?>
                        <span class="status-chip"><?= e($peminjaman['nama_kelas']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="status-box">
                    <span class="status-box-label">
                        <?= $peminjaman['nama_mata_kuliah'] ? 'Mata kuliah' : 'Keperluan' ?>
                    </span>
                    <strong class="status-box-value">
                        <?= e($peminjaman['nama_mata_kuliah'] ?: $peminjaman['keperluan']) ?>
                    </strong>

                    <?php if (!empty($peminjaman['keterangan'])): ?>
                        <p class="status-box-text"><?= nl2br(e($peminjaman['keterangan'])) ?></p>
                    <?php endif; ?>
                </div>

                <dl class="status-people">
                    <div>
                        <dt><i data-lucide="user"></i> Peminjam</dt>
                        <dd><?= e($_SESSION['user']['nama'] ?? '-') ?></dd>
                    </div>

                    <div>
                        <dt><i data-lucide="badge-check"></i> NIM</dt>
                        <dd class="status-mono"><?= e($_SESSION['user']['nim_nip'] ?? '-') ?></dd>
                    </div>

                    <div>
                        <dt><i data-lucide="graduation-cap"></i> Dosen</dt>
                        <dd><?= e($peminjaman['nama_dosen'] ?? '-') ?></dd>
                    </div>
                </dl>
            </article>
        </section>

        <!-- Informasi -->
        <section class="status-callout">
            <div class="status-callout-icon">
                <i data-lucide="bell-ring"></i>
            </div>

            <div>
                <h4>Status diperbarui otomatis</h4>
                <p>
                    Halaman ini mengecek status terbaru setiap beberapa detik, jadi
                    kamu tidak perlu me-refresh. Begitu dosen menyetujui atau admin lab
                    selesai menyiapkan alat, tahapan di atas langsung berubah.
                </p>
            </div>
        </section>

        <!-- Tombol -->
        <div class="status-actions">
            <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php" class="btn status-btn-outline">
                <i data-lucide="arrow-left"></i>
                Kembali ke Dashboard
            </a>

            <a href="<?= BASE_URL ?>/mahasiswa/katalog.php" class="btn btn-primary status-btn-primary">
                <i data-lucide="plus"></i>
                Ajukan Alat Lain
            </a>
        </div>

        <!-- Notifikasi perubahan status -->
        <div class="status-toast" id="statusToast" role="status" aria-live="polite" hidden>
            <i data-lucide="bell"></i>
            <span id="statusToastText"></span>
        </div>

    </main>
</div>

<script>
(function () {
    const URL_STATUS = <?= json_encode(
        BASE_URL . '/mahasiswa/status_peminjaman.php?id=' . (int) $peminjaman['id'],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;
    const INTERVAL = 5000; /* cek status tiap 5 detik */

    let data = <?= json_encode(
        $status_awal,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?>;
    let timer = null;

    const IKON_HERO = {
        terkirim: 'send',
        proses: 'user-check',
        siap: 'package-check',
        selesai: 'check-check',
        ditolak: 'x-circle'
    };

    const IKON_LANGKAH = {
        selesai: 'check',
        aktif: 'loader-circle',
        menunggu: 'clock',
        ditolak: 'x',
        batal: 'minus'
    };

    const el = (id) => document.getElementById(id);

    function ikon(nama) {
        const i = document.createElement('i');
        i.setAttribute('data-lucide', nama);
        return i;
    }

    function render(d) {
        /* Kartu utama */
        el('statusHero').dataset.jenis = d.hero.jenis;
        el('heroTitle').textContent = d.hero.judul;
        el('heroDesc').textContent = d.hero.deskripsi;
        el('heroIcon').replaceChildren(ikon(IKON_HERO[d.hero.jenis] || 'info'));

        /* Catatan dosen */
        el('catatanDosen').hidden = d.catatan_dosen === '';
        el('catatanDosenText').textContent = d.catatan_dosen;

        /* Nomor unit dari admin lab */
        el('unitText').hidden = d.unit.length === 0;
        el('unitText').textContent = 'Nomor unit: ' + d.unit.join(', ');

        /* Label tahap */
        el('stagePillText').textContent =
            d.final ? d.status_label : 'Tahap ' + d.tahap + ' dari 4 · ' + d.status_label;
        el('stagePill').dataset.state = d.status === 'ditolak' ? 'ditolak' : '';

        /* Empat kartu tahap */
        const wadah = el('statusSteps');
        wadah.replaceChildren();

        d.langkah.forEach(function (langkah, index) {
            const kartu = document.createElement('div');
            kartu.className = 'status-step';
            kartu.dataset.state = langkah.state;

            const atas = document.createElement('div');
            atas.className = 'status-step-top';

            const bulat = document.createElement('div');
            bulat.className = 'status-step-circle';
            bulat.appendChild(ikon(IKON_LANGKAH[langkah.state] || 'circle'));

            const badge = document.createElement('span');
            badge.className = 'status-step-badge';
            badge.textContent = langkah.badge;

            atas.append(bulat, badge);

            const judul = document.createElement('h3');
            judul.textContent = (index + 1) + '. ' + langkah.judul;

            const ket = document.createElement('p');
            ket.className = 'status-step-main';
            ket.textContent = langkah.keterangan;

            const sub = document.createElement('p');
            sub.className = 'status-step-sub';
            sub.textContent = langkah.sub;

            kartu.append(atas, judul, ket, sub);
            wadah.appendChild(kartu);
        });

        /* Jam dari browser, supaya tidak bergantung zona waktu PHP */
        const jam = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });

        el('statusLiveText').textContent = d.final
            ? 'Status final · ' + jam
            : 'Diperbarui otomatis · ' + jam;

        el('codeHint').textContent = d.status === 'ditolak' || d.status === 'dikembalikan'
            ? 'Simpan sebagai arsip pengajuan'
            : 'Tunjukkan saat mengambil alat';
        el('statusLive').dataset.final = d.final ? '1' : '';

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function tampilkanToast(teks) {
        const toast = el('statusToast');
        el('statusToastText').textContent = teks;
        toast.hidden = false;
        toast.classList.remove('show');
        void toast.offsetWidth; /* ulang animasi */
        toast.classList.add('show');

        clearTimeout(tampilkanToast.t);
        tampilkanToast.t = setTimeout(function () {
            toast.classList.remove('show');
            toast.hidden = true;
        }, 5000);
    }

    async function cekStatus() {
        try {
            const respon = await fetch(URL_STATUS, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (respon.status === 401) {
                el('statusLiveText').textContent = 'Sesi berakhir, silakan login ulang';
                berhenti();
                return;
            }

            if (!respon.ok) {
                throw new Error('HTTP ' + respon.status);
            }

            const baru = await respon.json();
            const berubah = baru.status !== data.status
                || baru.unit.join() !== data.unit.join()
                || baru.catatan_dosen !== data.catatan_dosen;

            if (berubah) {
                tampilkanToast('Status diperbarui: ' + baru.status_label);
            }

            data = baru;
            render(data);
            el('statusLive').dataset.offline = '';

            if (data.final) {
                berhenti();
            }
        } catch (err) {
            el('statusLive').dataset.offline = '1';
            el('statusLiveText').textContent = 'Koneksi terputus, mencoba lagi…';
        }
    }

    function mulai() {
        if (timer || data.final) return;
        timer = setInterval(cekStatus, INTERVAL);
    }

    function berhenti() {
        clearInterval(timer);
        timer = null;
    }

    /* Hemat request: jeda saat tab tidak dilihat, cek langsung saat kembali */
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            berhenti();
        } else if (!data.final) {
            cekStatus();
            mulai();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        render(data);
        mulai();
    });
})();
</script>

<?php require_once "../includes/footer.php"; ?>
