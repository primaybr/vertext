<?php
/**
 * Email: auto-reply to contact form submitter.
 * Variables: $senderName, $customMessage, $siteName, $siteUrl
 */
ob_start();
?>
<h2>Thanks for getting in touch!</h2>
<p>Hi <?php echo htmlspecialchars($senderName ?? 'there'); ?>,</p>
<p>We received your message and will get back to you as soon as possible.</p>
<?php if (!empty($customMessage)): ?>
<div class="notice">
  <?php echo nl2br(htmlspecialchars($customMessage)); ?>
</div>
<?php endif; ?>
<p style="font-size:13px;color:#94a3b8;">This is an automated reply. Please do not respond to this email directly.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'We received your message — ' . ($siteName ?? 'Vertext CMS');
include __DIR__ . '/base.php';
