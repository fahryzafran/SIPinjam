<?php

require_once "../config/app.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";

require_role('mahasiswa');

$page_title = 'Katalog Alat';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">

    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content">

        <div class="page-header">

            <div>
                <div class="page-label">KATALOG ALAT</div>

                <h1>
                    Katalog Alat
                </h1>

                <p>
                    Pilih alat yang ingin Anda pinjam.
                </p>
            </div>

        </div>

    </main>

</div>

<?php
require_once "../includes/footer.php";
?>