/* Videos module - admin JS */

/* -- admin/videos/index.php: reload list on CRUD success -- */
/* Guarded to only fire on the videos list page - this script now loads on
   every admin page, and vtx:crud:success is a shared event dispatched by
   any module's CRUD modal, so an unscoped listener here would reload the
   page after unrelated saves elsewhere in the admin. */
document.addEventListener('vtx:crud:success', function () {
    if (document.getElementById('vtx-videos-index')) window.location.reload();
});
