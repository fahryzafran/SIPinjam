console.log('SIPAKA APP.JS BERJALAN');

const sidebar = document.querySelector('.sidebar');
const toggleButton = document.querySelector('.sidebar-toggle');

if (sidebar && toggleButton) {

    toggleButton.addEventListener('click', function () {

        const isCollapsed = sidebar.classList.toggle('collapsed');

        const icon = toggleButton.querySelector('[data-lucide]');

        if (icon) {
            icon.setAttribute(
                'data-lucide',
                isCollapsed ? 'chevron-right' : 'chevron-left'
            );

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

    });

}
