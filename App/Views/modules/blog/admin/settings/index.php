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

    <div class="vtx-panel-foot" style="display:flex;justify-content:flex-end;gap:.5rem;">
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
  </form>
</div>
