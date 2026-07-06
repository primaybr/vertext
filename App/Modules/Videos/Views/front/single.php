<div class="container video-single">
    <nav class="video-breadcrumb">
        <a href="<?= $baseUrl ?>/videos">Videos</a>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($video['title']) ?></span>
    </nav>

    <div class="video-embed-wrap" id="videoWrap">
        <?php if (!empty($video['embed_iframe']) && (!empty($video['thumbnail_url']))): ?>
            <!-- Lazy load - show poster, load iframe on click -->
            <div class="video-poster" id="videoPoster" data-embed-iframe="<?php echo htmlspecialchars($video['embed_iframe'] ?? '', ENT_QUOTES); ?>">
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
