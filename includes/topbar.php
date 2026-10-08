<header class="topbar">

    <div class="topbar-spacer"></div>

    <div class="topbar-actions">

        <!-- Notifikasi -->
        <button
            class="notification-button"
            type="button"
            aria-label="Notifikasi"
        >
            <svg
                width="19"
                height="19"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>

            <span class="notification-dot"></span>
        </button>


        <!-- User Dropdown -->
        <div class="dropdown">

            <button
                class="user-profile dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >

                <div class="user-avatar">
                    FZ
                </div>

                <div class="user-info">

                    <div class="user-name">
                        <?= e($_SESSION['user']['nama']) ?>
                    </div>

                    <div class="user-role">
                        Mahasiswa - TEKOM A
                    </div>

                </div>

            </button>


            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">

                <li>
                    <a
                        class="dropdown-item"
                        href="<?= BASE_URL ?>/mahasiswa/profil.php"
                    >
                        <span><i data-lucide="user-round"></i></span>
                        <span>Profil Saya</span>
                    </a>
                </li>

                <li>
                    <a
                        class="dropdown-item"
                        href="<?= BASE_URL ?>/mahasiswa/pengaturan.php"
                    >
                        <span><i data-lucide="settings"></i></span>
                        <span>Pengaturan</span>
                    </a>
                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>
                    <a
                        class="dropdown-item logout-item"
                        href="<?= BASE_URL ?>/auth/logout.php"
                    >
                        <span><i data-lucide="log-out"></i></span>
                        <span>Logout</span>
                    </a>
                </li>

            </ul>

        </div>

    </div>

</header>