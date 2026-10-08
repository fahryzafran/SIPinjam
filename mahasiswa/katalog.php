<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../config/database.php";

require_role('mahasiswa');

$kategori_filter = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'nama_asc';
$status_filter = $_GET['status'] ?? '';

/*
|--------------------------------------------------------------------------
| Ambil Data Alat
|--------------------------------------------------------------------------
*/

$sql = "
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
";

$params = [];

$conditions = [];

if ($kategori_filter !== '') {
    $conditions[] = "alat.kategori_id = :kategori";
    $params[':kategori'] = $kategori_filter;
}

if ($status_filter === 'tersedia') {
    $conditions[] = "alat.stok_total > 0";
}

if ($status_filter === 'habis') {
    $conditions[] = "alat.stok_total = 0";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

switch ($sort) {

    case 'nama_desc':
        $sql .= " ORDER BY alat.nama DESC";
        break;

    case 'stok_desc':
        $sql .= " ORDER BY alat.stok_total DESC";
        break;

    default:
        $sql .= " ORDER BY alat.nama ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$alat_list = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Ambil Data Kategori
|--------------------------------------------------------------------------
*/

$kategori_stmt = $pdo->query("
    SELECT
        id,
        nama_kategori
    FROM kategori_alat
    ORDER BY nama_kategori ASC
");

$kategori_list = $kategori_stmt->fetchAll();


$page_title = 'Katalog Alat';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">

    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content">

        <!-- =========================================
             HEADER KATALOG
             ========================================= -->

        <div class="page-header">

            <div>

                <div class="page-label">
                    KATALOG ALAT
                </div>

                <h1>
                    Katalog Alat
                </h1>

                <p>
                    Lihat dan pilih alat yang tersedia untuk dipinjam.
                </p>

            </div>

        </div>


        <!-- =========================================
             SEARCH & FILTER
             ========================================= -->

        <div class="catalog-toolbar">

            <!-- Search -->
            <div class="catalog-search">

                <i data-lucide="search"></i>

            <input
                type="text"
                id = "searchAlat"
                placeholder="Cari nama alat atau kode unit..."
            >

            </div>


            <!-- Filter Kategori -->
            <select
                class="catalog-filter"
                name="kategori"
            >

                <option value="">
                    Semua Kategori
                </option>

                <?php foreach ($kategori_list as $kategori): ?>

                    <option
                        value="<?= e($kategori['id']) ?>"
                        <?= $kategori_filter == $kategori['id'] ? 'selected' : '' ?>
                    >
                        <?= e($kategori['nama_kategori']) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <!-- Filter Status -->
            <select
                class="catalog-filter"
                name="status"
            >
                <option value="">
                    Semua Status
                </option>

                <option
                    value="tersedia"
                    <?= $status_filter === 'tersedia' ? 'selected' : '' ?>
                >
                    Tersedia
                </option>

                <option
                    value="habis"
                    <?= $status_filter === 'habis' ? 'selected' : '' ?>
                >
                    Stok Habis
                </option>
            </select>

            <!-- Sorting -->
            <div class="dropdown">

                <button
                    class="catalog-sort dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <i data-lucide="arrow-up-down"></i>

                    <span>

                        <small>
                            Urutkan
                        </small>

                        <strong>
                            <?php
                            if ($sort === 'nama_desc') {
                                echo 'Nama Z - A';
                            } elseif ($sort === 'stok_desc') {
                                echo 'Stok Terbanyak';
                            } else {
                                echo 'Nama A - Z';
                            }
                            ?>
                        </strong>

                    </span>

                </button>

                <ul class="dropdown-menu dropdown-menu-end catalog-sort-menu">

                    <li>
                        <a
                            class="dropdown-item"
                            href="?kategori=<?= e($kategori_filter) ?>&sort=nama_asc"
                        >
                            Nama A - Z
                        </a>
                    </li>

                    <li>
                        <a
                            class="dropdown-item"
                            href="?kategori=<?= e($kategori_filter) ?>&sort=nama_desc"
                        >
                            Nama Z - A
                        </a>
                    </li>

                    <li>
                        <a
                            class="dropdown-item"
                            href="?kategori=<?= e($kategori_filter) ?>&sort=stok_desc"
                        >
                            Stok Terbanyak
                        </a>
                    </li>

                </ul>

            </div>

        </div>

        <!-- =========================================
             DAFTAR ALAT
             ========================================= -->

        <div class="catalog-grid">
            <div id="searchEmptyState" class="catalog-empty" style="display: none;">

                <i data-lucide="search-x"></i>

                <h3>
                    Alat tidak ditemukan
                </h3>

                <p>
                    Tidak ada alat yang sesuai dengan pencarian kamu.
                </p>

            </div>

            <?php if (empty($alat_list)): ?>

                <!-- Empty State -->

                <div class="catalog-empty">

                    <i data-lucide="package-open"></i>

                    <h3>
                        Belum ada alat
                    </h3>

                    <p>
                        Belum ada data alat yang tersedia di katalog.
                    </p>

                </div>

            <?php else: ?>

                <?php foreach ($alat_list as $alat): ?>

                    <!-- Equipment Card -->

                    <div 
                    class="equipment-card"
                    data-name="<?= e(strtolower($alat['nama'])) ?>"

                        <!-- Image / Icon -->

                        <div class="equipment-image">

                            <i data-lucide="package"></i>

                        </div>


                        <!-- Equipment Body -->

                        <div class="equipment-body">

                            <!-- Category -->

                            <div class="equipment-category">

                                <?= e($alat['kategori']) ?>

                            </div>


                            <!-- Name -->

                            <h3 class="equipment-name">

                                <?= e($alat['nama']) ?>

                            </h3>


                            <!-- Stock -->

                            <div class="equipment-stock">

                                <span>
                                    Stok tersedia
                                </span>

                                <strong>
                                    <?= e($alat['stok_total']) ?> unit
                                </strong>

                            </div>


                            <!-- Detail Button -->

                            <a
                                href="<?= BASE_URL ?>/mahasiswa/detail_alat.php?id=<?= e($alat['id']) ?>"
                                class="equipment-detail-button"
                            >
                                Lihat Detail
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</div>


<?php

require_once "../includes/footer.php";

?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('searchAlat');
    const equipmentCards = document.querySelectorAll('.equipment-card');
    const emptyState = document.getElementById('searchEmptyState');

    searchInput.addEventListener('input', function () {

        const keyword = this.value.toLowerCase().trim();

        let visibleCount = 0;

        equipmentCards.forEach(function (card) {

            const name = card.dataset.name;

            if (name.includes(keyword)) {

                card.style.display = '';
                visibleCount++;

            } else {

                card.style.display = 'none';

            }

        });

        if (keyword !== '' && visibleCount === 0) {

            emptyState.style.display = '';

        } else {

            emptyState.style.display = 'none';

        }

    });

});
</script>