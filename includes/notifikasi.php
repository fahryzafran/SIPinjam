<?php

/*
 * Helper notifikasi untuk dropdown topbar dan halaman notifikasi.
 * Tabel notifikasi hanya punya kolom pesan & link, jadi judul, jenis,
 * ikon, dan warna ditebak dari isi pesan dan alamat link.
 */

function info_notifikasi(array $n): array
{
    $pesan = mb_strtolower((string) $n['pesan']);
    $link = (string) ($n['link'] ?? '');

    /* Jenis untuk tab filter: utamakan alamat link, lalu kata utuh di pesan */
    if (preg_match('/\bpengingat\b/u', $pesan)) {
        $jenis = 'pengingat';
    } elseif (strpos($link, 'saran') !== false) {
        $jenis = 'saran';
    } elseif (strpos($link, 'peminjaman') !== false) {
        $jenis = 'peminjaman';
    } elseif (preg_match('/\b(saran|usulan)\b/u', $pesan)) {
        $jenis = 'saran';
    } else {
        $jenis = 'peminjaman';
    }

    /* Judul, ikon (lucide), dan warna berdasarkan kata kunci */
    $aturan = [
        ['kata' => 'siap diambil', 'judul' => 'Alat siap diambil', 'ikon' => 'package-check', 'warna' => 'hijau'],
        ['kata' => 'ditolak', 'judul' => $jenis === 'saran' ? 'Saran alat ditolak' : 'Pengajuan ditolak', 'ikon' => 'x-circle', 'warna' => 'merah'],
        ['kata' => 'diterima', 'judul' => 'Saran alat diterima', 'ikon' => 'message-square-check', 'warna' => 'hijau'],
        ['kata' => 'disetujui', 'judul' => 'Disetujui dosen', 'ikon' => 'user-check', 'warna' => 'primary'],
        ['kata' => 'menyetujui', 'judul' => 'Disetujui dosen', 'ikon' => 'user-check', 'warna' => 'primary'],
        ['kata' => 'pengingat', 'judul' => 'Pengingat pengembalian', 'ikon' => 'alarm-clock', 'warna' => 'kuning'],
        ['kata' => 'dikembalikan', 'judul' => 'Alat dikembalikan', 'ikon' => 'circle-check', 'warna' => 'hijau'],
        ['kata' => 'diproses', 'judul' => 'Saran alat diproses', 'ikon' => 'loader', 'warna' => 'primary'],
        ['kata' => 'pengajuan peminjaman baru', 'judul' => 'Pengajuan baru', 'ikon' => 'inbox', 'warna' => 'primary'],
        ['kata' => 'saran alat dari', 'judul' => 'Saran alat baru', 'ikon' => 'message-square-plus', 'warna' => 'primary']
    ];

    foreach ($aturan as $a) {
        if (strpos($pesan, $a['kata']) !== false) {
            return [
                'judul' => $a['judul'],
                'jenis' => $jenis,
                'ikon' => $a['ikon'],
                'warna' => $a['warna']
            ];
        }
    }

    return [
        'judul' => $jenis === 'saran' ? 'Saran alat' : 'Info peminjaman',
        'jenis' => $jenis,
        'ikon' => 'bell',
        'warna' => 'netral'
    ];
}

/* Contoh: "14.32", "Kemarin, 15.20", "Senin, 08.12", "28 Sep, 09.00" */
function waktu_notifikasi(?string $nilai): string
{
    if (!$nilai || ($t = strtotime($nilai)) === false) {
        return '';
    }

    $hari_ini = strtotime('today');
    $jam = date('H.i', $t);

    if ($t >= $hari_ini) {
        return $jam;
    }

    if ($t >= $hari_ini - 86400) {
        return 'Kemarin, ' . $jam;
    }

    if ($t >= $hari_ini - 6 * 86400) {
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return $hari[(int) date('w', $t)] . ', ' . $jam;
    }

    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ', ' . $jam;
}

/* Kelompok tanggal untuk halaman notifikasi */
function grup_notifikasi(?string $nilai): string
{
    $t = $nilai ? strtotime($nilai) : false;

    if ($t === false) {
        return 'Lainnya';
    }

    $hari_ini = strtotime('today');

    if ($t >= $hari_ini) {
        return 'Hari ini';
    }

    if ($t >= $hari_ini - 86400) {
        return 'Kemarin';
    }

    if ($t >= $hari_ini - 6 * 86400) {
        return 'Minggu ini';
    }

    return 'Lebih lama';
}

/* Link aman untuk membuka notifikasi (sekaligus menandai dibaca) */
function url_buka_notifikasi(int $id): string
{
    return BASE_URL . '/mahasiswa/notif_buka.php?id=' . $id;
}

function jumlah_notifikasi_belum_dibaca(PDO $pdo, int $user_id): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = :id AND dibaca = 0");
    $stmt->execute([':id' => $user_id]);

    return (int) $stmt->fetchColumn();
}
