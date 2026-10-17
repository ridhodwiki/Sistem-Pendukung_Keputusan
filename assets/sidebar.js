// Offcanvas sidebar untuk layar <= 767.98px (lihat assets/style.css)
// shortcut: tombol hanya muncul di mobile, desktop tetap sidebar statis
document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    var toggle = document.querySelector('.sidebar-toggle');
    if (!sidebar || !overlay || !toggle) return;

    function setOpen(open) {
        sidebar.classList.toggle('show', open);
        overlay.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('show'));
    });
    overlay.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
});
