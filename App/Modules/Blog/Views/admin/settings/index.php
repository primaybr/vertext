<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-settings me-2 text-primary"></i>Blog Settings</h1>
    <p class="vtx-page-desc">Configure your blog's behaviour and defaults.</p>
  </div>
</div>

<div class="vtx-panel" style="max-width:680px;">
  <form method="POST" action="{{baseUrl}}/admin/blog/settings/save" data-ajax-form>
    <input type="hidden" name="csrf_token" value="{{csrf_token}}">

    <div class="vtx-panel-head"><span class="vtx-panel-title">General</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="blog_title">Blog Title</label>
        <input class="form-control" type="text" id="blog_title" name="blog_title"
               value="<?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?>">
      </div>

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="blog_description">Description</label>
        <textarea class="form-control" id="blog_description" name="blog_description"
                  rows="2"><?php echo htmlspecialchars($settings['blog_description'] ?? ''); ?></textarea>
        <div class="vtx-help">Used as the meta description for the blog index page.</div>
      </div>

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="posts_per_page">Posts Per Page</label>
        <input class="form-control" type="number" id="posts_per_page" name="posts_per_page"
               min="1" max="50"
               value="<?php echo htmlspecialchars($settings['posts_per_page'] ?? '10'); ?>"
               style="max-width:120px;">
      </div>

    </div>

    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);"><span class="vtx-panel-title">Comments</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="comments_enabled" name="comments_enabled"
               value="1" <?php echo ($settings['comments_enabled'] ?? '1') ? 'checked' : ''; ?>>
        <label class="vtx-label" for="comments_enabled" style="margin:0;">Enable comments on posts</label>
      </div>

      <div class="vtx-field mb-3" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="comments_require_approval" name="comments_require_approval"
               value="1" <?php echo ($settings['comments_require_approval'] ?? '1') ? 'checked' : ''; ?>>
        <label class="vtx-label" for="comments_require_approval" style="margin:0;">Hold new comments for approval</label>
      </div>

    </div>

    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);"><span class="vtx-panel-title">SEO &amp; Social</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="og_default_image">Default Open Graph Image URL</label>
        <input class="form-control" type="text" id="og_default_image" name="og_default_image"
               value="<?php echo htmlspecialchars($settings['og_default_image'] ?? ''); ?>"
               placeholder="https://…">
        <div class="vtx-help">Used when a post has no featured image.</div>
      </div>

    </div>

    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);"><span class="vtx-panel-title">Route &amp; URL</span></div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-2">
        <label class="vtx-label" for="blog_base_path">Blog base path</label>
        <div class="input-group" style="max-width:420px;">
          <span class="input-group-text" style="font-size:.875rem;">yoursite.com/</span>
          <input class="form-control" type="text" id="blog_base_path" name="blog_base_path"
                 value="<?php echo htmlspecialchars($settings['blog_base_path'] ?? 'blog'); ?>"
                 placeholder="blog"
                 autocomplete="off"
                 pattern="[a-z0-9\-_\/]*"
                 title="Lowercase letters, numbers, hyphens and underscores only">
        </div>
        <div class="vtx-help">Leave blank to serve the blog from <code>/</code> (site root).</div>
      </div>

      <!-- SEO warning - hidden until path value changes -->
      <div id="blog-path-seo-warning" class="alert alert-warning mt-3" style="display:none;font-size:.875rem;">
        <strong><i class="pi pi-exclamation-triangle me-1"></i>SEO notice:</strong>
        Changing the blog path will break existing search engine links and any external URLs pointing to your blog.
        Choose how to handle traffic to the <strong id="old-path-label"></strong> path:

        <div class="mt-3" style="display:flex;flex-direction:column;gap:.5rem;">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="path_change_mode"
                   id="pcm_redirect" value="redirect" checked>
            <label class="form-check-label" for="pcm_redirect">
              <strong>Add 301 redirect</strong> - keep old path working, visitors and search engines are sent to the new path
              <span class="vtx-tag success ms-1" style="font-size:.75rem;">Recommended</span>
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="path_change_mode"
                   id="pcm_permanent" value="permanent">
            <label class="form-check-label" for="pcm_permanent">
              <strong>Change only, no redirect</strong> - old path simply stops working (use when the old path had no real traffic)
            </label>
          </div>
        </div>
      </div>

    </div>

    <div class="vtx-panel-foot" style="display:flex;justify-content:flex-end;gap:.5rem;">
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
  </form>
</div>

<script>
(function () {
  var pathInput    = document.getElementById('blog_base_path');
  var warning      = document.getElementById('blog-path-seo-warning');
  var oldPathLabel = document.getElementById('old-path-label');
  var originalPath = pathInput ? pathInput.value.trim() : '';

  function checkChange() {
    if (!pathInput || !warning) return;
    var current = pathInput.value.trim().replace(/^\/+|\/+$/g, '');
    var changed  = current !== originalPath;
    warning.style.display = changed ? '' : 'none';
    if (changed && oldPathLabel) {
      oldPathLabel.textContent = originalPath ? '/' + originalPath : '/';
    }
  }

  if (pathInput) {
    pathInput.addEventListener('input', checkChange);
  }
})();
</script>
