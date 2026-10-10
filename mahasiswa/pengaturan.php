<?php
require_once "../config/app.php";
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/helpers.php";
require_once "../includes/akun.php";

require_role('mahasiswa');

$user_id = (int) $_SESSION['user']['id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const MAKS_FOTO = 2 * 1024 * 1024; /* 2 MB */
$folder_foto = __DIR__ . '/..' . FOLDER_FOTO_PROFIL;

/* Hapus file foto lama dengan aman (hanya nama file, tanpa folder) */
function hapus_file_foto(string $folder, ?string $nama_file): void
{
    if ($nama_file === null || $nama_file === '') {
        return;
    }

    $path = $folder . basename($nama_file);

    if (is_file($path)) {
        @unlink($path);
    }
}

$errors_profil = [];
$errors_password = [];
$form_nama = null;

/* ==========================================================
   Proses formulir
   ========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';
    $csrf_ok = is_string($csrf) && hash_equals($_SESSION['csrf_token'], $csrf);

    $akun_lama = data_akun($pdo, $user_id);

    /* ---------- Simpan profil (nama + foto) ---------- */
    if ($aksi === 'profil') {
        $form_nama = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nama'] ?? '')));

        if (!$csrf_ok) {
            $errors_profil[] = 'Permintaan tidak valid. Muat ulang halaman.';
        }

        if (mb_strlen($form_nama) < 3 || mb_strlen($form_nama) > 100) {
            $errors_profil[] = 'Nama harus 3 sampai 100 karakter.';
        } elseif (!preg_match("/^[\p{L}][\p{L} .,'-]*$/u", $form_nama)) {
            $errors_profil[] = 'Nama hanya boleh berisi huruf, spasi, titik, koma, tanda petik, dan tanda hubung.';
        }

        /* Foto (opsional) */
        $foto_baru = null;
        $file = $_FILES['foto'] ?? null;
        $ada_file = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($ada_file && !$errors_profil) {
            $tipe_diizinkan = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAKS_FOTO) {
                $errors_profil[] = 'Ukuran foto maksimal 2 MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                $errors_profil[] = 'Foto gagal diunggah. Coba lagi.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                $ukuran = @getimagesize($file['tmp_name']);

                if (!isset($tipe_diizinkan[$mime]) || $ukuran === false) {
                    $errors_profil[] = 'Foto harus berupa gambar JPG atau PNG.';
                } else {
                    if (!is_dir($folder_foto) && !mkdir($folder_foto, 0755, true)) {
                        $errors_profil[] = 'Folder foto belum bisa dibuat di server.';
                    } else {
                        $foto_baru = 'u' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $tipe_diizinkan[$mime];

                        if (!move_uploaded_file($file['tmp_name'], $folder_foto . $foto_baru)) {
                            $errors_profil[] = 'Foto gagal disimpan. Coba lagi.';
                            $foto_baru = null;
                        }
                    }
                }
            }
        }

        if (!$errors_profil) {
            try {
                if ($foto_baru !== null) {
                    $stmt = $pdo->prepare("UPDATE users SET nama = :nama, foto = :foto WHERE id = :id");
                    $stmt->execute([':nama' => $form_nama, ':foto' => $foto_baru, ':id' => $user_id]);
                    hapus_file_foto($folder_foto, $akun_lama['foto'] ?? null);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nama = :nama WHERE id = :id");
                    $stmt->execute([':nama' => $form_nama, ':id' => $user_id]);
                }

                $_SESSION['user']['nama'] = $form_nama;
                $_SESSION['flash_pengaturan'] = 'Profil berhasil disimpan.';

                header("Location: " . BASE_URL . "/mahasiswa/pengaturan.php");
                exit;
            } catch (PDOException $e) {
                hapus_file_foto($folder_foto, $foto_baru);
                error_log('Simpan profil gagal: ' . $e->getMessage());
                $errors_profil[] = 'Profil belum bisa disimpan. Silakan coba lagi.';
            }
        } elseif ($foto_baru !== null) {
            hapus_file_foto($folder_foto, $foto_baru);
        }
    }

    /* ---------- Hapus foto ---------- */
    if ($aksi === 'hapus_foto' && $csrf_ok) {
        $pdo->prepare("UPDATE users SET foto = NULL WHERE id = :id")->execute([':id' => $user_id]);
        hapus_file_foto($folder_foto, $akun_lama['foto'] ?? null);

        $_SESSION['flash_pengaturan'] = 'Foto profil dihapus.';
        header("Location: " . BASE_URL . "/mahasiswa/pengaturan.php");
        exit;
    }

    /* ---------- Ganti kata sandi ---------- */
    if ($aksi === 'password') {
        $lama = (string) ($_POST['password_lama'] ?? '');
        $baru = (string) ($_POST['password_baru'] ?? '');
        $ulang = (string) ($_POST['password_ulang'] ?? '');

        if (!$csrf_ok) {
            $errors_password[] = 'Permintaan tidak valid. Muat ulang halaman.';
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user_id]);
        $hash = (string) $stmt->fetchColumn();

        if ($lama === '' || !password_verify($lama, $hash)) {
            $errors_password[] = 'Kata sandi saat ini salah.';
        }

        if (strlen($baru) < 8) {
            $errors_password[] = 'Kata sandi baru minimal 8 karakter.';
        } elseif (strlen($baru) > 72) {
            $errors_password[] = 'Kata sandi baru maksimal 72 karakter.';
        }

        if ($baru !== $ulang) {
            $errors_password[] = 'Ulangi kata sandi baru dengan tepat.';
        }

        if ($baru !== '' && $baru === $lama) {
            $errors_password[] = 'Kata sandi baru harus berbeda dari yang lama.';
        }

        if (!$errors_password) {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute([':hash' => password_hash($baru, PASSWORD_DEFAULT), ':id' => $user_id]);

            session_regenerate_id(true);
            $_SESSION['flash_pengaturan'] = 'Kata sandi berhasil diperbarui.';

            header("Location: " . BASE_URL . "/mahasiswa/pengaturan.php#keamanan");
            exit;
        }
    }
}

