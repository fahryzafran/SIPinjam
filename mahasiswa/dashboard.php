<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";

require_role('mahasiswa');

$page_title = 'Dashboard';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">

    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content">


    <!-- Header Dashboard -->
    <div class="dashboard-header">

        <div>
            <div class="page-label">DASHBOARD</div>

            <h1>
                Halo, <?= e($_SESSION['user']['nama']) ?>!
            </h1>

            <p>
                Berikut ringkasan aktivitas peminjaman alat Anda.
            </p>
        </div>

    </div>


    <!-- Informasi Kelas -->
    <section class="class-summary">

        <div class="class-info">

            <div class="class-icon">
                <i data-lucide="graduation-cap"></i>
            </div>

            <div>
                <div class="summary-label">Kelas Anda</div>
                <div class="summary-value">TEKOM A 25</div>
            </div>

        </div>


        <div class="quota-info">

            <div class="summary-label">
                Sisa Kuota Kelas
            </div>

            <div class="quota-items">

                <div class="quota-item">
                    <span>Proyektor</span>
                    <strong>1 / 1</strong>
                </div>

                <div class="quota-item">
                    <span>Alat Praktikum</span>
                    <strong>1 / 1</strong>
                </div>

            </div>

        </div>

    </section>


    <!-- Status Cards -->
    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon stat-blue">
                <i data-lucide="file-text"></i>
            </div>

            <div class="stat-content">
                <span>Pengajuan Aktif</span>
                <strong>0</strong>
            </div>

            <span class="stat-arrow">›</span>

        </div>


        <div class="stat-card">

            <div class="stat-icon stat-yellow">
                <i data-lucide="clock-3"></i>
            </div>

            <div class="stat-content">
                <span>Menunggu Persetujuan</span>
                <strong>0</strong>
            </div>

            <span class="stat-arrow">›</span>

        </div>


        <div class="stat-card">

            <div class="stat-icon stat-green">
                <i data-lucide="package-check"></i>
            </div>

            <div class="stat-content">
                <span>Siap Diambil</span>
                <strong>0</strong>
            </div>

            <span class="stat-arrow">›</span>

        </div>


        <div class="stat-card">

            <div class="stat-icon stat-gray">
                <i data-lucide="history"></i>
            </div>

            <div class="stat-content">
                <span>Riwayat Peminjaman</span>
                <strong>0</strong>
            </div>

            <span class="stat-arrow">›</span>

        </div>

    </section>


    <!-- Dashboard Body -->
    <section class="dashboard-grid">

        <!-- Pengajuan Terbaru -->
        <div class="dashboard-card recent-loans">

            <div class="card-header">

                <div>
                    <h2>Pengajuan Terbaru</h2>
                </div>

                <a href="<?= BASE_URL ?>/mahasiswa/riwayat.php">
                    Lihat Semua →
                </a>

            </div>


            <div class="table-wrapper">

                <table class="loan-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Alat</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Keperluan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr>
                            <td colspan="7" class="empty-state">
                                Belum ada pengajuan peminjaman.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- Alat Populer -->
        <div class="dashboard-card popular-equipment">

            <div class="card-header">

                <div>
                    <h2>Alat Populer</h2>
                </div>

                <a href="<?= BASE_URL ?>/mahasiswa/katalog.php">
                    Lihat Semua →
                </a>

            </div>


            <div class="equipment-list">

                <div class="equipment-empty">
                    <div class="equipment-empty-icon">
                        <i data-lucide="package"></i>
                    </div>

                    <p>
                        Belum ada data alat.
                    </p>

                    <a
                        href="<?= BASE_URL ?>/mahasiswa/katalog.php"
                        class="equipment-button"
                    >
                        Lihat Katalog
                    </a>
                </div>

            </div>

        </div>

    </section>

</main>

<?php
require_once "../includes/footer.php";
?>