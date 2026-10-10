<?php
/*
 * Menyimpan pengajuan peminjaman dari halaman konfirmasi.
 * Berhasil  -> detail_peminjaman.php?id=...
 * Gagal     -> kembali ke konfirmasi.php dengan pesan error
 */

require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/kelengkapan.php";

require_role('mahasiswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
    exit;
}

$draft = $_SESSION['peminjaman_draft'] ?? null;

if (
    !is_array($draft)
    || empty($draft['alat_id'])
    || empty($draft['tanggal'])
    || empty($draft['ruangan'])
    || empty($draft['jam_mulai'])
    || empty($draft['jam_selesai'])
    || empty($draft['keperluan'])
    || !is_array($draft['keperluan'])
) {
    header("Location: " . BASE_URL . "/mahasiswa/katalog.php");
    exit;
}

$alat_id = (int) $draft['alat_id'];
$kembali_ke = BASE_URL . "/mahasiswa/konfirmasi.php?alat_id=" . $alat_id;

/* Kembali ke konfirmasi dengan pesan error */
function gagal(array $pesan, string $url): void
{
    $_SESSION['flash_konfirmasi'] = $pesan;
    header("Location: " . $url);
    exit;
}

/* 1. Keamanan dan persetujuan */
$csrf = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    gagal(['Permintaan tidak valid. Muat ulang halaman lalu coba lagi.'], $kembali_ke);
}

if (empty($_POST['setuju'])) {
    gagal(['Centang pernyataan persetujuan sebelum mengirim pengajuan.'], $kembali_ke);
}

$user_id = (int) $_SESSION['user']['id'];
$detail = $draft['keperluan'];
$tujuan = (string) ($detail['tujuan'] ?? '');
$kelas_id = (int) ($detail['kelas_id'] ?? 0);

if ($draft['tanggal'] < date('Y-m-d')) {
    gagal(['Tanggal peminjaman sudah lewat. Silakan pilih jadwal baru.'], $kembali_ke);
}

/* 2. Data alat */
$stmt = $pdo->prepare("
    SELECT a.id, a.nama, a.stok_total, a.kategori_id,
           k.nama_kategori, k.max_per_kelas
    FROM alat a
    INNER JOIN kategori_alat k ON k.id = a.kategori_id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $alat_id]);
$alat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alat) {
    gagal(['Alat tidak ditemukan.'], BASE_URL . "/mahasiswa/katalog.php");
}

/* 3. Dosen yang dimintai persetujuan -> id akun dosen */
$nama_dosen = $tujuan === 'Penggunaan kelas'
    ? (string) ($detail['dosen_pengampu'] ?? '')
    : (string) ($detail['dosen_pj'] ?? '');

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE role = 'dosen'
      AND nama = :nama
    LIMIT 1
");
$stmt->execute([':nama' => $nama_dosen]);
$dosen_id = (int) ($stmt->fetchColumn() ?: 0);

if ($dosen_id === 0) {
    gagal([
        'Dosen "' . $nama_dosen . '" belum terdaftar sebagai akun dosen di SIPAKA, '
        . 'sehingga pengajuan tidak bisa diteruskan. Hubungi admin.'
    ], $kembali_ke);
}

/* 4. Nilai kolom keperluan, mata kuliah, keterangan */
if ($tujuan === 'Penggunaan kelas') {
    $keperluan = 'Penggunaan kelas';
    $mata_kuliah_id = (int) ($detail['mata_kuliah'] ?? 0) ?: null;
} else {
    $keperluan = mb_substr('Organisasi: ' . ($detail['organisasi'] ?? ''), 0, 100);
    $mata_kuliah_id = null;
}

$keterangan = (string) ($detail['keterangan'] ?? '');

/* Kelengkapan tambahan dari langkah Informasi Alat (hanya yang ada di daftar) */
$kelengkapan = saring_kelengkapan($draft['kelengkapan'] ?? []);
$kelengkapan_teks = $kelengkapan ? implode(', ', $kelengkapan) : null;

/* 5. Simpan dalam satu transaksi, setelah memeriksa ulang jadwal */
$status_aktif = "p.status NOT IN ('ditolak', 'dikembalikan')";

$param_waktu = [
    ':tanggal' => $draft['tanggal'],
    ':jam_mulai' => $draft['jam_mulai'],
    ':jam_selesai' => $draft['jam_selesai']
];

