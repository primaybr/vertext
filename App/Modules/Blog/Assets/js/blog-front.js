/* Blog Module - Front-end JS: reading list + comment reply toggle */

/* -- post.php: reading-list localStorage toggle -- */
(function () {
    var btn = document.getElementById('reading-list-btn');
    if (!btn) return;

    var KEY        = 'vtx_reading_list';
    var POST_ID    = btn.dataset.postId;
    var POST_TITLE = btn.dataset.postTitle;
    var POST_SLUG  = btn.dataset.postSlug;
    var POST_URL   = btn.dataset.postUrl;

    function getList() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
    function saveList(l) { localStorage.setItem(KEY, JSON.stringify(l)); }
    function isSaved() { return getList().some(function (p) { return p.id === POST_ID; }); }

    var icon  = document.getElementById('rl-icon');
    var label = document.getElementById('rl-label');

    function updateBtn() {
        if (isSaved()) {
            btn.classList.add('saved');
            icon.className = 'pi pi-check';
            label.textContent = 'Saved to Reading List';
        } else {
            btn.classList.remove('saved');
            icon.className = 'pi pi-menu';
            label.textContent = 'Save to Reading List';
        }
    }

    updateBtn();
    btn.addEventListener('click', function () {
        var list = getList();
        if (isSaved()) {
            saveList(list.filter(function (p) { return p.id !== POST_ID; }));
        } else {
            list.push({ id: POST_ID, title: POST_TITLE, slug: POST_SLUG, url: POST_URL });
            saveList(list);
        }
        updateBtn();
    });
}());

/* -- post.php: comment reply-form toggle (invoked via inline onclick attributes
   in the server-rendered comment markup, so this stays a plain global function) -- */
function toggleReplyForm(id) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('open');
}
