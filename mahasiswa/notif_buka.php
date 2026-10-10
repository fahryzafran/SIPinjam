<?php
/*
 * Membuka satu notifikasi: tandai dibaca lalu arahkan ke link-nya.
 * Hanya notifikasi milik user yang login yang bisa dibuka.
 */

require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";

require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT link FROM notifikasi WHERE id = :id AND user_id = :user_id LIMIT 1");
$stmt->execute([':id' => (int) $id, ':user_id' => $user_id]);
$link = $stmt->fetchColumn();

if ($link === false) {
    header("Location: " . BASE_URL . "/mahasiswa/notifikasi.php");
    exit;
}

$pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = :id AND user_id = :user_id")
    ->execute([':id' => (int) $id, ':user_id' => $user_id]);

/* Hanya link internal (diawali satu "/") yang diikuti */
$link = (string) $link;

if ($link !== '' && $link[0] === '/' && strpos($link, '//') !== 0 && strpos($link, '\\') === false) {
    header("Location: " . BASE_URL . $link);
} else {
    header("Location: " . BASE_URL . "/mahasiswa/notifikasi.php");
}
exit;
