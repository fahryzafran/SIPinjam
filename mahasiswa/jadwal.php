<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../config/database.php";

require_role('mahasiswa');

$alat_id = filter_input(INPUT_GET, 'alat_id', FILTER_VALIDATE_INT);

if (!$alat_id || $alat_id < 1) {
    header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
    exit;
}

/* Ambil informasi alat */
$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.nama,
        a.deskripsi,
        a.spesifikasi,
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
    http_response_code(404);
    exit('Alat tidak ditemukan.');
}

/* Ambil ruangan aktif dari database */
$stmtRuangan = $pdo->query("
    SELECT id, nama_ruangan, lokasi
    FROM ruangan
    WHERE status = 'aktif'
    ORDER BY lokasi, nama_ruangan
");
$daftar_ruangan = $stmtRuangan->fetchAll(PDO::FETCH_ASSOC);

/* Token keamanan formulir */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

$tanggal = $_POST['tanggal'] ?? '';
$ruangan = trim($_POST['ruangan'] ?? '');
$jam_mulai = $_POST['jam_mulai'] ?? '';
$jam_selesai = $_POST['jam_selesai'] ?? '';

/* Proses formulir */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_token'], $csrf)
    ) {
        $errors[] = 'Permintaan tidak valid. Muat ulang halaman.';
    }

    /* Validasi tanggal */
    $tanggalObj = DateTime::createFromFormat('!Y-m-d', $tanggal);

    if (
        !$tanggalObj ||
        $tanggalObj->format('Y-m-d') !== $tanggal ||
        $tanggal < date('Y-m-d')
    ) {
        $errors[] = 'Pilih tanggal hari ini atau tanggal mendatang.';
    }

    /* Validasi ruangan berdasarkan data database */
    $ruanganValid = false;

    if ($ruangan !== '') {
        $stmtRuanganValid = $pdo->prepare("
            SELECT id
            FROM ruangan
            WHERE nama_ruangan = :nama
              AND status = 'aktif'
            LIMIT 1
        ");

        $stmtRuanganValid->execute([
            ':nama' => $ruangan
        ]);

        $ruanganValid = (bool) $stmtRuanganValid->fetchColumn();
    }

    if (!$ruanganValid) {
        $errors[] = 'Pilih ruangan aktif yang tersedia pada daftar.';
    }

    /* Validasi format dan rentang jam */
    $formatJam = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    if (
        !preg_match($formatJam, $jam_mulai) ||
        !preg_match($formatJam, $jam_selesai)
    ) {
        $errors[] = 'Format jam mulai atau selesai tidak valid.';
    } else {
        if ($jam_mulai < '07:15' || $jam_mulai > '16:00') {
            $errors[] = 'Jam mulai harus antara 07.15 dan 16.00.';
        }

        if ($jam_selesai > '16:00') {
            $errors[] = 'Jam selesai tidak boleh melewati 16.00.';
        }

        if ($jam_selesai <= $jam_mulai) {
            $errors[] = 'Jam selesai harus setelah jam mulai.';
        }
    }

    /* Cek bentrok hanya jika input dasar valid */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /*
             * Bentrok ruangan:
             * tanggal dan ruangan sama serta waktu tumpang tindih.
             * Pengajuan yang ditolak atau sudah dikembalikan dikecualikan.
             */
            $stmtBentrokRuangan = $pdo->prepare("
                SELECT COUNT(*)
                FROM peminjaman p
                WHERE p.tgl = :tanggal
                  AND p.ruangan = :ruangan
                  AND p.jam_mulai < :jam_selesai
                  AND p.jam_selesai > :jam_mulai
                  AND p.status NOT IN ('ditolak', 'dikembalikan')
            ");

            $stmtBentrokRuangan->execute([
                ':tanggal' => $tanggal,
                ':ruangan' => $ruangan,
                ':jam_mulai' => $jam_mulai,
                ':jam_selesai' => $jam_selesai
            ]);

            $jumlahBentrokRuangan =
                (int) $stmtBentrokRuangan->fetchColumn();

            if ($jumlahBentrokRuangan > 0) {
                $errors[] =
                    'Ruangan sudah memiliki jadwal pada waktu tersebut. '
                    . 'Pilih ruangan atau waktu lain.';
            }

            /*
             * Cek jumlah peminjaman alat yang waktunya bertumpang tindih.
             * Perhitungan ini merupakan pemeriksaan awal berdasarkan
             * baris peminjaman_item yang masih aktif.
             */
            $stmtPemakaianAlat = $pdo->prepare("
                SELECT COUNT(*)
                FROM peminjaman p
                INNER JOIN peminjaman_item pi
                    ON pi.peminjaman_id = p.id
                WHERE pi.alat_id = :alat_id
                  AND p.tgl = :tanggal
                  AND p.jam_mulai < :jam_selesai
                  AND p.jam_selesai > :jam_mulai
                  AND p.status NOT IN ('ditolak', 'dikembalikan')
            ");

            $stmtPemakaianAlat->execute([
                ':alat_id' => (int) $alat['id'],
                ':tanggal' => $tanggal,
                ':jam_mulai' => $jam_mulai,
                ':jam_selesai' => $jam_selesai
            ]);

            $jumlahPemakaianAlat =
                (int) $stmtPemakaianAlat->fetchColumn();

            if ($jumlahPemakaianAlat >= (int) $alat['stok_total']) {
                $errors[] =
                    'Stok alat tidak mencukupi pada jadwal tersebut. '
                    . 'Pilih waktu lain.';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Pemeriksaan jadwal gagal: ' . $e->getMessage());

            $errors[] =
                'Jadwal belum bisa diperiksa. Silakan coba kembali.';
        }
    }


        /* Simpan draft hanya jika lolos pemeriksaan */
        if (!$errors) {
            $_SESSION['peminjaman_draft'] = [
                'alat_id' => (int) $alat['id'],
                'tanggal' => $tanggal,
                'ruangan' => $ruangan,
                'jam_mulai' => $jam_mulai,
                'jam_selesai' => $jam_selesai
            ];

            $_SESSION['jadwal_lolos'] = true;
            $success = true;
        }
    }

$page_title = 'Jadwal Peminjaman';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content borrowing-page borrowing-schedule-page">

        <div class="page-header borrowing-header">
            <div>
                <div class="page-label">PEMINJAMAN ALAT</div>
                <h1>Ajukan Peminjaman Alat</h1>
                <p>Tentukan jadwal penggunaan alat dan ruangan.</p>
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

            <div class="step-line"></div>

            <div class="borrowing-step active">
                <div class="step-circle">2</div>
                <span>Jadwal Peminjaman</span>
            </div>

            <div class="step-line"></div>

            <div class="borrowing-step">
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
                    Jadwal belum tersedia
                </strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>


        <?php if (!empty($_SESSION['jadwal_lolos'])): ?>
            <div id="jadwalPopup" class="jadwal-popup-overlay">
                <div class="jadwal-popup jadwal-popup-success">
                    <div class="jadwal-popup-icon">✓</div>
                    <h3>Jadwal tersedia!</h3>
                    <p>
                        Jadwal lolos pemeriksaan awal dan tidak ditemukan bentrok
                        pada ruangan serta waktu yang dipilih.
                    </p>

                    <button type="button" id="btnMengerti" class="btn-popup-success">
                        Mengerti
                    </button>

                    <script>
                    document.getElementById('btnMengerti').addEventListener('click', function () {
                        window.location.href =
                            '<?= BASE_URL ?>/mahasiswa/keperluan.php?alat_id=<?= (int)($_GET["alat_id"] ?? 0) ?>';
                    });
                    </script>
                </div>
            </div>
            <?php unset($_SESSION['jadwal_lolos']); ?>
        <?php endif; ?>

        <section class="borrowing-layout borrowing-schedule-layout">

            <!-- Kartu alat -->
            <article class="borrowing-panel selected-equipment">
                <h2>Alat yang dipilih</h2>

                <div class="borrowing-equipment-image">
                    <i data-lucide="package"></i>
                </div>

                <div class="borrowing-equipment-name">
                    <h3><?= e($alat['nama']) ?></h3>
                    <span><?= e($alat['kategori']) ?></span>
                </div>

                <div class="borrowing-equipment-footer">
                    <span>
                        Stok tersedia:
                        <strong><?= (int) $alat['stok_total'] ?> unit</strong>
                    </span>
                </div>

                <div class="borrowing-left-details">
                    <div>
                        <h3>Deskripsi</h3>
                        <p>
                            <?= e(
                                $alat['deskripsi']
                                ?: 'Belum ada deskripsi alat.'
                            ) ?>
                        </p>
                    </div>

                    <div>
                        <h3>Spesifikasi</h3>
                        <p>
                            <?= nl2br(e(
                                $alat['spesifikasi']
                                ?: 'Belum ada spesifikasi.'
                            )) ?>
                        </p>
                    </div>
                </div>
            </article>

            <!-- Kartu jadwal -->
            <article class="borrowing-panel borrowing-details">

                <div class="schedule-panel-heading">
                    <h2>Jadwal penggunaan</h2>
                </div>

                <form
                    method="POST"
                    action="<?= BASE_URL ?>/mahasiswa/jadwal.php?alat_id=<?= (int) $alat['id'] ?>"
                    id="jadwalForm"
                    class="schedule-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <div class="schedule-field">
                        <label for="tanggal" class="form-label">
                            Tanggal peminjaman
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="tanggal"
                            name="tanggal"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= e($tanggal) ?>"
                            required
                        >
                    </div>

                    <div class="schedule-field">
                        <label for="ruangan" class="form-label">
                            Ruangan
                        </label>

                        <select
                            name="ruangan"
                            id="ruangan"
                            class="form-select"
                            required
                        >
                            <option value="">Pilih ruangan</option>

                            <?php foreach ($daftar_ruangan as $item): ?>
                                <option
                                    value="<?= e($item['nama_ruangan']) ?>"
                                    <?= $ruangan === $item['nama_ruangan']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($item['nama_ruangan']) ?>
                                    <?php if (!empty($item['lokasi'])): ?>
                                        (<?= e($item['lokasi']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="schedule-time-grid">
                        <div class="schedule-field">
                            <label for="jam_mulai" class="form-label">
                                Jam mulai
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                id="jam_mulai"
                                name="jam_mulai"
                                min="07:15"
                                max="16:00"
                                value="<?= e($jam_mulai) ?>"
                                required
                            >
                        </div>

                        <div class="schedule-field">
                            <label for="jam_selesai" class="form-label">
                                Jam selesai
                            </label>

                            <input
                                type="time"
                                class="form-control"
                                id="jam_selesai"
                                name="jam_selesai"
                                min="07:15"
                                max="16:00"
                                value="<?= e($jam_selesai) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="schedule-time-notice">
                        <i data-lucide="clock"></i>

                        <div>
                            <strong>Ketentuan waktu</strong>

                            <p>Jam penggunaan: 07.15–16.00.</p>

                            <p>
                                Pengembalian paling lambat 17.00.
                                Toleransi keterlambatan sampai 17.15.
                            </p>
                        </div>
                    </div>
                </form>
            </article>

        </section>

        <!-- Tombol di luar kartu -->
        <div class="borrowing-page-actions">
            <a
                href="<?= BASE_URL ?>/mahasiswa/ajukan.php?alat_id=<?= (int) $alat['id'] ?>"
                class="btn btn-outline-secondary borrowing-back-button"
            >
                Kembali
            </a>

            <button
                type="submit"
                form="jadwalForm"
                class="btn btn-primary borrowing-next-button"
            >
                Lanjut ke Keperluan
                <i data-lucide="arrow-right"></i>
            </button>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('jadwalForm');
    const mulai = document.getElementById('jam_mulai');
    const selesai = document.getElementById('jam_selesai');

    function validasiWaktu() {
        mulai.setCustomValidity('');
        selesai.setCustomValidity('');

        if (!mulai.value || !selesai.value) {
            return;
        }

        if (mulai.value < '07:15' || mulai.value > '16:00') {
            mulai.setCustomValidity(
                'Jam mulai harus antara 07.15 dan 16.00.'
            );
            return;
        }

        if (selesai.value > '16:00') {
            selesai.setCustomValidity(
                'Jam selesai tidak boleh melewati 16.00.'
            );
            return;
        }

        if (selesai.value <= mulai.value) {
            selesai.setCustomValidity(
                'Jam selesai harus setelah jam mulai.'
            );
        }
    }

    mulai.addEventListener('change', validasiWaktu);
    selesai.addEventListener('change', validasiWaktu);

    form.addEventListener('submit', function (event) {
        validasiWaktu();

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>