<style>
  .album-back { font-size: .875rem; margin-bottom: 2rem; }
  .album-back a { color: #6b7280; }
  .album-header { margin-bottom: 2rem; }
  .album-header h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 .375rem; }
  .album-header p { margin: 0; color: #6b7280; }
  .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .75rem; }
  .photo-item { aspect-ratio: 1; overflow: hidden; border-radius: 6px; background: #f3f4f6; cursor: pointer; position: relative; }
  .photo-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .2s; display: block; }
  .photo-item:hover img { transform: scale(1.05); }
  .photo-item .photo-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0); transition: background .2s; display: flex; align-items: flex-end; padding: .5rem; }
  .photo-item:hover .photo-overlay { background: rgba(0,0,0,.2); }
  .photo-caption { font-size: .75rem; color: #fff; display: none; }
  .photo-item:hover .photo-caption { display: block; }

  /* Lightbox */
  .vtx-lightbox { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.92); align-items: center; justify-content: center; }
  .vtx-lightbox.is-open { display: flex; }
  .vtx-lightbox img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 4px; }
  .vtx-lb-close { position: absolute; top: 1rem; right: 1.25rem; color: #fff; font-size: 2rem; cursor: pointer; background: none; border: none; line-height: 1; }
  .vtx-lb-close:hover { color: #ccc; }
  .vtx-lb-arrow { position: absolute; top: 50%; transform: translateY(-50%); color: #fff; font-size: 2.5rem; cursor: pointer; background: none; border: none; line-height: 1; padding: 0 .75rem; }
  .vtx-lb-arrow:hover { color: #ccc; }
  .vtx-lb-prev { left: .5rem; }
  .vtx-lb-next { right: .5rem; }
  .vtx-lb-caption { position: absolute; bottom: 1.25rem; left: 50%; transform: translateX(-50%); color: #fff; font-size: .9375rem; text-align: center; max-width: 80%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .vtx-lb-counter { position: absolute; top: 1.25rem; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,.6); font-size: .8125rem; }
</style>

<div class="container">
  <div class="album-back">
    <a href="<?php echo $baseUrl; ?>/gallery">← Gallery</a>
  </div>

  <div class="album-header">
    <h1><?php echo htmlspecialchars($gallery['title']); ?></h1>
    <?php if (!empty($gallery['description'])): ?>
    <p><?php echo htmlspecialchars($gallery['description']); ?></p>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
  <p style="color:#9ca3af;text-align:center;padding:3rem 0;">No photos in this album yet.</p>
  <?php else: ?>
  <div class="photo-grid" id="vtx-photo-grid">
    <?php foreach ($items as $i => $item): ?>
    <div class="photo-item"
         data-index="<?php echo $i; ?>"
         data-url="<?php echo htmlspecialchars($item['url']); ?>"
         data-caption="<?php echo htmlspecialchars($item['caption'] ?? ''); ?>">
      <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
           alt="<?php echo htmlspecialchars($item['alt_text'] ?? ''); ?>"
           loading="lazy">
      <div class="photo-overlay">
        <?php if (!empty($item['caption'])): ?>
        <span class="photo-caption"><?php echo htmlspecialchars($item['caption']); ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div class="vtx-lightbox" id="vtx-lightbox" role="dialog" aria-modal="true">
  <button class="vtx-lb-close" id="vtx-lb-close" aria-label="Close">&times;</button>
  <button class="vtx-lb-arrow vtx-lb-prev" id="vtx-lb-prev" aria-label="Previous">&#8249;</button>
  <img src="" alt="" id="vtx-lb-img">
  <button class="vtx-lb-arrow vtx-lb-next" id="vtx-lb-next" aria-label="Next">&#8250;</button>
  <div class="vtx-lb-counter" id="vtx-lb-counter"></div>
  <div class="vtx-lb-caption" id="vtx-lb-caption"></div>
</div>

<script>
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
</script>
