/* -- front/single.php: click-to-load embed (poster -> iframe swap) -- */
(function () {
    var poster = document.getElementById('videoPoster');
    if (!poster) return;
    var embed = poster.dataset.embedIframe || '';
    poster.addEventListener('click', function () {
        this.remove();
        document.getElementById('videoWrap').insertAdjacentHTML('beforeend', embed);
    });
})();
