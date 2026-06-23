<style>
.video-single { padding: 2.5rem 0 4rem; }
.video-embed-wrap { position: relative; padding-top: 56.25%; background: #000; border-radius: 8px; overflow: hidden; margin-bottom: 2rem; }
.video-embed-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
.video-poster { position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; }
.video-poster img { width: 100%; height: 100%; object-fit: cover; }
.poster-play {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,.92);
    display: flex; align-items: center; justify-content: center;
    color: var(--clr-accent, #4f46e5); font-size: 1.8rem; transition: transform .15s;
}
.video-poster:hover .poster-play { transform: translate(-50%,-50%) scale(1.08); }
.video-back { display: inline-flex; align-items: center; gap: .4rem; font-size: .875rem; color: var(--clr-muted, #6b7280); text-decoration: none; margin-bottom: 1.5rem; }
.video-back:hover { color: var(--clr-accent, #4f46e5); }
.video-title { font-size: 1.75rem; font-weight: 700; margin: 0 0 .5rem; }
.video-provider { display: inline-block; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; padding: 2px 8px; border-radius: 4px; background: #f3f4f6; color: #6b7280; margin-bottom: 1rem; }
.video-description { color: var(--clr-text, #111827); line-height: 1.75; max-width: 680px; }
</style>

<div class="container video-single">
    <a href="<?= $baseUrl ?>/videos" class="video-back">
        <i class="pi pi-arrow-left"></i> All Videos
    </a>

    <div class="video-embed-wrap" id="videoWrap">
        <?php if (!empty($video['embed_iframe']) && (!empty($video['thumbnail_url']))): ?>
            <!-- Lazy load — show poster, load iframe on click -->
            <div class="video-poster" id="videoPoster">
                <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="<?= htmlspecialchars($video['title']) ?>">
                <div class="poster-play"><i class="pi pi-play-circle"></i></div>
            </div>
        <?php elseif (!empty($video['embed_iframe'])): ?>
            <?= $video['embed_iframe'] ?>
        <?php endif; ?>
    </div>

    <h1 class="video-title"><?= htmlspecialchars($video['title']) ?></h1>
    <span class="video-provider"><?= htmlspecialchars($video['provider']) ?></span>

    <?php if (!empty($video['description'])): ?>
        <div class="video-description"><?= nl2br(htmlspecialchars($video['description'])) ?></div>
    <?php endif; ?>
</div>

<script>
(function () {
    const poster = document.getElementById('videoPoster');
    if (!poster) return;
    const embed = <?= json_encode($video['embed_iframe'] ?? '') ?>;
    poster.addEventListener('click', function () {
        this.remove();
        document.getElementById('videoWrap').insertAdjacentHTML('beforeend', embed);
    });
})();
</script>
