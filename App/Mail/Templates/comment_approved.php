<?php
/**
 * Email: comment approved notification sent to the commenter.
 * Variables: $authorName, $postTitle, $postUrl, $commentBody, $siteName, $siteUrl
 */
ob_start();
?>
<h2>Your comment was approved!</h2>
<p>Hi <?php echo htmlspecialchars($authorName ?? 'there'); ?>,</p>
<p>Your comment on <strong><?php echo htmlspecialchars($postTitle ?? 'the post'); ?></strong> has been approved and is now visible to readers.</p>
<div class="notice">
  <?php echo nl2br(htmlspecialchars(mb_substr($commentBody ?? '', 0, 300))); ?>
  <?php if (mb_strlen($commentBody ?? '') > 300): ?>&hellip;<?php endif; ?>
</div>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($postUrl ?? '#'); ?>">View the post</a>
</p>
<p style="font-size:13px;color:#94a3b8;">You received this email because you left a comment on <?php echo htmlspecialchars($siteName ?? 'our site'); ?>.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'Your comment was approved - ' . ($siteName ?? 'Vertext CMS');
include __DIR__ . '/base.php';
