<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? APP_NAME;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- CSS SIPinjam -->
    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/assets/css/style.css"
    >

    <!-- Animasi masuk setelah login (penanda dari login.js) -->
    <script>
        try {
            if (sessionStorage.getItem('sipaka_baru_masuk')) {
                sessionStorage.removeItem('sipaka_baru_masuk');
                document.documentElement.classList.add('baru-masuk');
            }
        } catch (e) {}
    </script>
</head>

<body>