<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/peminjaman_status.php";

require_role('mahasiswa');

$user_id = (int) $_SESSION['user']['id'];

/* ==========================================================
   1. Data pilihan
   ========================================================== */

$daftar_alat = $pdo->query("
    SELECT a.id, a.nama, a.stok_total, a.kategori_id,
           k.nama_kategori, k.max_per_kelas
    FROM alat a
    INNER JOIN kategori_alat k ON k.id = a.kategori_id
    ORDER BY a.nama
")->fetchAll(PDO::FETCH_ASSOC);

$alat_per_id = array_column($daftar_alat, null, 'id');

$daftar_kategori = $pdo->query("
    SELECT id, nama_kategori
    FROM kategori_alat
    ORDER BY nama_kategori
")->fetchAll(PDO::FETCH_ASSOC);

$kategori_per_id = array_column($daftar_kategori, null, 'id');

$label_status = [
    'diajukan' => ['label' => 'Menunggu review', 'kelas' => 'menunggu'],
    'diproses' => ['label' => 'Sedang diproses', 'kelas' => 'proses'],
    'diterima' => ['label' => 'Diterima', 'kelas' => 'diterima'],
    'ditolak' => ['label' => 'Ditolak', 'kelas' => 'ditolak']
];

/* ==========================================================
   2. Nilai form (dari POST, atau ?alat_id= dari katalog)
   ========================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ambil = static function (string $kunci): string {
    return is_string($_POST[$kunci] ?? null) ? trim($_POST[$kunci]) : '';
};

$alat_dari_url = filter_input(INPUT_GET, 'alat_id', FILTER_VALIDATE_INT) ?: 0;

$form = [
    'jenis' => 'stok',
    'alat_id' => isset($alat_per_id[$alat_dari_url]) ? (string) $alat_dari_url : '',
    'nama_alat_baru' => '',
    'spesifikasi' => '',
    'kategori_id' => '',
    'jumlah' => '1',
    'alasan' => '',
    'tautan' => ''
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($form) as $kunci) {
        $form[$kunci] = $ambil($kunci);
    }

    $csrf = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Permintaan tidak valid. Muat ulang halaman.';
    }

    if (!in_array($form['jenis'], ['stok', 'baru'], true)) {
        $errors[] = 'Pilih jenis usulan.';
        $form['jenis'] = 'stok';
    }

    $alat_id = null;
    $kategori_id = null;
    $nama_baru = null;
    $spesifikasi = null;

    if ($form['jenis'] === 'stok') {
        $alat_id = (int) $form['alat_id'];

        if (!isset($alat_per_id[$alat_id])) {
            $errors[] = 'Pilih alat dari katalog.';
        } else {
            $kategori_id = (int) $alat_per_id[$alat_id]['kategori_id'];

            /* Cegah usulan ganda untuk alat yang sama selama masih ditinjau */
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM saran_alat
                WHERE user_id = :user_id
                  AND alat_id = :alat_id
                  AND status IN ('diajukan', 'diproses')
            ");
            $stmt->execute([':user_id' => $user_id, ':alat_id' => $alat_id]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Kamu sudah punya usulan untuk alat ini yang masih ditinjau.';
            }
        }
    } else {
        $nama_baru = $form['nama_alat_baru'];
        $spesifikasi = $form['spesifikasi'] !== '' ? $form['spesifikasi'] : null;
        $kategori_id = (int) $form['kategori_id'];

        if (mb_strlen($nama_baru) < 3 || mb_strlen($nama_baru) > 150) {
            $errors[] = 'Nama alat baru harus 3 sampai 150 karakter.';
        }

        if (!isset($kategori_per_id[$kategori_id])) {
            $errors[] = 'Pilih kategori alat.';
        }

        if ($spesifikasi !== null && mb_strlen($spesifikasi) > 500) {
            $errors[] = 'Spesifikasi maksimal 500 karakter.';
        }
    }

    $jumlah = filter_var($form['jumlah'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 50]
    ]);

    if ($jumlah === false) {
        $errors[] = 'Jumlah kebutuhan harus 1 sampai 50 unit.';
    }

    $panjang_alasan = mb_strlen($form['alasan']);

    if ($panjang_alasan < 20 || $panjang_alasan > 1000) {
        $errors[] = 'Alasan pengajuan harus 20 sampai 1000 karakter.';
    }

    $tautan = $form['tautan'] !== '' ? $form['tautan'] : null;

    if (
        $tautan !== null
        && (
            mb_strlen($tautan) > 255
            || !filter_var($tautan, FILTER_VALIDATE_URL)
            || !preg_match('#^https?://#i', $tautan)
        )
    ) {
        $errors[] = 'Tautan referensi harus berupa alamat web (http/https) yang valid.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO saran_alat (
                    user_id, jenis, alat_id, nama_alat_baru, spesifikasi,
                    kategori_id, jumlah, alasan, tautan, status
                ) VALUES (
                    :user_id, :jenis, :alat_id, :nama_alat_baru, :spesifikasi,
                    :kategori_id, :jumlah, :alasan, :tautan, 'diajukan'
                )
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':jenis' => $form['jenis'],
                ':alat_id' => $alat_id,
                ':nama_alat_baru' => $nama_baru,
                ':spesifikasi' => $spesifikasi,
                ':kategori_id' => $kategori_id,
                ':jumlah' => $jumlah,
                ':alasan' => $form['alasan'],
                ':tautan' => $tautan
            ]);

            /* Beri tahu admin lab dan superadmin */
            $nama_usulan = $form['jenis'] === 'stok'
                ? 'tambah stok ' . $alat_per_id[$alat_id]['nama']
                : 'alat baru ' . $nama_baru;

            $pesan = 'Saran alat dari ' . ($_SESSION['user']['nama'] ?? 'mahasiswa')
                . ': ' . $nama_usulan . ' (' . $jumlah . ' unit).';

            $stmt = $pdo->prepare("
                INSERT INTO notifikasi (user_id, pesan, link)
                SELECT id, :pesan, :link
                FROM users
                WHERE role IN ('adminlab', 'superadmin')
            ");
            $stmt->execute([
                ':pesan' => $pesan,
                ':link' => '/adminlab/saran.php'
            ]);

            $pdo->commit();

            $_SESSION['flash_saran'] = 'Usulan berhasil dikirim ke pengelola lab.';
            header("Location: " . BASE_URL . "/mahasiswa/saran.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Simpan saran alat gagal: ' . $e->getMessage());
            $errors[] = 'Usulan belum bisa disimpan. Silakan coba lagi.';
        }
    }
}

