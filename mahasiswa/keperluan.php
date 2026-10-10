<?php
 
require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../config/database.php";
 
require_role('mahasiswa');
 
/* ==========================================================
   1. Pastikan langkah sebelumnya (alat + jadwal) sudah diisi
   ========================================================== */
 
$draft = $_SESSION['peminjaman_draft'] ?? null;
$alat_id_url = filter_input(INPUT_GET, 'alat_id', FILTER_VALIDATE_INT);
 
$draft_lengkap = is_array($draft)
    && !empty($draft['alat_id'])
    && !empty($draft['tanggal'])
    && !empty($draft['ruangan'])
    && !empty($draft['jam_mulai'])
    && !empty($draft['jam_selesai']);
 
if (!$draft_lengkap) {
    $tujuan_redirect = $alat_id_url
        ? "/mahasiswa/jadwal.php?alat_id=" . $alat_id_url
        : "/mahasiswa/katalog.php";
 
    header("Location: " . BASE_URL . $tujuan_redirect);
    exit;
}
 
$alat_id = (int) $draft['alat_id'];
 
/* Draft milik alat lain: arahkan ulang ke jadwal alat yang dibuka */
if ($alat_id_url && $alat_id_url !== $alat_id) {
    header("Location: " . BASE_URL . "/mahasiswa/jadwal.php?alat_id=" . $alat_id_url);
    exit;
}
 
/* ==========================================================
   2. Data pendukung tampilan
   ========================================================== */
 
$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.nama,
        a.stok_total,
        k.nama_kategori AS kategori
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
 
/* Lokasi ruangan (opsional, hanya untuk tampilan) */
$lokasi_ruangan = '';
 
try {
    $stmt = $pdo->prepare("
        SELECT lokasi
        FROM ruangan
        WHERE nama_ruangan = :nama
        LIMIT 1
    ");
    $stmt->execute([':nama' => $draft['ruangan']]);
    $lokasi_ruangan = (string) ($stmt->fetchColumn() ?: '');
} catch (PDOException $e) {
    $lokasi_ruangan = '';
}
 
/* Kelas diambil dari akun mahasiswa, tidak bisa diubah di form */
$user_id = $_SESSION['user']['id'] ?? null;
$kelas = '';
$kelas_id = 0;

if ($user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT k.id, k.nama_kelas
            FROM kelas_mahasiswa km
            INNER JOIN kelas k ON k.id = km.kelas_id
            WHERE km.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $baris_kelas = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($baris_kelas) {
            $kelas_id = (int) $baris_kelas['id'];
            $kelas = (string) $baris_kelas['nama_kelas'];
        }
    } catch (PDOException $e) {
        $kelas = '';
        $kelas_id = 0;
    }
}

/* Pilihan jenis keperluan */
$pilihan_tujuan = [
    'Penggunaan kelas',
    'Organisasi'
];

/* Daftar dosen (untuk dosen penanggung jawab organisasi) */
$daftar_dosen = [];

try {
    $stmt = $pdo->query("
        SELECT id, nama, nim_nip
        FROM users
        WHERE role = 'dosen'
        ORDER BY nama
    ");
    $daftar_dosen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $daftar_dosen = [];
}

/*
 * Daftar mata kuliah dan organisasi harus berasal dari
 * tabel master serta relasi kelas/dosen yang benar-benar
 * tersedia di database. Tidak memakai data contoh palsu.
 */
$daftar_mata_kuliah = [];
$daftar_organisasi = [];

if ($kelas_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                kode_mata_kuliah,
                nama_mata_kuliah,
                dosen_pengampu,
                dosen_mitra
            FROM mata_kuliah
            WHERE kelas_id = :kelas_id
            ORDER BY nama_mata_kuliah
        ");
        $stmt->execute([':kelas_id' => $kelas_id]);
        $daftar_mata_kuliah = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $daftar_mata_kuliah = [];
    }
}

