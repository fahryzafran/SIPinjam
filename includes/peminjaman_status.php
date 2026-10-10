<?php

/*
 * Helper status peminjaman.
 * Dipakai oleh mahasiswa/detail_peminjaman.php (tampilan awal)
 * dan mahasiswa/status_peminjaman.php (pembaruan realtime / JSON),
 * sehingga keduanya selalu menghasilkan data yang sama.
 */

function format_tanggal_indonesia(?string $nilai, bool $dengan_hari = false): string
{
    if (!$nilai) {
        return '-';
    }

    $waktu = strtotime($nilai);

    if ($waktu === false) {
        return $nilai;
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    $teks = date('j', $waktu) . ' ' . $bulan[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);

    return $dengan_hari ? $hari[(int) date('w', $waktu)] . ', ' . $teks : $teks;
}

function format_jam_titik(?string $nilai): string
{
    return $nilai ? str_replace(':', '.', substr($nilai, 0, 5)) : '-';
}

/* Contoh: 12 Okt 2026, 09.30 */
function format_waktu_singkat(?string $nilai): string
{
    if (!$nilai) {
        return '';
    }

    $waktu = strtotime($nilai);

    if ($waktu === false) {
        return $nilai;
    }

    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return date('j', $waktu) . ' ' . $bulan[(int) date('n', $waktu)] . ' ' . date('Y', $waktu)
        . ', ' . date('H.i', $waktu);
}

function kode_pengajuan(int $id, ?string $waktu_diajukan): string
{
    $tahun = $waktu_diajukan ? date('Y', strtotime($waktu_diajukan)) : date('Y');

    return 'PMJ-' . $tahun . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/**
 * Mengambil satu peminjaman milik mahasiswa beserta data tampilannya.
 * Mengembalikan null jika tidak ditemukan atau bukan milik user tersebut.
 */
function ambil_peminjaman(PDO $pdo, int $peminjaman_id, int $user_id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            d.nama AS nama_dosen,
            k.nama_kelas,
            mk.nama_mata_kuliah,
            r.lokasi AS lokasi_ruangan
        FROM peminjaman p
        LEFT JOIN users d ON d.id = p.dosen_id
        LEFT JOIN kelas k ON k.id = p.kelas_id
        LEFT JOIN mata_kuliah mk ON mk.id = p.mata_kuliah_id
        LEFT JOIN ruangan r ON r.nama_ruangan = p.ruangan
        WHERE p.id = :id
          AND p.peminjam_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':id' => $peminjaman_id, ':user_id' => $user_id]);
    $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$peminjaman) {
        return null;
    }

    /* Alat dan nomor unit (nomor unit diisi admin lab saat menyiapkan) */
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.nama,
            a.spesifikasi,
            ka.nama_kategori,
            u.kode_unit
        FROM peminjaman_item pi
        INNER JOIN alat a ON a.id = pi.alat_id
        INNER JOIN kategori_alat ka ON ka.id = a.kategori_id
        LEFT JOIN unit_alat u ON u.id = pi.unit_alat_id
        WHERE pi.peminjaman_id = :id
    ");
    $stmt->execute([':id' => $peminjaman_id]);
    $peminjaman['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $peminjaman;
}

/**
 * Menyusun data status untuk tracker 4 tahap.
 * Tahap ditentukan dari kolom status DAN kolom waktu_*,
 * jadi tetap benar walaupun ada nilai status lain di enum.
 */
