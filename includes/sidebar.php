<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-mark">
            S
        </div>

        <div>
            <div class="brand-name">SIPAKA</div>

            <div class="brand-subtitle">
                Sistem Peminjaman<br>
                Alat Kampus
            </div>
        </div>

        <button class="sidebar-toggle" type="button">
            ‹
        </button>

    </div>


    <nav class="sidebar-nav">

        <a
            href="<?= BASE_URL ?>/mahasiswa/dashboard.php"
            class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-icon">⌂</span>
            <span>Dashboard</span>
        </a>


        <a
            href="<?= BASE_URL ?>/mahasiswa/katalog.php"
            class="sidebar-link <?= $current_page === 'katalog.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-icon">▦</span>
            <span>Katalog Alat</span>
        </a>


        <a
            href="<?= BASE_URL ?>/mahasiswa/riwayat.php"
            class="sidebar-link <?= $current_page === 'riwayat.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-icon">◷</span>
            <span>Riwayat Peminjaman</span>
        </a>


        <a
            href="<?= BASE_URL ?>/mahasiswa/saran.php"
            class="sidebar-link <?= $current_page === 'saran.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-icon">▢</span>
            <span>Saran Alat</span>
        </a>

    </nav>

</aside>