/* Mata kuliah per id, untuk validasi di server */
$mata_kuliah_per_id = [];

foreach ($daftar_mata_kuliah as $mk) {
    $mata_kuliah_per_id[(string) $mk['id']] = $mk;
}

$nama_dosen_valid = array_column($daftar_dosen, 'nama');
$nama_organisasi_valid = array_column($daftar_organisasi, 'nama');

/*
 * Nilai pilihan: dari POST saat formulir dikirim,
 * atau dari draft saat mahasiswa kembali ke langkah ini.
 */
$tersimpan = is_array($draft['keperluan'] ?? null)
    ? $draft['keperluan']
    : [];

$sumber_nilai = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $_POST
    : $tersimpan;

$ambil_teks = static function (array $sumber, string $kunci): string {
    return is_string($sumber[$kunci] ?? null) ? trim($sumber[$kunci]) : '';
};

$tujuan = $ambil_teks($sumber_nilai, 'tujuan');
$mata_kuliah = $ambil_teks($sumber_nilai, 'mata_kuliah');
$dosen_pengampu = $ambil_teks($sumber_nilai, 'dosen_pengampu');
$organisasi = $ambil_teks($sumber_nilai, 'organisasi');
$dosen_pj = $ambil_teks($sumber_nilai, 'dosen_pj');
$keterangan = $ambil_teks($sumber_nilai, 'keterangan');

/* ==========================================================
   3. Proses formulir (hanya saat dikirim)
   ========================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    if (!in_array($tujuan, $pilihan_tujuan, true)) {
        $errors[] = 'Pilih jenis keperluan.';
    }

    $mk_dipilih = $mata_kuliah_per_id[$mata_kuliah] ?? null;

    if ($tujuan === 'Penggunaan kelas') {
        if (!$mk_dipilih) {
            $errors[] = 'Pilih mata kuliah dari daftar kelasmu.';
        } else {
            $dosen_mk = array_filter([
                (string) ($mk_dipilih['dosen_pengampu'] ?? ''),
                (string) ($mk_dipilih['dosen_mitra'] ?? '')
            ]);

            if (!in_array($dosen_pengampu, $dosen_mk, true)) {
                $errors[] = 'Pilih dosen yang mengampu mata kuliah tersebut.';
            }
        }
    }

    if ($tujuan === 'Organisasi') {
        if (!in_array($organisasi, $nama_organisasi_valid, true)) {
            $errors[] = 'Pilih organisasi dari daftar.';
        }

        if (!in_array($dosen_pj, $nama_dosen_valid, true)) {
            $errors[] = 'Pilih dosen penanggung jawab organisasi.';
        }
    }

    if (mb_strlen($keterangan) < 10 || mb_strlen($keterangan) > 500) {
        $errors[] = 'Keterangan harus berisi 10 sampai 500 karakter.';
    }

    if (!$errors) {
        $untuk_kelas = $tujuan === 'Penggunaan kelas';

        $_SESSION['peminjaman_draft']['keperluan'] = [
            'kelas' => $kelas,
            'kelas_id' => $kelas_id,
            'tujuan' => $tujuan,
            'mata_kuliah' => $untuk_kelas ? $mata_kuliah : '',
            'nama_mata_kuliah' => $untuk_kelas
                ? (string) $mk_dipilih['nama_mata_kuliah']
                : '',
            'dosen_pengampu' => $untuk_kelas ? $dosen_pengampu : '',
            'organisasi' => $untuk_kelas ? '' : $organisasi,
            'dosen_pj' => $untuk_kelas ? '' : $dosen_pj,
            'keterangan' => $keterangan
        ];

        header(
            "Location: " . BASE_URL .
            "/mahasiswa/konfirmasi.php?alat_id=" . $alat_id
        );
        exit;
    }
}
 
/* ==========================================================
   4. Format tampilan
   ========================================================== */
 
$nama_bulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
 