try {
    $pdo->beginTransaction();

    $errors = [];

    /* Bentrok ruangan */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM peminjaman p
        WHERE p.tgl = :tanggal
          AND p.ruangan = :ruangan
          AND p.jam_mulai < :jam_selesai
          AND p.jam_selesai > :jam_mulai
          AND $status_aktif
    ");
    $stmt->execute($param_waktu + [':ruangan' => $draft['ruangan']]);

    if ((int) $stmt->fetchColumn() > 0) {
        $errors[] = 'Ruangan sudah dipakai pada waktu tersebut. Pilih ruangan atau jam lain.';
    }

    /* Stok alat pada slot yang sama */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM peminjaman p
        INNER JOIN peminjaman_item pi ON pi.peminjaman_id = p.id
        WHERE pi.alat_id = :alat_id
          AND p.tgl = :tanggal
          AND p.jam_mulai < :jam_selesai
          AND p.jam_selesai > :jam_mulai
          AND $status_aktif
    ");
    $stmt->execute($param_waktu + [':alat_id' => $alat_id]);

    if ((int) $stmt->fetchColumn() >= (int) $alat['stok_total']) {
        $errors[] = 'Stok ' . $alat['nama'] . ' sudah habis pada jadwal tersebut.';
    }

    /* Kuota kelas per kategori */
    $maks = (int) $alat['max_per_kelas'];

    if ($maks > 0 && $kelas_id > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM peminjaman p
            INNER JOIN peminjaman_item pi ON pi.peminjaman_id = p.id
            INNER JOIN alat a2 ON a2.id = pi.alat_id
            WHERE p.kelas_id = :kelas_id
              AND a2.kategori_id = :kategori_id
              AND p.tgl = :tanggal
              AND p.jam_mulai < :jam_selesai
              AND p.jam_selesai > :jam_mulai
              AND $status_aktif
        ");
        $stmt->execute($param_waktu + [
            ':kelas_id' => $kelas_id,
            ':kategori_id' => (int) $alat['kategori_id']
        ]);

        if ((int) $stmt->fetchColumn() >= $maks) {
            $errors[] = 'Kelasmu sudah memakai kuota ' . $maks . ' unit '
                . $alat['nama_kategori'] . ' pada slot waktu tersebut.';
        }
    }

    if ($errors) {
        $pdo->rollBack();
        gagal($errors, $kembali_ke);
    }

    /* Simpan pengajuan */
    $stmt = $pdo->prepare("
        INSERT INTO peminjaman (
            peminjam_id, kelas_id, mata_kuliah_id, ruangan, tgl,
            jam_mulai, jam_selesai, keperluan, keterangan,
            kelengkapan_tambahan, status, dosen_id, waktu_diajukan
        ) VALUES (
            :peminjam_id, :kelas_id, :mata_kuliah_id, :ruangan, :tgl,
            :jam_mulai, :jam_selesai, :keperluan, :keterangan,
            :kelengkapan_tambahan, 'diajukan', :dosen_id, NOW()
        )
    ");
    $stmt->execute([
        ':peminjam_id' => $user_id,
        ':kelas_id' => $kelas_id ?: null,
        ':mata_kuliah_id' => $mata_kuliah_id,
        ':ruangan' => $draft['ruangan'],
        ':tgl' => $draft['tanggal'],
        ':jam_mulai' => $draft['jam_mulai'],
        ':jam_selesai' => $draft['jam_selesai'],
        ':keperluan' => $keperluan,
        ':keterangan' => $keterangan,
        ':kelengkapan_tambahan' => $kelengkapan_teks,
        ':dosen_id' => $dosen_id
    ]);

    $peminjaman_id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO peminjaman_item (peminjaman_id, alat_id)
        VALUES (:peminjaman_id, :alat_id)
    ");
    $stmt->execute([
        ':peminjaman_id' => $peminjaman_id,
        ':alat_id' => $alat_id
    ]);

    /* Notifikasi untuk dosen */
    $nama_mahasiswa = $_SESSION['user']['nama'] ?? 'Mahasiswa';
    $kelas = (string) ($detail['kelas'] ?? '');

    $pesan = 'Pengajuan peminjaman baru dari ' . $nama_mahasiswa
        . ($kelas !== '' ? ' (' . $kelas . ')' : '')
        . ': ' . $alat['nama'] . ' pada ' . $draft['tanggal']
        . ' pukul ' . substr($draft['jam_mulai'], 0, 5)
        . '-' . substr($draft['jam_selesai'], 0, 5)
        . ($kelengkapan_teks ? ' + ' . $kelengkapan_teks : '') . '.';

    $stmt = $pdo->prepare("
        INSERT INTO notifikasi (user_id, pesan, link)
        VALUES (:user_id, :pesan, :link)
    ");
    $stmt->execute([
        ':user_id' => $dosen_id,
        ':pesan' => $pesan,
        ':link' => '/dosen/persetujuan.php?id=' . $peminjaman_id
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Simpan peminjaman gagal: ' . $e->getMessage());

        gagal(['Pengajuan belum bisa disimpan. Silakan coba lagi.'], $kembali_ke);
}

/* 6. Selesai: hapus draft lalu buka halaman status */
unset($_SESSION['peminjaman_draft']);

header("Location: " . BASE_URL . "/mahasiswa/detail_peminjaman.php?id=" . $peminjaman_id);
exit;