$flash = $_SESSION['flash_saran'] ?? '';
unset($_SESSION['flash_saran']);

/* ==========================================================
   3. Usulan milik mahasiswa
   ========================================================== */

$stmt = $pdo->prepare("
    SELECT
        s.id, s.jenis, s.jumlah, s.alasan, s.tautan, s.status,
        s.catatan_admin, s.created_at, s.nama_alat_baru,
        a.nama AS nama_alat,
        k.nama_kategori
    FROM saran_alat s
    LEFT JOIN alat a ON a.id = s.alat_id
    LEFT JOIN kategori_alat k ON k.id = s.kategori_id
    WHERE s.user_id = :user_id
    ORDER BY s.created_at DESC, s.id DESC
    LIMIT 20
");
$stmt->execute([':user_id' => $user_id]);
$usulan_saya = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT status, COUNT(*) FROM saran_alat WHERE user_id = :user_id GROUP BY status");
$stmt->execute([':user_id' => $user_id]);
$jumlah_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_usulan = array_sum($jumlah_status);
$usulan_aktif = (int) ($jumlah_status['diajukan'] ?? 0) + (int) ($jumlah_status['diproses'] ?? 0);

$page_title = 'Saran Alat';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content saran-page">

        <!-- Judul -->
        <div class="saran-header">
            <div>
                <nav class="riwayat-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= BASE_URL ?>/mahasiswa/dashboard.php">Dashboard</a>
                    <i data-lucide="chevron-right"></i>
                    <span>Saran Alat</span>
                </nav>

                <h1>Saran Penambahan Alat</h1>
                <p>
                    Usulkan penambahan stok atau alat baru untuk mendukung praktikum,
                    penelitian, dan perkuliahan.
                </p>
            </div>

            <div class="saran-metric">
                <div class="saran-metric-icon"><i data-lucide="clipboard-list"></i></div>
                <div>
                    <span>Usulan diajukan</span>
                    <strong><?= $total_usulan ?> <small>usulan</small></strong>
                    <?php if ($usulan_aktif > 0): ?>
                        <em><?= $usulan_aktif ?> masih ditinjau</em>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pedoman -->
        <div class="saran-guide">
            <div class="saran-guide-icon"><i data-lucide="info"></i></div>
            <div>
                <h2>Pedoman pengajuan usulan</h2>
                <p>
                    Usulan ditinjau oleh admin lab dan superadmin. Jelaskan kendala yang
                    kamu alami secara spesifik (misalnya alat sering habis saat jam praktikum)
                    agar usulan lebih mudah diprioritaskan.
                </p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <strong><i data-lucide="alert-circle"></i> Usulan belum bisa dikirim</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="saran-layout">

            <!-- Form -->
            <section class="saran-card" aria-labelledby="judulForm">
                <div class="saran-card-head">
                    <div class="saran-card-title">
                        <div class="saran-card-icon"><i data-lucide="file-pen-line"></i></div>
                        <div>
                            <h2 id="judulForm">Kirim Usulan</h2>
                            <p>Lengkapi detail usulan di bawah ini dengan akurat.</p>
                        </div>
                    </div>
                    <span class="saran-badge">Form mahasiswa</span>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/mahasiswa/saran.php" id="saranForm" class="saran-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Jenis usulan -->
                    <fieldset class="saran-field">
                        <legend class="form-label">Jenis usulan <span class="saran-req">*</span></legend>

                        <div class="saran-jenis">
                            <label class="saran-jenis-opsi">
                                <input type="radio" name="jenis" value="stok" <?= $form['jenis'] === 'stok' ? 'checked' : '' ?>>
                                <span class="saran-jenis-icon"><i data-lucide="package-plus"></i></span>
                                <span>
                                    <strong>Tambah stok alat yang ada</strong>
                                    <small>Alat sudah ada di katalog, tapi unitnya kurang atau sering habis</small>
                                </span>
                            </label>

                            <label class="saran-jenis-opsi">
                                <input type="radio" name="jenis" value="baru" <?= $form['jenis'] === 'baru' ? 'checked' : '' ?>>
                                <span class="saran-jenis-icon"><i data-lucide="sparkles"></i></span>
                                <span>
                                    <strong>Usulan alat baru</strong>
                                    <small>Perangkat belum tersedia di SIPAKA</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <!-- Bagian: tambah stok -->
                    <div class="saran-bagian" data-jenis="stok">
                        <div class="saran-field">
                            <label for="alat_id" class="form-label">Pilih alat dari katalog <span class="saran-req">*</span></label>
                            <select name="alat_id" id="alat_id" class="form-select">
                                <option value="">Pilih alat</option>
                                <?php foreach ($daftar_alat as $alat): ?>
                                    <option
                                        value="<?= (int) $alat['id'] ?>"
                                        data-kategori="<?= e($alat['nama_kategori']) ?>"
                                        data-stok="<?= (int) $alat['stok_total'] ?>"
                                        data-kuota="<?= (int) $alat['max_per_kelas'] ?>"
                                        <?= $form['alat_id'] === (string) $alat['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($alat['nama']) ?> — stok <?= (int) $alat['stok_total'] ?> unit
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="saran-alat-info" id="alatInfo" hidden>
                                <span><i data-lucide="tag"></i> <b id="infoKategori"></b></span>
                                <span><i data-lucide="boxes"></i> Stok: <b id="infoStok"></b> unit</span>
                                <span><i data-lucide="users"></i> Kuota: <b id="infoKuota"></b> unit/kelas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian: alat baru -->
                    <div class="saran-bagian" data-jenis="baru">
                        <div class="saran-field">
                            <label for="nama_alat_baru" class="form-label">Nama alat yang diusulkan <span class="saran-req">*</span></label>
                            <input
                                type="text" id="nama_alat_baru" name="nama_alat_baru" class="form-control"
                                maxlength="150" value="<?= e($form['nama_alat_baru']) ?>"
                                placeholder="Contoh: Logic Analyzer, Fiber Optic Splicer, Arduino Starter Kit"
                            >
                        </div>

                        <div class="saran-field">
                            <label for="spesifikasi" class="form-label">
                                Spesifikasi / tipe rekomendasi <span class="saran-opsional">(opsional)</span>
                            </label>
                            <input
                                type="text" id="spesifikasi" name="spesifikasi" class="form-control"
                                maxlength="500" value="<?= e($form['spesifikasi']) ?>"
                                placeholder="Contoh: merek, model, atau spesifikasi minimal"
                            >
                        </div>

                        <div class="saran-field">
                            <label for="kategori_id" class="form-label">Kategori alat <span class="saran-req">*</span></label>
                            <select name="kategori_id" id="kategori_id" class="form-select">
                                <option value="">Pilih kategori</option>
                                <?php foreach ($daftar_kategori as $kat): ?>
                                    <option value="<?= (int) $kat['id'] ?>" <?= $form['kategori_id'] === (string) $kat['id'] ? 'selected' : '' ?>>
                                        <?= e($kat['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Jumlah -->
                    <div class="saran-field saran-field-jumlah">
                        <label for="jumlah" class="form-label">Perkiraan kebutuhan <span class="saran-req">*</span></label>
                        <div class="saran-input-unit">
                            <input
                                type="number" id="jumlah" name="jumlah" class="form-control"
                                min="1" max="50" required value="<?= e($form['jumlah']) ?>"
                            >
                            <span>unit</span>
                        </div>
                    </div>

                    <!-- Alasan -->
                    <div class="saran-field">
                        <div class="saran-label-row">
                            <label for="alasan" class="form-label">Alasan pengajuan <span class="saran-req">*</span></label>
                            <span class="saran-counter" id="alasanCounter">0 / minimal 20 karakter</span>
                        </div>
                        <textarea
                            id="alasan" name="alasan" class="form-control" rows="4"
                            minlength="20" maxlength="1000" required
                            placeholder="Contoh: Saat praktikum Jaringan Komputer hari Rabu, 8 kelompok harus bergantian memakai router yang sama sehingga praktikum molor 1 jam."
                        ><?= e($form['alasan']) ?></textarea>
                    </div>

                    <!-- Tautan -->
                    <div class="saran-field">
                        <label for="tautan" class="form-label">
                            Tautan referensi <span class="saran-opsional">(opsional)</span>
                        </label>
                        <div class="saran-input-icon">
                            <i data-lucide="link"></i>
                            <input
                                type="url" id="tautan" name="tautan" class="form-control"
                                maxlength="255" value="<?= e($form['tautan']) ?>"
                                placeholder="https://… (toko, brosur, datasheet, atau jadwal praktikum)"
                            >
                        </div>
                    </div>

                    <div class="saran-actions">
                        <button type="button" class="btn saran-btn-reset" id="resetForm">
                            <i data-lucide="rotate-ccw"></i>
                            Reset
                        </button>

                        <button type="submit" class="btn btn-primary saran-btn-kirim">
                            Kirim Saran
                            <i data-lucide="send"></i>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Usulan saya -->
            <aside class="saran-card saran-list-card" aria-labelledby="judulUsulan">
                <div class="saran-card-head">
                    <div class="saran-card-title">
                        <div class="saran-card-icon"><i data-lucide="history"></i></div>
                        <div>
                            <h2 id="judulUsulan">Usulan Saya</h2>
                            <p>Pantau status usulan yang sudah dikirim.</p>
                        </div>
                    </div>
                </div>

                <?php if ($usulan_saya): ?>
                    <div class="saran-tabs" role="tablist">
                        <button type="button" class="saran-tab is-active" data-filter="semua">Semua</button>
                        <button type="button" class="saran-tab" data-filter="aktif">Ditinjau</button>
                        <button type="button" class="saran-tab" data-filter="diterima">Diterima</button>
                        <button type="button" class="saran-tab" data-filter="ditolak">Ditolak</button>
                    </div>

                    <div class="saran-list">
                        <?php foreach ($usulan_saya as $s):
                            $info = $label_status[$s['status']] ?? ['label' => $s['status'], 'kelas' => 'menunggu'];
                            $nama = $s['jenis'] === 'baru' ? $s['nama_alat_baru'] : $s['nama_alat'];
                            $grup = in_array($s['status'], ['diajukan', 'diproses'], true) ? 'aktif' : $s['status'];
                        ?>
                            <article class="saran-item" data-grup="<?= e($grup) ?>">
                                <div class="saran-item-top">
                                    <span class="saran-status is-<?= e($info['kelas']) ?>">
                                        <i class="dot"></i><?= e($info['label']) ?>
                                    </span>
                                    <span class="saran-item-date">
                                        <?= e(format_waktu_singkat($s['created_at']) ?: '-') ?>
                                    </span>
                                </div>

                                <h3><?= e($nama ?: '-') ?></h3>
                                <p class="saran-item-meta">
                                    <?= $s['jenis'] === 'baru' ? 'Alat baru' : 'Tambah stok' ?>
                                    · <?= e($s['nama_kategori'] ?? '-') ?>
                                    · <?= (int) $s['jumlah'] ?> unit
                                </p>

                                <p class="saran-item-alasan"><?= e($s['alasan']) ?></p>

                                <?php if (!empty($s['catatan_admin'])): ?>
                                    <div class="saran-item-catatan">
                                        <i data-lucide="message-square-text"></i>
                                        <div>
                                            <strong>Tanggapan pengelola lab</strong>
                                            <?= e($s['catatan_admin']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($s['tautan'])): ?>
                                    <a href="<?= e($s['tautan']) ?>" class="saran-item-link" target="_blank" rel="noopener noreferrer">
                                        <i data-lucide="external-link"></i> Lihat referensi
                                    </a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>

                        <p class="saran-list-empty" id="listKosong" hidden>Tidak ada usulan dengan status ini.</p>
                    </div>
                <?php else: ?>
                    <div class="saran-empty">
                        <i data-lucide="inbox"></i>
                        <strong>Belum ada usulan</strong>
                        <p>Usulan yang kamu kirim akan muncul di sini beserta statusnya.</p>
                    </div>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($flash !== ''): ?>
            <div class="status-toast show" id="saranToast" role="status">
                <i data-lucide="check-circle-2"></i>
                <span><?= e($flash) ?></span>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('saranForm');
    const radios = form.querySelectorAll('input[name="jenis"]');
    const bagian = form.querySelectorAll('.saran-bagian');
    const alatSelect = document.getElementById('alat_id');
    const alasan = document.getElementById('alasan');
    const counter = document.getElementById('alasanCounter');

    /* Tampilkan field sesuai jenis usulan */
    function perbaruiJenis() {
        const jenis = form.querySelector('input[name="jenis"]:checked').value;

        bagian.forEach(function (el) {
            const aktif = el.dataset.jenis === jenis;
            el.hidden = !aktif;
            el.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !aktif;
            });
        });

        alatSelect.required = jenis === 'stok';
        document.getElementById('nama_alat_baru').required = jenis === 'baru';
        document.getElementById('kategori_id').required = jenis === 'baru';
    }

    /* Info kategori, stok, kuota untuk alat yang dipilih */
    function perbaruiInfoAlat() {
        const opsi = alatSelect.selectedOptions[0];
        const ada = opsi && opsi.value !== '';

        document.getElementById('alatInfo').hidden = !ada;

        if (ada) {
            document.getElementById('infoKategori').textContent = opsi.dataset.kategori;
            document.getElementById('infoStok').textContent = opsi.dataset.stok;
            document.getElementById('infoKuota').textContent = opsi.dataset.kuota;
        }
    }

    function perbaruiCounter() {
        const n = alasan.value.trim().length;
        counter.textContent = n + (n < 20 ? ' / minimal 20 karakter' : ' karakter');
        counter.classList.toggle('is-ok', n >= 20);
    }

    radios.forEach(function (r) { r.addEventListener('change', perbaruiJenis); });
    alatSelect.addEventListener('change', perbaruiInfoAlat);
    alasan.addEventListener('input', perbaruiCounter);

    document.getElementById('resetForm').addEventListener('click', function () {
        form.querySelectorAll('input[type="text"], input[type="url"], textarea').forEach(function (el) { el.value = ''; });
        form.querySelectorAll('select').forEach(function (el) { el.value = ''; });
        document.getElementById('jumlah').value = 1;
        form.querySelector('input[value="stok"]').checked = true;
        perbaruiJenis();
        perbaruiInfoAlat();
        perbaruiCounter();
    });

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });

    perbaruiJenis();
    perbaruiInfoAlat();
    perbaruiCounter();

    /* Filter daftar usulan */
    const tabs = document.querySelectorAll('.saran-tab');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');

            let tampil = 0;

            document.querySelectorAll('.saran-item').forEach(function (item) {
                const cocok = tab.dataset.filter === 'semua' || item.dataset.grup === tab.dataset.filter;
                item.hidden = !cocok;
                if (cocok) tampil++;
            });

            const kosong = document.getElementById('listKosong');
            if (kosong) kosong.hidden = tampil > 0;
        });
    });

    /* Notifikasi sukses hilang sendiri */
    const toast = document.getElementById('saranToast');
    if (toast) {
        setTimeout(function () { toast.classList.remove('show'); }, 4500);
        setTimeout(function () { toast.remove(); }, 5000);
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