$tanggal_obj = DateTime::createFromFormat('!Y-m-d', (string) $draft['tanggal']);
 
$tanggal_tampil = $tanggal_obj
    ? $tanggal_obj->format('j') . ' '
        . $nama_bulan[(int) $tanggal_obj->format('n')] . ' '
        . $tanggal_obj->format('Y')
    : (string) $draft['tanggal'];
 
$format_jam = static function (string $jam): string {
    return str_replace(':', '.', substr($jam, 0, 5));
};
 
$waktu_tampil = $format_jam((string) $draft['jam_mulai'])
    . ' - '
    . $format_jam((string) $draft['jam_selesai'])
    . ' WIB';
 
$stok_tersedia = (int) $alat['stok_total'] > 0;
 
$page_title = 'Keperluan Peminjaman';
 
require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>
 
<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>
 
    <main class="main-content keperluan-page">
 
        <div class="page-header borrowing-header">
            <div>
                <div class="page-label">PEMINJAMAN ALAT</div>
                <h1>Ajukan Peminjaman Alat</h1>
                <p>Lengkapi keperluan akademik untuk peminjaman alat.</p>
            </div>
        </div>
 
        <!-- Stepper -->
        <section class="borrowing-stepper">
            <div class="borrowing-step completed">
                <div class="step-circle">
                    <i data-lucide="check"></i>
                </div>
                <span>Informasi Alat</span>
            </div>
 
            <div class="step-line completed"></div>
 
            <div class="borrowing-step completed">
                <div class="step-circle">
                    <i data-lucide="check"></i>
                </div>
                <span>Jadwal Peminjaman</span>
            </div>
 
            <div class="step-line completed"></div>
 
            <div class="borrowing-step active">
                <div class="step-circle">3</div>
                <span>Keperluan</span>
            </div>
 
            <div class="step-line"></div>
 
            <div class="borrowing-step">
                <div class="step-circle">4</div>
                <span>Konfirmasi</span>
            </div>
        </section>

        <!-- Notifikasi -->
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <strong>
                    <i data-lucide="alert-circle"></i>
                    Keperluan belum lengkap
                </strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="keperluan-layout">
 
            <!-- Kartu kiri: alat dan jadwal -->
            <article class="borrowing-panel keperluan-summary">
                <h2>Alat yang dipilih</h2>
 
                <div class="keperluan-equipment-image">
                    <i data-lucide="package"></i>
                </div>
 
                <div class="keperluan-equipment-name">
                    <h3><?= e($alat['nama']) ?></h3>
                    <span><?= e($alat['kategori']) ?></span>
                </div>
 
                <div class="keperluan-equipment-stock">
                    <span>
                        Stok tersedia:
                        <strong><?= (int) $alat['stok_total'] ?> unit</strong>
                    </span>
 
                    <span class="stock-badge <?= $stok_tersedia ? '' : 'unavailable' ?>">
                        <?= $stok_tersedia ? 'Tersedia' : 'Habis' ?>
                    </span>
                </div>
 
                <div class="keperluan-recap">
                    <div class="keperluan-recap-title">
                        <i data-lucide="calendar-days"></i>
                        <span>Jadwal yang ditentukan</span>
                    </div>
 
                    <dl class="keperluan-recap-list">
                        <div>
                            <dt>Tanggal</dt>
                            <dd><?= e($tanggal_tampil) ?></dd>
                        </div>
 
                        <div>
                            <dt>Waktu</dt>
                            <dd><?= e($waktu_tampil) ?></dd>
                        </div>
 
                        <div>
                            <dt>Ruangan</dt>
                            <dd>
                                <?= e($draft['ruangan']) ?>
                                <?php if ($lokasi_ruangan !== ''): ?>
                                    <span class="keperluan-recap-sub">
                                        <?= e($lokasi_ruangan) ?>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </article>
 
            <!-- Kartu kanan: formulir keperluan -->
            <article class="borrowing-panel keperluan-form-panel">
 
                <div class="keperluan-panel-head">
                    <div class="keperluan-panel-icon">
                        <i data-lucide="file-text"></i>
                    </div>
 
                    <div>
                        <h2>Detail Keperluan Akademik</h2>
                        <p>
                            Pengajuan mahasiswa wajib diverifikasi dan
                            disetujui oleh dosen pengampu kelas.
                        </p>
                    </div>
                </div>


                <form
                    method="POST"
                    action="<?= BASE_URL ?>/mahasiswa/keperluan.php?alat_id=<?= $alat_id ?>"
                    id="keperluanForm"
                    class="keperluan-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

 
                
        <div class="keperluan-field">
                    <label for="kelas" class="form-label">Kelas Mahasiswa</label>
                    <input
                        type="text"
                        class="form-control"
                        id="kelas"
                        value="<?= e($kelas ?: 'Kelas belum terdaftar') ?>"
                        readonly
                    >
                    <small class="keperluan-hint">
                        Kelas mengikuti data akun mahasiswa.
                    </small>
        </div>

        <div class="keperluan-field">
            <label for="tujuan" class="form-label">
                Jenis Keperluan <span class="keperluan-required">*</span>
            </label>
            <select class="form-select" id="tujuan" name="tujuan" required>
                <option value="">Pilih jenis keperluan</option>
                <option value="Penggunaan kelas"
                    <?= $tujuan === 'Penggunaan kelas' ? 'selected' : '' ?>>
                    Penggunaan kelas
                </option>
                <option value="Organisasi"
                    <?= $tujuan === 'Organisasi' ? 'selected' : '' ?>>
                    Organisasi
                </option>
            </select>
        </div>

        <div id="formKelas"
            class="keperluan-conditional"
            <?= $tujuan === 'Penggunaan kelas' ? '' : 'hidden' ?>>

            <div class="keperluan-field">
                <label for="mata_kuliah" class="form-label">Mata Kuliah</label>
                <select class="form-select" id="mata_kuliah" name="mata_kuliah">
                    <option value="">Pilih mata kuliah</option>
                    <?php foreach ($daftar_mata_kuliah as $mk): ?>
                        <option
                            value="<?= e((string) $mk['id']) ?>"
                            <?= $mata_kuliah === (string) $mk['id'] ? 'selected' : '' ?>>
                            <?= e($mk['nama_mata_kuliah']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$daftar_mata_kuliah): ?>
                    <small class="keperluan-hint">
                        Data mata kuliah belum tersedia pada sumber data yang terhubung.
                    </small>
                <?php endif; ?>
            </div>

            <div class="keperluan-field">
                <label for="dosen_pengampu" class="form-label">
                    Dosen yang Dimintai Persetujuan
                </label>

                <select
                    class="form-select"
                    id="dosen_pengampu"
                    name="dosen_pengampu"
                    required
                >
                    <option value="">Pilih mata kuliah terlebih dahulu</option>
                </select>
            </div>
        </div>

        <div id="formOrganisasi"
            class="keperluan-conditional"
            <?= $tujuan === 'Organisasi' ? '' : 'hidden' ?>>

            <div class="keperluan-field">
                <label for="organisasi" class="form-label">Organisasi</label>
                <select class="form-select" id="organisasi" name="organisasi">
                    <option value="">Pilih organisasi</option>
                    <?php foreach ($daftar_organisasi as $org): ?>
                        <option
                            value="<?= e($org['nama']) ?>"
                            <?= $organisasi === $org['nama'] ? 'selected' : '' ?>>
                            <?= e($org['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$daftar_organisasi): ?>
                    <small class="keperluan-hint">
                        Data organisasi belum tersedia pada sumber data yang terhubung.
                    </small>
                <?php endif; ?>
            </div>

            <div class="keperluan-field">
                <label for="dosen_pj" class="form-label">
                    Dosen Penanggung Jawab
                </label>
                <select class="form-select" id="dosen_pj" name="dosen_pj">
                    <option value="">Pilih dosen penanggung jawab</option>
                    <?php foreach ($daftar_dosen as $dosen): ?>
                        <option
                            value="<?= e($dosen['nama']) ?>"
                            <?= $dosen_pj === $dosen['nama'] ? 'selected' : '' ?>>
                            <?= e($dosen['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="keperluan-field">
            <label for="keterangan" class="form-label">
                Keterangan Keperluan <span class="keperluan-required">*</span>
            </label>
            <textarea
                class="form-control"
                id="keterangan"
                name="keterangan"
                rows="3"
                minlength="10"
                maxlength="500"
                required
                placeholder="Jelaskan tujuan penggunaan alat..."><?= e($keterangan) ?></textarea>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tujuan = document.getElementById('tujuan');
            const formKelas = document.getElementById('formKelas');
            const formOrganisasi = document.getElementById('formOrganisasi');

            const mataKuliah = document.getElementById('mata_kuliah');
            const dosenPengampu = document.getElementById('dosen_pengampu');
            const organisasi = document.getElementById('organisasi');
            const dosenPJ = document.getElementById('dosen_pj');

            const dataMataKuliah = <?= json_encode(
                $daftar_mata_kuliah,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
            ) ?>;

            function perbaruiPilihanDosen() {
                const idMataKuliah = mataKuliah.value;

                const mk = dataMataKuliah.find(
                    item => String(item.id) === idMataKuliah
                );

                const dosenTersimpan = <?= json_encode(
                    $dosen_pengampu,
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                ) ?>;

                dosenPengampu.replaceChildren();

                const pilihanAwal = document.createElement('option');
                pilihanAwal.value = '';
                pilihanAwal.textContent = mk
                    ? 'Pilih dosen yang dimintai persetujuan'
                    : 'Pilih mata kuliah terlebih dahulu';

                dosenPengampu.appendChild(pilihanAwal);

                if (!mk) return;

                /* dosen_mitra bisa kosong, jadi lewati nilai kosong */
                [mk.dosen_pengampu, mk.dosen_mitra]
                    .filter(namaDosen => namaDosen)
                    .forEach(namaDosen => {
                        const option = document.createElement('option');
                        option.value = namaDosen;
                        option.textContent = namaDosen;
                        option.selected = namaDosen === dosenTersimpan;

                        dosenPengampu.appendChild(option);
                    });
            }

            mataKuliah.addEventListener('change', perbaruiPilihanDosen);
            perbaruiPilihanDosen();
            function perbaruiForm() {
                const kelasDipilih = tujuan.value === 'Penggunaan kelas';
                const organisasiDipilih = tujuan.value === 'Organisasi';

                formKelas.hidden = !kelasDipilih;
                formOrganisasi.hidden = !organisasiDipilih;

                mataKuliah.required = kelasDipilih;
                dosenPengampu.required = kelasDipilih;
                organisasi.required = organisasiDipilih;
                dosenPJ.required = organisasiDipilih;
            }

            tujuan.addEventListener('change', perbaruiForm);
            perbaruiForm();
        });
        </script>

                </form>
            </article>
 
        </section>
 
        <!-- Tombol di luar kartu -->
        <div class="borrowing-page-actions">
            <a
                href="<?= BASE_URL ?>/mahasiswa/jadwal.php?alat_id=<?= $alat_id ?>"
                class="btn btn-outline-secondary borrowing-back-button"
            >
                Kembali
            </a>
 
            <button
                type="submit"
                form="keperluanForm"
                class="btn btn-primary borrowing-next-button"
            >
                Lanjut ke Konfirmasi
                <i data-lucide="arrow-right"></i>
            </button>
        </div>
 
    </main>
</div>
 
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('keperluanForm');

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
});
</script>
 
<?php require_once "../includes/footer.php"; ?>