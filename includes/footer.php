<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<!-- Lucide Icons -->
<script
    src="https://unpkg.com/lucide@1.52.0/dist/umd/lucide.min.js">
</script>

<script>
    lucide.createIcons();
</script>

<!-- Javascript SIPinjam -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const popup = document.getElementById('jadwalPopup');
    const tombol = document.getElementById('tutupJadwalPopup');

    if (!popup) return;

    function tutupPopup() {
        popup.remove();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const popup = document.getElementById('jadwalPopup');
        const tombol = document.getElementById('tutupJadwalPopup');

        if (!popup || !tombol) return;

        tombol.addEventListener('click', function () {
            window.location.href =
                '<?= BASE_URL ?>/mahasiswa/keperluan.php?alat_id=<?= (int) ($_GET["alat_id"] ?? 0) ?>';
        });
    });
    });
</script>
</body>