function susun_status_peminjaman(array $p): array
{
    $status = (string) $p['status'];

    $label_status = [
        'diajukan' => 'Menunggu persetujuan dosen',
        'disetujui_dosen' => 'Disetujui dosen',
        'ditolak' => 'Ditolak dosen',
        'siap_diambil' => 'Siap diambil',
        'dipinjam' => 'Sedang dipinjam',
        'dikembalikan' => 'Sudah dikembalikan'
    ];

    $sesudah_dosen = ['disetujui_dosen', 'siap_diambil', 'dipinjam', 'dikembalikan'];
    $sesudah_siap = ['siap_diambil', 'dipinjam', 'dikembalikan'];
    $sesudah_ambil = ['dipinjam', 'dikembalikan'];

    $ditolak = $status === 'ditolak';
    $dosen_selesai = in_array($status, $sesudah_dosen, true) || !empty($p['waktu_disetujui_dosen']);
    $lab_selesai = in_array($status, $sesudah_siap, true) || !empty($p['waktu_disiapkan']);
    $sudah_diambil = in_array($status, $sesudah_ambil, true) || !empty($p['waktu_diambil']);
    $dikembalikan = $status === 'dikembalikan' || !empty($p['waktu_dikembalikan']);

    $kode_unit = array_values(array_filter(array_column($p['items'] ?? [], 'kode_unit')));
    $nama_dosen = $p['nama_dosen'] ?: 'Dosen pengampu';

    /* Tahap 1: Diajukan */
    $langkah = [[
        'judul' => 'Diajukan',
        'keterangan' => format_waktu_singkat($p['waktu_diajukan']) ?: 'Pengajuan terkirim',
        'sub' => 'Oleh mahasiswa',
        'state' => 'selesai',
        'badge' => 'Selesai'
    ]];

    /* Tahap 2: Persetujuan dosen */
    if ($ditolak) {
        $langkah[] = [
            'judul' => 'Persetujuan Dosen',
            'keterangan' => $nama_dosen,
            'sub' => 'Pengajuan ditolak',
            'state' => 'ditolak',
            'badge' => 'Ditolak'
        ];
    } elseif ($dosen_selesai) {
        $langkah[] = [
            'judul' => 'Persetujuan Dosen',
            'keterangan' => $nama_dosen,
            'sub' => format_waktu_singkat($p['waktu_disetujui_dosen']) ?: 'Disetujui',
            'state' => 'selesai',
            'badge' => 'Disetujui'
        ];
    } else {
        $langkah[] = [
            'judul' => 'Persetujuan Dosen',
            'keterangan' => $nama_dosen,
            'sub' => 'Menunggu keputusan dosen',
            'state' => 'aktif',
            'badge' => 'Sedang diproses'
        ];
    }

    /* Tahap 3: Disiapkan admin lab */
    if ($ditolak) {
        $state3 = 'batal';
    } elseif ($lab_selesai) {
        $state3 = 'selesai';
    } elseif ($dosen_selesai) {
        $state3 = 'aktif';
    } else {
        $state3 = 'menunggu';
    }

    $langkah[] = [
        'judul' => 'Disiapkan Admin Lab',
        'keterangan' => $state3 === 'selesai' && $kode_unit
            ? 'Nomor unit: ' . implode(', ', $kode_unit)
            : 'Penyiapan & pengecekan unit',
        'sub' => $state3 === 'selesai'
            ? (format_waktu_singkat($p['waktu_disiapkan']) ?: 'Unit siap')
            : 'Admin lab',
        'state' => $state3,
        'badge' => [
            'selesai' => 'Selesai',
            'aktif' => 'Sedang diproses',
            'menunggu' => 'Menunggu',
            'batal' => 'Dibatalkan'
        ][$state3]
    ];

    /* Tahap 4: Siap diambil */
    if ($ditolak) {
        $state4 = 'batal';
    } elseif ($sudah_diambil) {
        $state4 = 'selesai';
    } elseif ($lab_selesai) {
        $state4 = 'aktif';
    } else {
        $state4 = 'menunggu';
    }

    if ($dikembalikan) {
        $sub4 = 'Dikembalikan ' . format_waktu_singkat($p['waktu_dikembalikan']);
    } elseif ($sudah_diambil) {
        $sub4 = 'Diambil ' . format_waktu_singkat($p['waktu_diambil']);
    } elseif ($state4 === 'aktif') {
        $sub4 = 'Tunjukkan kode pengajuan';
    } else {
        $sub4 = 'Ruang pengambilan alat';
    }

    $langkah[] = [
        'judul' => 'Siap Diambil',
        'keterangan' => $state4 === 'aktif' ? 'Silakan ambil alat' : 'Pengambilan alat',
        'sub' => trim($sub4),
        'state' => $state4,
        'badge' => [
            'selesai' => $dikembalikan ? 'Dikembalikan' : 'Diambil',
            'aktif' => 'Siap',
            'menunggu' => 'Menunggu',
            'batal' => 'Dibatalkan'
        ][$state4]
    ];

    /* Tahap yang sedang berjalan (untuk label "Tahap X dari 4") */
    if ($ditolak) {
        $tahap = 2;
    } elseif ($sudah_diambil) {
        $tahap = 4;
    } elseif ($lab_selesai) {
        $tahap = 4;
    } elseif ($dosen_selesai) {
        $tahap = 3;
    } else {
        $tahap = 2;
    }

    /* Teks kartu utama */
    if ($ditolak) {
        $hero = [
            'jenis' => 'ditolak',
            'judul' => 'Pengajuan Ditolak',
            'deskripsi' => 'Dosen tidak menyetujui pengajuan ini. Kamu bisa mengajukan ulang dengan jadwal atau keperluan yang disesuaikan.'
        ];
    } elseif ($dikembalikan) {
        $hero = [
            'jenis' => 'selesai',
            'judul' => 'Peminjaman Selesai',
            'deskripsi' => 'Alat sudah dikembalikan. Terima kasih telah menjaga alat kampus.'
        ];
    } elseif ($sudah_diambil) {
        $hero = [
            'jenis' => 'selesai',
            'judul' => 'Alat Sedang Dipinjam',
            'deskripsi' => 'Kembalikan alat paling lambat pukul 17.00 (toleransi sampai 17.15).'
        ];
    } elseif ($lab_selesai) {
        $hero = [
            'jenis' => 'siap',
            'judul' => 'Alat Siap Diambil!',
            'deskripsi' => 'Admin lab sudah menyiapkan alat. Datang ke ruang pengambilan dan tunjukkan kode pengajuan.'
        ];
    } elseif ($dosen_selesai) {
        $hero = [
            'jenis' => 'proses',
            'judul' => 'Disetujui Dosen',
            'deskripsi' => 'Pengajuan sudah disetujui dan diteruskan ke admin lab untuk disiapkan.'
        ];
    } else {
        $hero = [
            'jenis' => 'terkirim',
            'judul' => 'Pengajuan Berhasil Dikirim!',
            'deskripsi' => 'Pengajuan sudah diteruskan ke ' . $nama_dosen . ' untuk dievaluasi dan disetujui.'
        ];
    }

    return [
        'id' => (int) $p['id'],
        'kode' => kode_pengajuan((int) $p['id'], $p['waktu_diajukan']),
        'status' => $status,
        'status_label' => $label_status[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        'tahap' => $tahap,
        'final' => $ditolak || $dikembalikan,
        'hero' => $hero,
        'langkah' => $langkah,
        'catatan_dosen' => (string) ($p['catatan_dosen'] ?? ''),
        'unit' => $kode_unit
    ];
}
