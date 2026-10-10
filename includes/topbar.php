<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/akun.php';
require_once __DIR__ . '/notifikasi.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$topbar_user_id = (int) ($_SESSION['user']['id'] ?? 0);
$topbar_akun = data_akun($pdo, $topbar_user_id);

/* 5 notifikasi terbaru + jumlah belum dibaca */
$topbar_belum_dibaca = 0;
$topbar_notif = [];

try {
    $topbar_belum_dibaca = jumlah_notifikasi_belum_dibaca($pdo, $topbar_user_id);

    $stmt = $pdo->prepare("
        SELECT id, pesan, link, dibaca, created_at
        FROM notifikasi
        WHERE user_id = :id
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $topbar_user_id]);
    $topbar_notif = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Notifikasi topbar gagal: ' . $e->getMessage());
}

$topbar_sub = $topbar_akun['role_label']
    . ($topbar_akun['kelas'] !== '' ? ' · ' . $topbar_akun['kelas'] : '');
?>
<header class="topbar">

    <div class="topbar-spacer"></div>

    <div class="topbar-actions">

        <!-- Notifikasi -->
        <div class="dropdown">
            <button
                class="notification-button <?= $topbar_belum_dibaca > 0 ? 'has-unread' : '' ?>"
                type="button"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                aria-label="Notifikasi<?= $topbar_belum_dibaca > 0 ? ', ' . $topbar_belum_dibaca . ' belum dibaca' : '' ?>"
            >
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>

                <?php if ($topbar_belum_dibaca > 0): ?>
                    <span class="notification-count"><?= $topbar_belum_dibaca > 9 ? '9+' : $topbar_belum_dibaca ?></span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                <div class="notif-dropdown-head">
                    <div>
                        <strong>Notifikasi</strong>
                        <?php if ($topbar_belum_dibaca > 0): ?>
                            <span class="notif-badge"><?= $topbar_belum_dibaca ?> baru</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($topbar_belum_dibaca > 0): ?>
                        <form method="POST" action="<?= BASE_URL ?>/mahasiswa/notifikasi.php">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="aksi" value="baca_semua">
                            <input type="hidden" name="kembali" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                            <button type="submit" class="notif-link-btn">Tandai dibaca</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($topbar_notif): ?>
                    <div class="notif-dropdown-list">
                        <?php foreach ($topbar_notif as $n):
                            $info = info_notifikasi($n);
                        ?>
                            <a href="<?= e(url_buka_notifikasi((int) $n['id'])) ?>" class="notif-mini <?= (int) $n['dibaca'] === 0 ? 'is-unread' : '' ?>">
                                <span class="notif-icon is-<?= e($info['warna']) ?>">
                                    <i data-lucide="<?= e($info['ikon']) ?>"></i>
                                </span>
                                <span class="notif-mini-body">
                                    <strong><?= e($info['judul']) ?></strong>
                                    <span class="notif-mini-text"><?= e($n['pesan']) ?></span>
                                    <span class="notif-mini-time"><?= e(waktu_notifikasi($n['created_at'])) ?></span>
                                </span>
                                <?php if ((int) $n['dibaca'] === 0): ?>
                                    <span class="notif-unread-dot" aria-label="Belum dibaca"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="notif-dropdown-empty">
                        <i data-lucide="bell-off"></i>
                        <span>Belum ada notifikasi.</span>
                    </div>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/mahasiswa/notifikasi.php" class="notif-dropdown-all">
                    Lihat semua notifikasi
                    <i data-lucide="chevron-right"></i>
                </a>
            </div>
        </div>


        <!-- User Dropdown -->
        <div class="dropdown">

            <button
                class="user-profile dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <?php if ($topbar_akun['foto_url'] !== ''): ?>
                    <img src="<?= e($topbar_akun['foto_url']) ?>" alt="" class="user-avatar user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar"><?= e($topbar_akun['inisial']) ?></div>
                <?php endif; ?>

                <div class="user-info">
                    <div class="user-name"><?= e($topbar_akun['nama']) ?></div>
                    <div class="user-role"><?= e($topbar_sub) ?></div>
                </div>
            </button>

            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/mahasiswa/profil.php">
                        <span class="dropdown-icon"><i data-lucide="user-round"></i></span>
                        <span>Profil Saya</span>
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/mahasiswa/pengaturan.php">
                        <span class="dropdown-icon"><i data-lucide="settings"></i></span>
                        <span>Pengaturan</span>
                    </a>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item logout-item" href="<?= BASE_URL ?>/auth/logout.php">
                        <span class="dropdown-icon"><i data-lucide="log-out"></i></span>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>

        </div>

    </div>

</header>
