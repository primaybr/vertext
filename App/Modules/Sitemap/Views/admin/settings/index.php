<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-map me-2 text-primary"></i>Sitemap Settings</h1>
    <p class="vtx-page-desc">Control what's included in <code>/sitemap.xml</code> and <code>/robots.txt</code>.</p>
  </div>
</div>

<div class="vtx-panel" style="max-width:680px;">
  <form method="POST" action="{{baseUrl}}/admin/sitemap/settings/save" data-ajax-form>
    <input type="hidden" name="csrf_token" value="{{csrf_token}}">

    <div class="vtx-panel-head"><span class="vtx-panel-title">Include in Sitemap</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="sitemap_include_pages" name="sitemap_include_pages"
               value="1" <?php echo ($settings['sitemap_include_pages'] ?? '1') !== '0' ? 'checked' : ''; ?>>
        <label class="vtx-label" for="sitemap_include_pages" style="margin:0;">Pages</label>
      </div>

      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="sitemap_include_blog" name="sitemap_include_blog"
               value="1" <?php echo ($settings['sitemap_include_blog'] ?? '1') !== '0' ? 'checked' : ''; ?>>
        <label class="vtx-label" for="sitemap_include_blog" style="margin:0;">Blog posts</label>
      </div>

      <?php if ($eventsEnabled): ?>
      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="sitemap_include_events" name="sitemap_include_events"
               value="1" <?php echo ($settings['sitemap_include_events'] ?? '1') !== '0' ? 'checked' : ''; ?>>
        <label class="vtx-label" for="sitemap_include_events" style="margin:0;">Events</label>
      </div>
      <?php endif; ?>

      <?php if ($galleryEnabled): ?>
      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="sitemap_include_gallery" name="sitemap_include_gallery"
               value="1" <?php echo ($settings['sitemap_include_gallery'] ?? '1') !== '0' ? 'checked' : ''; ?>>
        <label class="vtx-label" for="sitemap_include_gallery" style="margin:0;">Gallery</label>
      </div>
      <?php endif; ?>

      <?php if ($videosEnabled): ?>
      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="sitemap_include_videos" name="sitemap_include_videos"
               value="1" <?php echo ($settings['sitemap_include_videos'] ?? '1') !== '0' ? 'checked' : ''; ?>>
        <label class="vtx-label" for="sitemap_include_videos" style="margin:0;">Videos</label>
      </div>
      <?php endif; ?>

    </div>

    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);"><span class="vtx-panel-title">robots.txt</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-2">
        <label class="vtx-label" for="robots_extra_disallow">Extra Disallow paths</label>
        <textarea class="form-control" id="robots_extra_disallow" name="robots_extra_disallow"
                  rows="4" placeholder="/private/&#10;/drafts/"><?php echo htmlspecialchars($settings['robots_extra_disallow'] ?? ''); ?></textarea>
        <div class="vtx-help">One path per line. <code>Disallow: /admin/</code> is always included automatically.</div>
      </div>

    </div>

    <div class="vtx-panel-foot" style="display:flex;justify-content:flex-end;gap:.5rem;">
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
  </form>
</div>
