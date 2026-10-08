<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";

require_role('mahasiswa');

$page_title = 'Dashboard';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<main class="main-content">

    <div class="page-header">

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

</main>

<?php
require_once "../includes/footer.php";
?>