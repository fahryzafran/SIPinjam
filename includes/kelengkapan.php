<?php

/*
 * Kelengkapan tambahan (opsional) yang bisa dipilih mahasiswa
 * di langkah Informasi Alat. Satu daftar dipakai di semua halaman:
 * ajukan.php, konfirmasi.php, proses_peminjaman.php, detail_peminjaman.php.
 *
 * Format: 'Nama kelengkapan' => 'nama ikon lucide'
 * Tambah atau ubah pilihan cukup di sini.
 */
function daftar_kelengkapan_tambahan(): array
{
    return [
        'Adaptor HDMI to Type-C' => 'plug',
        'Pointer / Wireless Presenter' => 'presentation'
    ];
}

/**
 * Menyaring input agar hanya berisi pilihan yang ada di daftar,
 * tanpa duplikat, dengan urutan mengikuti daftar.
 */
function saring_kelengkapan($input): array
{
    if (!is_array($input)) {
        return [];
    }

    $dipilih = array_filter($input, 'is_string');

    return array_values(array_filter(
        array_keys(daftar_kelengkapan_tambahan()),
        static fn (string $nama): bool => in_array($nama, $dipilih, true)
    ));
}

/* Mengubah teks dari database ("A, B") kembali menjadi array */
function urai_kelengkapan(?string $teks): array
{
    if ($teks === null || trim($teks) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $teks))));
}

function ikon_kelengkapan(string $nama): string
{
    return daftar_kelengkapan_tambahan()[$nama] ?? 'plus-circle';
}
