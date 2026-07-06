/* -- front/album.php: lightbox open/close/prev/next + keyboard nav -- */
(function () {
    var items   = Array.from(document.querySelectorAll('#vtx-photo-grid .photo-item'));
    var lb      = document.getElementById('vtx-lightbox');
    var lbImg   = document.getElementById('vtx-lb-img');
    var lbCap   = document.getElementById('vtx-lb-caption');
    var lbCtr   = document.getElementById('vtx-lb-counter');
    var current = 0;

    if (!lb || !items.length) return;

    function open(index) {
        current = ((index % items.length) + items.length) % items.length;
        var el = items[current];
        lbImg.src = el.dataset.url;
        lbImg.alt = el.querySelector('img').alt;
        lbCap.textContent = el.dataset.caption || '';
        lbCtr.textContent = (current + 1) + ' / ' + items.length;
        lb.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lb.classList.remove('is-open');
        lbImg.src = '';
        document.body.style.overflow = '';
    }

    items.forEach(function (el, i) {
        el.addEventListener('click', function () { open(i); });
    });

    document.getElementById('vtx-lb-close').addEventListener('click', close);
    document.getElementById('vtx-lb-prev').addEventListener('click', function () { open(current - 1); });
    document.getElementById('vtx-lb-next').addEventListener('click', function () { open(current + 1); });

    lb.addEventListener('click', function (e) {
        if (e.target === lb) close();
    });

    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('is-open')) return;
        if (e.key === 'Escape')     close();
        if (e.key === 'ArrowLeft')  open(current - 1);
        if (e.key === 'ArrowRight') open(current + 1);
    });
}());
