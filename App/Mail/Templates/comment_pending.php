<?php
/**
 * Email: new comment pending review - sent to admin/post author.
 * Variables: $authorName, $authorEmail, $postTitle, $postUrl, $moderateUrl,
 *            $commentBody, $siteName, $siteUrl
 */
ob_start();
?>
<h2>New comment pending review</h2>
<p>A new comment has been submitted on <strong><?php echo htmlspecialchars($postTitle ?? 'a post'); ?></strong> and is awaiting moderation.</p>
<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
  <tr><td style="padding:6px 0;color:#64748b;width:100px;">Author</td><td><?php echo htmlspecialchars($authorName ?? ''); ?></td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Email</td><td><?php echo htmlspecialchars($authorEmail ?? ''); ?></td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Post</td><td><a href="<?php echo htmlspecialchars($postUrl ?? '#'); ?>"><?php echo htmlspecialchars($postTitle ?? ''); ?></a></td></tr>
</table>
<div class="notice">
  <?php echo nl2br(htmlspecialchars(mb_substr($commentBody ?? '', 0, 500))); ?>
  <?php if (mb_strlen($commentBody ?? '') > 500): ?>&hellip;<?php endif; ?>
</div>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($moderateUrl ?? '#'); ?>">Review in admin panel</a>
</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'New comment pending review - ' . ($siteName ?? 'Vertext CMS');
include __DIR__ . '/base.php';
