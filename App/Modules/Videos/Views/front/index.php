<div class="container videos-page">
    <div class="videos-page-header">
        <h1>Videos</h1>
    </div>

    <?php if (empty($videos)): ?>
        <p class="videos-empty">No videos have been published yet.</p>
    <?php else: ?>
        <div class="videos-grid">
            <?php foreach ($videos as $v): ?>
                <a href="<?= $baseUrl ?>/videos/<?= htmlspecialchars($v['slug']) ?>" class="video-card">
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
