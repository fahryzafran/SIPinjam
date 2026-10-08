<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../config/database.php";

require_role('mahasiswa');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    die('ID alat tidak valid.');
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
");

$stmt->execute([
    ':id' => $id
]);

$alat = $stmt->fetch();

if (!$alat) {
    http_response_code(404);
    die('Alat tidak ditemukan.');
}

$page_title = $alat['nama'];

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">

    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <div class="page-label">
                    DETAIL ALAT
                </div>

                <h1>
                    <?= e($alat['nama']) ?>
                </h1>

                <p>
                    Informasi alat yang tersedia untuk dipinjam.
                </p>

            </div>

        </div>

        <div class="equipment-detail-card">

            <div class="equipment-detail-image">

                <i data-lucide="package"></i>

            </div>

            <div class="equipment-detail-content">

                <div class="equipment-category">
                    <?= e($alat['kategori']) ?>
                </div>

                <h2>
                    <?= e($alat['nama']) ?>
                </h2>

                <div class="equipment-detail-info">

                    <div>
                        <span>Stok tersedia</span>
                        <strong>
                            <?= e($alat['stok_total']) ?> unit
                        </strong>
                    </div>

                    <div>
                        <span>Kuota per kelas</span>
                        <strong>
                            <?= e($alat['max_per_kelas']) ?> unit
                        </strong>
                    </div>

                </div>

                <div class="equipment-description">

                    <h3>Deskripsi</h3>

                    <p>
                        <?= nl2br(e($alat['deskripsi'])) ?>
                    </p>

                </div>

                <div class="equipment-specification">

                <h3>Spesifikasi</h3>

                <div class="specification-list">

                    <?php
                    $spesifikasi = preg_split(
                        "/\r\n|\r|\n/",
                        $alat['spesifikasi'] ?? ''
                    );
                    ?>

                    <?php foreach ($spesifikasi as $item): ?>

                        <?php
                        $item = trim($item);

                        if ($item === '') {
                            continue;
                        }

                        $parts = explode(':', $item, 2);

                        $label = trim($parts[0]);
                        $value = isset($parts[1])
                            ? trim($parts[1])
                            : '';
                        ?>

                        <div class="specification-row">

                            <span>
                                <?= e($label) ?>
                            </span>

                            <strong>
                                <?= e($value) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

                <div class="equipment-detail-actions">

                    <a
                        href="<?= BASE_URL ?>/mahasiswa/katalog.php"
                        class="btn-secondary"
                    >
                        Kembali
                    </a>

                    <a
                        href="<?= BASE_URL ?>/mahasiswa/ajukan.php?alat_id=<?= e($alat['id']) ?>"
                        class="btn-primary"
                    >
                        Ajukan Peminjaman
                    </a>

                </div>

            </div>

        </div>

    </main>

</div>

<?php
require_once "../includes/footer.php";
?>