$akun = data_akun($pdo, $user_id, true);
$nama_tampil = $form_nama ?? $akun['nama'];

$flash = $_SESSION['flash_pengaturan'] ?? '';
unset($_SESSION['flash_pengaturan']);

$page_title = 'Pengaturan';

require_once "../includes/header.php";
require_once "../includes/sidebar.php";
?>

<div class="main-wrapper">
    <?php require_once "../includes/topbar.php"; ?>

    <main class="main-content pengaturan-page">

        <div>
            <nav class="riwayat-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>/mahasiswa/profil.php">Profil Saya</a>
                <i data-lucide="chevron-right"></i>
                <span>Pengaturan</span>
            </nav>
            <h1 class="profil-title">Pengaturan</h1>
            <p class="pengaturan-sub">Ubah foto profil, nama, dan kata sandi akunmu.</p>
        </div>

        <div class="pengaturan-grid">

        <!-- Profil -->
        <section class="profil-card pengaturan-card" id="profil">
            <div class="profil-card-head">
                <span class="profil-card-icon"><i data-lucide="user-round"></i></span>
                <div>
                    <h3>Profil</h3>
                    <p>Foto dan nama tampil di navbar dan di pengajuan yang kamu kirim.</p>
                </div>
            </div>

            <?php if ($errors_profil): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors_profil as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/mahasiswa/pengaturan.php" enctype="multipart/form-data" id="formProfil">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="aksi" value="profil">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAKS_FOTO ?>">

                <div class="pengaturan-foto">
                    <div class="pengaturan-avatar-wrap">
                        <img
                            src="<?= e($akun['foto_url']) ?>"
                            alt="Foto profil"
                            class="pengaturan-avatar"
                            id="pratinjauFoto"
                            <?= $akun['foto_url'] === '' ? 'hidden' : '' ?>
                        >
                        <div class="pengaturan-avatar" id="inisialFoto" <?= $akun['foto_url'] !== '' ? 'hidden' : '' ?>>
                            <?= e($akun['inisial']) ?>
                        </div>

                        <label for="foto" class="pengaturan-kamera" aria-label="Ganti foto profil">
                            <i data-lucide="camera"></i>
                        </label>
                    </div>

                    <div class="pengaturan-foto-info">
                        <strong>Foto profil</strong>
                        <p id="fotoInfo">JPG atau PNG, maksimal 2 MB. Foto ditampilkan bulat.</p>

                        <div class="pengaturan-foto-aksi">
                            <label for="foto" class="btn pengaturan-btn-outline">
                                <i data-lucide="upload"></i>
                                Unggah foto
                            </label>
                            <input type="file" id="foto" name="foto" accept="image/jpeg,image/png" class="pengaturan-file">

                            <?php if ($akun['foto_url'] !== ''): ?>
                                <button type="submit" form="formHapusFoto" class="btn pengaturan-btn-text">
                                    Hapus foto
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="pengaturan-field">
                    <label for="nama" class="form-label">Nama lengkap</label>
                    <input
                        type="text" id="nama" name="nama" class="form-control"
                        value="<?= e($nama_tampil) ?>" minlength="3" maxlength="100" required
                    >
                    <small>Gunakan nama sesuai data kampus agar mudah diverifikasi dosen.</small>
                </div>

                <div class="pengaturan-actions">
                    <button type="submit" class="btn btn-primary pengaturan-btn">Simpan profil</button>
                </div>
            </form>

            <form method="POST" action="<?= BASE_URL ?>/mahasiswa/pengaturan.php" id="formHapusFoto"
                  onsubmit="return confirm('Hapus foto profil?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="aksi" value="hapus_foto">
            </form>
        </section>

        <!-- Kata sandi -->
        <section class="profil-card pengaturan-card" id="keamanan">
            <div class="profil-card-head">
                <span class="profil-card-icon"><i data-lucide="lock-keyhole"></i></span>
                <div>
                    <h3>Ganti kata sandi</h3>
                    <p>Minimal 8 karakter. Jangan pakai kata sandi yang sama dengan akun lain.</p>
                </div>
            </div>

            <?php if ($errors_password): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors_password as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/mahasiswa/pengaturan.php#keamanan" id="formPassword" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="aksi" value="password">

                <div class="pengaturan-field">
                    <label for="password_lama" class="form-label">Kata sandi saat ini</label>
                    <input type="password" id="password_lama" name="password_lama" class="form-control" autocomplete="current-password" required>
                </div>

                <div class="pengaturan-field">
                    <label for="password_baru" class="form-label">Kata sandi baru</label>
                    <input type="password" id="password_baru" name="password_baru" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>

                    <div class="pengaturan-kekuatan" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <small id="kekuatanTeks">Gunakan campuran huruf besar, angka, dan simbol.</small>
                </div>

                <div class="pengaturan-field">
                    <label for="password_ulang" class="form-label">Ulangi kata sandi baru</label>
                    <input type="password" id="password_ulang" name="password_ulang" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>

                <div class="pengaturan-actions">
                    <button type="submit" class="btn btn-primary pengaturan-btn">Perbarui kata sandi</button>
                </div>
            </form>
        </section>

        </div>

        <?php if ($flash !== ''): ?>
            <div class="status-toast show" id="pengaturanToast" role="status">
                <i data-lucide="check-circle-2"></i>
                <span><?= e($flash) ?></span>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Pratinjau foto sebelum disimpan */
    const inputFoto = document.getElementById('foto');
    const pratinjau = document.getElementById('pratinjauFoto');
    const inisial = document.getElementById('inisialFoto');
    const info = document.getElementById('fotoInfo');
    const MAKS = <?= MAKS_FOTO ?>;

    inputFoto.addEventListener('change', function () {
        const file = inputFoto.files[0];

        if (!file) return;

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            alert('Foto harus berupa gambar JPG atau PNG.');
            inputFoto.value = '';
            return;
        }

        if (file.size > MAKS) {
            alert('Ukuran foto maksimal 2 MB.');
            inputFoto.value = '';
            return;
        }

        pratinjau.src = URL.createObjectURL(file);
        pratinjau.hidden = false;
        inisial.hidden = true;
        info.textContent = file.name + ' dipilih. Klik "Simpan profil" untuk menyimpan.';
    });

    /* Indikator kekuatan kata sandi */
    const baru = document.getElementById('password_baru');
    const ulang = document.getElementById('password_ulang');
    const batang = document.querySelectorAll('.pengaturan-kekuatan span');
    const teks = document.getElementById('kekuatanTeks');
    const label = ['Terlalu pendek', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];

    baru.addEventListener('input', function () {
        const v = baru.value;
        let skor = 0;

        if (v.length >= 8) {
            skor = 1;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) skor++;
            if (/\d/.test(v)) skor++;
            if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) skor++;
        }

        batang.forEach(function (b, i) { b.classList.toggle('is-on', i < skor); });
        teks.textContent = v === '' ? 'Gunakan campuran huruf besar, angka, dan simbol.' : 'Kekuatan: ' + label[skor];
    });

    function cekSama() {
        ulang.setCustomValidity(ulang.value !== '' && ulang.value !== baru.value ? 'Kata sandi tidak sama.' : '');
    }

    baru.addEventListener('input', cekSama);
    ulang.addEventListener('input', cekSama);

    const toast = document.getElementById('pengaturanToast');
    if (toast) {
        setTimeout(function () { toast.classList.remove('show'); }, 4000);
        setTimeout(function () { toast.remove(); }, 4500);
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>