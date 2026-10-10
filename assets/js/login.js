/* ==========================================================
   SIPAKA - LOGIN
   Animasi panel kiri, validasi form, dan transisi login berhasil
   ========================================================== */

(function () {
    'use strict';

    const kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. Teks berganti (efek mengetik) ---------- */

    const ketik = document.getElementById('loginKetik');
    const kalimat = [
        'Proyektor, kabel HDMI, sampai mic.',
        'Ajukan dalam 3 langkah singkat.',
        'Cek ketersediaan alat kapan saja.',
        'Riwayat peminjaman tersimpan rapi.'
    ];

    if (ketik) {
        let k = 0;
        let h = 0;
        let hapus = false;

        const jalan = function () {
            const teks = kalimat[k];

            if (!hapus) {
                h++;
                ketik.textContent = teks.slice(0, h);

                if (h >= teks.length) {
                    hapus = true;
                    setTimeout(jalan, 1600);
                    return;
                }
            } else {
                h = Math.max(0, h - 2);
                ketik.textContent = teks.slice(0, h);

                if (h === 0) {
                    hapus = false;
                    k = (k + 1) % kalimat.length;
                }
            }

            setTimeout(jalan, hapus ? 30 : 55);
        };

        if (kurangiGerak) {
            ketik.textContent = kalimat[0];
        } else {
            jalan();
        }
    }

    /* ---------- 2. Proyektor menyala + slide bergantian ---------- */

    const scene = document.getElementById('loginScene');

    if (scene) {
        const slides = scene.querySelectorAll('.ls-slide');
        const dots = scene.querySelectorAll('.ls-dots span');
        let aktif = 0;

        const tampilkanSlide = function (i) {
            slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === i); });
            dots.forEach(function (d, idx) { d.classList.toggle('is-active', idx === i); });
        };

        setTimeout(function () {
            scene.classList.add('is-on');
            tampilkanSlide(0);

            setInterval(function () {
                aktif = (aktif + 1) % slides.length;
                tampilkanSlide(aktif);
            }, 3200);
        }, kurangiGerak ? 0 : 900);

        /* Efek kedalaman mengikuti mouse */
        const hero = document.getElementById('loginHero');

        if (hero && !kurangiGerak) {
            hero.addEventListener('mousemove', function (e) {
                const r = hero.getBoundingClientRect();
                scene.style.setProperty('--mx', ((e.clientX - r.left) / r.width - 0.5).toFixed(3));
                scene.style.setProperty('--my', ((e.clientY - r.top) / r.height - 0.5).toFixed(3));
            });

            hero.addEventListener('mouseleave', function () {
                scene.style.setProperty('--mx', 0);
                scene.style.setProperty('--my', 0);
            });
        }
    }

    /* ---------- 3. Form login ---------- */

    const form = document.getElementById('loginForm');

    if (!form) {
        return;
    }

    const box = document.getElementById('loginBox');
    const inputId = document.getElementById('nim_nip');
    const inputPw = document.getElementById('password');
    const tombol = document.getElementById('loginSubmit');
    const alertBox = document.getElementById('loginAlert');
    const alertText = document.getElementById('loginAlertText');
    const mata = document.getElementById('loginEye');
    const caps = document.getElementById('loginCaps');

    /* Tampilkan / sembunyikan kata sandi */
    mata.addEventListener('click', function () {
        const lihat = inputPw.type === 'password';
        inputPw.type = lihat ? 'text' : 'password';
        mata.classList.toggle('is-visible', lihat);
        mata.setAttribute('aria-label', lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        inputPw.focus();
    });

    /* Peringatan Caps Lock */
    const cekCaps = function (e) {
        if (e.getModifierState) {
            caps.hidden = !e.getModifierState('CapsLock');
        }
    };
    inputPw.addEventListener('keyup', cekCaps);
    inputPw.addEventListener('keydown', cekCaps);
    inputPw.addEventListener('blur', function () { caps.hidden = true; });

    /* Hapus tanda merah saat mulai mengetik */
    [inputId, inputPw].forEach(function (el) {
        el.addEventListener('input', function () { el.classList.remove('is-error'); });
    });

    const tampilkanError = function (pesan, field) {
        alertText.textContent = pesan;
        alertBox.hidden = false;

        inputId.classList.toggle('is-error', field === 'nim_nip' || field === 'semua');
        inputPw.classList.toggle('is-error', field === 'password' || field === 'semua');

        /* Ulang animasi goyang */
        box.classList.remove('is-shake');
        void box.offsetWidth;
        box.classList.add('is-shake');

        if (field === 'password') {
            inputPw.focus();
            inputPw.select();
        } else {
            inputId.focus();
        }
    };

    const setTombol = function (status) {
        tombol.classList.toggle('is-loading', status === 'loading');
        tombol.classList.toggle('is-done', status === 'done');
        tombol.disabled = status !== 'idle';
    };

    const tunggu = function (ms) {
        return new Promise(function (r) { setTimeout(r, ms); });
    };

    let sedangKirim = false;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (sedangKirim) {
            return;
        }

        const id = inputId.value.trim();
        const pw = inputPw.value;

        if (id === '' && pw === '') {
            tampilkanError('Isi NIM/NIP dan kata sandi terlebih dahulu.', 'semua');
            return;
        }
        if (id === '') {
            tampilkanError('NIM/NIP belum diisi.', 'nim_nip');
            return;
        }
        if (pw === '') {
            tampilkanError('Kata sandi belum diisi.', 'password');
            return;
        }
        if (id.indexOf('@') !== -1) {
            tampilkanError('Login hanya bisa memakai NIM atau NIP, bukan email.', 'nim_nip');
            return;
        }

        sedangKirim = true;
        alertBox.hidden = true;
        setTombol('loading');

        const minimal = tunggu(kurangiGerak ? 0 : 700);

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                return minimal.then(function () { return data; });
            })
            .then(function (data) {
                if (data.ok) {
                    return jalankanTransisi(data);
                }

                sedangKirim = false;
                setTombol('idle');
                tampilkanError(data.error || 'Login gagal. Coba lagi.', data.field || '');
            })
            .catch(function () {
                /* Jika fetch gagal, kirim form biasa (tanpa animasi) */
                form.submit();
            });
    });

    /* ---------- 4. Transisi login berhasil ---------- */

    function jalankanTransisi(data) {
        const layar = document.getElementById('loginTransisi');
        const halaman = document.querySelector('.login-page');

        document.getElementById('ltAvatar').textContent = data.inisial || '';
        document.getElementById('ltNama').textContent = data.nama || '';
        document.getElementById('ltSub').textContent =
            'Menyiapkan dashboard ' + (data.role ? data.role.toLowerCase() : '') + '…';

        /* Penanda untuk animasi masuk di halaman dashboard */
        try {
            sessionStorage.setItem('sipaka_baru_masuk', '1');
        } catch (err) { /* abaikan */ }

        if (kurangiGerak) {
            window.location.href = data.redirect;
            return;
        }

        setTombol('done');

        return tunggu(550).then(function () {
            /* Lingkaran membesar dari tombol Masuk */
            const r = tombol.getBoundingClientRect();
            layar.style.setProperty('--tx', (r.left + r.width / 2) + 'px');
            layar.style.setProperty('--ty', (r.top + r.height / 2) + 'px');

            layar.hidden = false;
            void layar.offsetWidth;
            layar.classList.add('is-open');
            halaman.classList.add('is-leaving');

            return tunggu(2200);
        }).then(function () {
            window.location.href = data.redirect;
        });
    }
})();
