<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-sparkle me-2 text-primary"></i>Blog Setup</h1>
    <p class="vtx-page-desc">Configure your blog before publishing. You can change any of these in Blog Settings later.</p>
  </div>
</div>

<div class="vtx-panel" style="max-width:640px;">
  <form method="POST" action="{{baseUrl}}/admin/blog/setup/complete">
    <input type="hidden" name="csrf_token" value="{{csrf_token}}">

    <!-- -- Step 1: URL path ------------------------------------------- -->
    <div class="vtx-panel-head">
      <span class="vtx-panel-title">
        <span class="vtx-step-badge">1</span> Blog URL Path
      </span>
    </div>
    <div class="vtx-panel-body">
      <p style="font-size:.875rem;color:var(--ps-text-muted);margin-bottom:1rem;">
        Choose the URL path where your blog will be accessible. Leave blank to serve from the site root&nbsp;<code>/</code>.
      </p>

      <div class="vtx-field mb-2">
        <label class="vtx-label" for="blog_base_path">Blog path</label>
        <div class="input-group" style="max-width:420px;">
          <span class="input-group-text" style="font-size:.875rem;">yoursite.com/</span>
          <input class="form-control" type="text" id="blog_base_path" name="blog_base_path"
                 value="<?php echo htmlspecialchars($settings['blog_base_path'] ?? 'blog'); ?>"
                 placeholder="blog"
                 autocomplete="off"
                 pattern="[a-z0-9\-_\/]*"
                 title="Lowercase letters, numbers, hyphens and underscores only">
        </div>
      </div>

      <div id="path-preview" class="vtx-help" style="margin-top:.25rem;">
        Preview: <strong id="path-preview-val"></strong>
      </div>

      <div class="alert alert-info mt-3" style="font-size:.8125rem;">
        <strong>Root mount:</strong> Setting the path to blank serves the blog at <code>/</code>.
        Make sure no other page or route is registered at the root before doing this.
      </div>
    </div>

    <!-- -- Step 2: Blog identity -------------------------------------- -->
    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);">
      <span class="vtx-panel-title">
        <span class="vtx-step-badge">2</span> Blog Identity
      </span>
    </div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="blog_title">Blog title</label>
        <input class="form-control" type="text" id="blog_title" name="blog_title"
               value="<?php echo htmlspecialchars($settings['blog_title'] ?? 'Blog'); ?>"
               placeholder="My Blog">
      </div>

      <div class="vtx-field mb-0">
        <label class="vtx-label" for="blog_description">Description <span class="text-muted fw-normal">(optional)</span></label>
        <textarea class="form-control" id="blog_description" name="blog_description"
                  rows="2" placeholder="A short description shown in meta tags"><?php echo htmlspecialchars($settings['blog_description'] ?? ''); ?></textarea>
      </div>

    </div>

    <!-- -- Step 3: Defaults ------------------------------------------- -->
    <div class="vtx-panel-head" style="border-top:1px solid var(--ps-border);">
      <span class="vtx-panel-title">
        <span class="vtx-step-badge">3</span> Defaults
      </span>
    </div>
    <div class="vtx-panel-body">

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="posts_per_page">Posts per page</label>
        <input class="form-control" type="number" id="posts_per_page" name="posts_per_page"
               min="1" max="50" style="max-width:120px;"
               value="<?php echo htmlspecialchars($settings['posts_per_page'] ?? '10'); ?>">
      </div>

      <div class="vtx-field mb-0" style="display:flex;align-items:center;gap:.75rem;">
        <input class="form-check-input" type="checkbox" id="comments_enabled" name="comments_enabled"
               value="1" <?php echo ($settings['comments_enabled'] ?? '1') ? 'checked' : ''; ?>>
        <label class="vtx-label" for="comments_enabled" style="margin:0;">Enable comments on posts</label>
      </div>

    </div>

    <!-- -- Footer ------------------------------------------------------ -->
    <div class="vtx-panel-foot" style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
      <a href="{{baseUrl}}/admin/blog" class="btn btn-link text-muted" style="font-size:.875rem;">
        Skip for now
      </a>
      <button type="submit" class="btn btn-primary px-4">
        <i class="pi pi-check me-1"></i>Finish Setup
      </button>
    </div>
  </form>
</div>
