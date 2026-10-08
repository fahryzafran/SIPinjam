<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login()
{
    if (!isset($_SESSION['user'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function require_role($role)
{
    require_login();

    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}