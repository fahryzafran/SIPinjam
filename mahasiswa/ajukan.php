
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

$stmt = $pdo->prepare("
    SELECT
        alat.id,
        alat.nama,
        alat.deskripsi,
        alat.spesifikasi,
        alat.stok_total,
        kategori_alat.nama_kategori AS kategori,
        kategori_alat.max_per_kelas
    FROM alat
    INNER JOIN kategori_alat
        ON kategori_alat.id = alat.kategori_id
    WHERE alat.id = :id
    LIMIT 1
");

$stmt->execute([':id' => $alat_id]);
$alat = $stmt->fetch();

if (!$alat) {
    http_response_code(404);
    exit('Alat tidak ditemukan.');
}

$spesifikasi = preg_split(
    "/\r\n|\r|\n/",
    $alat['spesifikasi'] ?? ''
);

$page_title = 'Ajukan Peminjaman';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">

    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content borrowing-page">

        <div class="page-header borrowing-header">
            <div>
                <div class="page-label">PEMINJAMAN ALAT</div>
                <h1>Ajukan Peminjaman Alat</h1>
                <p>
                    Silakan lengkapi beberapa langkah untuk meminjam alat.
                </p>
            </div>
        </div>

        <!-- STEPPER -->
        <section class="borrowing-stepper">

            <div class="borrowing-step active">
                <div class="step-circle">1</div>
                <span>Informasi Alat</span>
            </div>

            <div class="step-line"></div>

            <div class="borrowing-step">
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

        <!-- INFORMASI ALAT -->
        <section class="borrowing-layout">

            <!-- KARTU ALAT -->
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
                        <strong><?= e($alat['stok_total']) ?> unit</strong>
                    </span>

                    <?php if ((int) $alat['stok_total'] > 0): ?>
                        <span class="stock-badge">
                            <i data-lucide="check-circle-2"></i>
                            Tersedia
                        </span>
                    <?php else: ?>
                        <span class="stock-badge unavailable">
                            Stok habis
                        </span>
                    <?php endif; ?>
                </div>

                <div class="borrowing-left-details">

                    <div class="borrowing-section">

                        <div class="borrowing-section-heading">
                            <h2>Detail Alat</h2>
                            <span class="standard-badge">INFORMASI ALAT</span>
                        </div>

                        <p class="borrowing-description">
                            <?= nl2br(e(
                                $alat['deskripsi'] ?: 'Belum ada deskripsi alat.'
                            )) ?>
                        </p>

                    </div>

                    <div class="borrowing-section">

                        <h2>Spesifikasi</h2>

                        <div class="borrowing-spec-list">

                            <?php
                            $has_specification = false;

                            foreach ($spesifikasi as $item):
                                $item = trim($item);

                                if ($item === '') {
                                    continue;
                                }

                                $has_specification = true;
                                $parts = explode(':', $item, 2);
                                $label = trim($parts[0]);
                                $value = isset($parts[1]) ? trim($parts[1]) : '';
                            ?>

                                <div class="borrowing-spec-row">
                                    <span><?= e($label) ?></span>
                                    <strong><?= e($value) ?></strong>
                                </div>

                            <?php endforeach; ?>

                            <?php if (!$has_specification): ?>
                                <p class="borrowing-empty-spec">
                                    Belum ada spesifikasi untuk alat ini.
                                </p>
                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </article>

            <!-- DETAIL PEMINJAMAN -->
            <article class="borrowing-panel borrowing-details">

                <div class="borrowing-section">

                    <h2>Jumlah unit</h2>

                    <div class="borrowing-quantity-row">

                        <div class="quantity-value">1</div>

                        <p>
                            Jumlah unit yang diajukan.
                            <?php if ((int) $alat['max_per_kelas'] > 0): ?>
                                Maksimal
                                <?= e($alat['max_per_kelas']) ?>
                                per kelas sesuai kuota kategori.
                            <?php endif; ?>
                        </p>

                    </div>

                </div>

                <?php if ((int) $alat['stok_total'] > 0): ?>

                <div class="borrowing-section borrowing-standard">

                    <div class="borrowing-section-heading">
                        <h2>Detail Peminjaman</h2>
                        <span class="standard-badge">KELENGKAPAN STANDAR</span>
                    </div>

                    <div class="standard-equipment-list">

                        <div class="standard-equipment-item">
                            <i data-lucide="check-circle-2"></i>
                            <span>1 pcs LCD Proyektor</span>
                        </div>

                        <div class="standard-equipment-item">
                            <i data-lucide="check-circle-2"></i>
                            <span>1 pcs Kabel Power</span>
                        </div>

                        <div class="standard-equipment-item">
                            <i data-lucide="check-circle-2"></i>
                            <span>1 pcs Kabel HDMI</span>
                        </div>

                        <div class="standard-equipment-item">
                            <i data-lucide="check-circle-2"></i>
                            <span>1 pcs Kabel panjang 5 M 5 Lubang</span>
                        </div>

                    </div>

                </div>

                    <div class="borrowing-section borrowing-extras">

                        <h2>Kelengkapan Tambahan</h2>

                        <p class="extras-description">
                            Pilih aksesori tambahan jika diperlukan.
                        </p>

                        <label class="extra-option">
                            <input
                                type="checkbox"
                                name="kelengkapan[]"
                                value="Adaptor HDMI to Type-C"
                            >

                            <span class="extra-option-text">
                                <i data-lucide="plug"></i>
                                Adaptor HDMI to Type-C
                                <small>Opsional</small>
                            </span>
                        </label>

                        <label class="extra-option">
                            <input
                                type="checkbox"
                                name="kelengkapan[]"
                                value="Pointer / Wireless Presenter"
                            >

                            <span class="extra-option-text">
                                <i data-lucide="presentation"></i>
                                Pointer / Wireless Presenter
                                <small>Opsional</small>
                            </span>
                        </label>

                    </div>


                    <div class="borrowing-info-note">
                        <i data-lucide="info"></i>
                        <p>
                            Pastikan alat yang dipilih sudah sesuai.
                            Jadwal dan keperluan peminjaman akan diisi
                            pada langkah berikutnya.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="borrowing-info-note warning">
                        <i data-lucide="alert-circle"></i>
                        <p>
                            Stok alat sedang habis. Pengajuan belum
                            dapat dilanjutkan.
                        </p>
                    </div>

                <?php endif; ?>

            </article>

        </section>

        <!-- NAVIGASI -->
        <div class="borrowing-actions">

            <a
                href="<?= BASE_URL ?>/mahasiswa/detail_alat.php?id=<?= e($alat['id']) ?>"
                class="borrowing-back-button"
            >
                Kembali
            </a>

            <?php if ((int) $alat['stok_total'] > 0): ?>

            <a
                href="<?= BASE_URL ?>/mahasiswa/jadwal.php?alat_id=<?= (int) $alat['id'] ?>"
                class="btn btn-primary borrowing-next-button"
            >
                Lanjut ke Jadwal Peminjaman
            </a>

            <?php else: ?>

                <button
                    type="button"
                    class="borrowing-next-button"
                    disabled
                >
                    Stok Habis
                </button>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once "../includes/footer.php"; ?>
