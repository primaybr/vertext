<style>
.videos-page { padding: 3rem 0; }
.videos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
.video-card { border: 1px solid var(--clr-border, #e5e7eb); border-radius: 8px; overflow: hidden; background: #fff; transition: box-shadow .2s; }
.video-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.video-thumb { position: relative; padding-top: 56.25%; background: #111; overflow: hidden; }
.video-thumb img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
.video-thumb .play-btn {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,.9);
    display: flex; align-items: center; justify-content: center;
    color: var(--clr-accent, #4f46e5); font-size: 1.4rem;
    pointer-events: none;
}
.video-card:hover .play-btn { background: #fff; }
.video-thumb .no-thumb { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #555; font-size: 2.5rem; }
.video-info { padding: .85rem 1rem; }
.video-info h2 { font-size: 1rem; font-weight: 600; margin: 0 0 .25rem; }
.video-info p { font-size: .875rem; color: var(--clr-muted, #6b7280); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.provider-badge { position: absolute; bottom: .5rem; left: .5rem; background: rgba(0,0,0,.6); color: #fff; font-size: .65rem; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; }
</style>

<div class="container videos-page">
    <h1 style="margin-bottom:2rem">Videos</h1>

    <?php if (empty($videos)): ?>
        <p class="text-muted">No videos have been published yet.</p>
    <?php else: ?>
        <div class="videos-grid">
            <?php foreach ($videos as $v): ?>
                <a href="<?= $baseUrl ?>/videos/<?= htmlspecialchars($v['slug']) ?>" class="video-card" style="text-decoration:none;color:inherit">
                    <div class="video-thumb">
                        <?php if ($v['thumbnail_url']): ?>
                            <img src="<?= htmlspecialchars($v['thumbnail_url']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-thumb"><i class="pi pi-video"></i></div>
                        <?php endif; ?>
                        <div class="play-btn"><i class="pi pi-play-circle"></i></div>
                        <span class="provider-badge"><?= htmlspecialchars($v['provider']) ?></span>
                    </div>
                    <div class="video-info">
                        <h2><?= htmlspecialchars($v['title']) ?></h2>
                        <?php if ($v['description']): ?>
                            <p><?= htmlspecialchars(mb_substr(strip_tags($v['description']), 0, 120)) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
