/* Media Module - Admin JS: upload toggle + grid reload */
(function () {
    var uploadBtn = document.getElementById('vtx-media-upload-btn');
    if (!uploadBtn) return; // Not on the media library page

    uploadBtn.addEventListener('click', function () {
        var zone = document.getElementById('vtx-upload-zone');
        if (zone) zone.style.display = zone.style.display === 'none' ? '' : 'none';
    });

    // Reload grid after upload - guard so this only fires on the media page
    document.addEventListener('vtx:upload:done', function () {
        if (document.getElementById('vtx-media-grid')) location.reload();
    });
}());
