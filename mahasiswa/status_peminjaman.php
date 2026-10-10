<?php
/*
 * Endpoint JSON untuk pembaruan status realtime.
 * Dipanggil berkala oleh detail_peminjaman.php (tanpa refresh halaman).
 */

require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/peminjaman_status.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (
    !isset($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'mahasiswa'
) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesi berakhir. Silakan login ulang.']);
    exit;
}

$user_id = (int) $_SESSION['user']['id'];

/* Lepas kunci sesi agar polling tidak menahan halaman lain */
session_write_close();

$peminjaman_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pengajuan tidak valid.']);
    exit;
}

try {
    $peminjaman = ambil_peminjaman($pdo, $peminjaman_id, $user_id);
} catch (PDOException $e) {
    error_log('Status peminjaman gagal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Status belum bisa diambil.']);
    exit;
}

if (!$peminjaman) {
    http_response_code(404);
    echo json_encode(['error' => 'Pengajuan tidak ditemukan.']);
    exit;
}

echo json_encode(susun_status_peminjaman($peminjaman), JSON_UNESCAPED_UNICODE);
