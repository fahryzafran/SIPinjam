<?php

/*
 * Data akun yang dipakai topbar, profil, dan pengaturan.
 * Hasil disimpan sementara (cache) agar topbar tidak query berulang.
 */

const FOLDER_FOTO_PROFIL = '/uploads/foto_profil/';

function data_akun(PDO $pdo, int $user_id, bool $segarkan = false): array
{
    static $cache = [];

    if (!$segarkan && isset($cache[$user_id])) {
        return $cache[$user_id];
    }

    $stmt = $pdo->prepare("
        SELECT id, nama, email, role, nim_nip, foto
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $user_id]);
    $akun = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'id' => $user_id,
        'nama' => $_SESSION['user']['nama'] ?? 'Pengguna',
        'email' => '',
        'role' => $_SESSION['user']['role'] ?? '',
        'nim_nip' => '',
        'foto' => null
    ];

    /* Kelas mahasiswa (kosong untuk role lain) */
    $akun['kelas'] = '';
    $akun['kelas_id'] = 0;

    if ($akun['role'] === 'mahasiswa') {
        $stmt = $pdo->prepare("
            SELECT k.id, k.nama_kelas
            FROM kelas_mahasiswa km
            INNER JOIN kelas k ON k.id = km.kelas_id
            WHERE km.user_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $user_id]);

        if ($kelas = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $akun['kelas'] = $kelas['nama_kelas'];
            $akun['kelas_id'] = (int) $kelas['id'];
        }
    }

    $akun['inisial'] = inisial_nama($akun['nama']);
    $akun['foto_url'] = !empty($akun['foto'])
        ? BASE_URL . FOLDER_FOTO_PROFIL . rawurlencode($akun['foto'])
        : '';

    $label_role = [
        'mahasiswa' => 'Mahasiswa',
        'dosen' => 'Dosen',
        'adminlab' => 'Admin Lab',
        'superadmin' => 'Superadmin'
    ];
    $akun['role_label'] = $label_role[$akun['role']] ?? ucfirst((string) $akun['role']);

    return $cache[$user_id] = $akun;
}

/* "Fahry Zafran" -> "FZ", "Fahry" -> "FA" */
function inisial_nama(string $nama): string
{
    $kata = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY);

    if (!$kata) {
        return '?';
    }

    if (count($kata) === 1) {
        return mb_strtoupper(mb_substr($kata[0], 0, 2));
    }

    return mb_strtoupper(mb_substr($kata[0], 0, 1) . mb_substr($kata[1], 0, 1));
}
