<div class="vtx-error-page">
  <div class="vtx-error-code">404</div>
  <h1 class="vtx-error-title">Page Not Found</h1>
  <p class="vtx-error-desc">The page you&rsquo;re looking for doesn&rsquo;t exist or has been moved.</p>
  <div class="vtx-error-actions">
    <a href="<?php echo htmlspecialchars($baseUrl ?: '/'); ?>" class="btn btn-primary">Go to Homepage</a>
    <button type="button" class="btn btn-outline-secondary" data-history-back>Go Back</button>
  </div>
</div>
<script src="<?php echo htmlspecialchars($baseUrl . '/assets/js/error-page.js'); ?>"></script>
