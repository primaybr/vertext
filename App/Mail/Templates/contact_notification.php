<?php
/**
 * Email: new contact form submission - sent to admin.
 * Variables: $senderName, $senderEmail, $subject, $messageBody,
 *            $submittedAt, $inboxUrl, $siteName, $siteUrl
 */
ob_start();
?>
<h2>New contact form submission</h2>
<p>A visitor has submitted the contact form on <strong><?php echo htmlspecialchars($siteName ?? 'your site'); ?></strong>.</p>
<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
  <tr><td style="padding:6px 0;color:#64748b;width:90px;">Name</td><td><?php echo htmlspecialchars($senderName ?? ''); ?></td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Email</td><td><a href="mailto:<?php echo htmlspecialchars($senderEmail ?? ''); ?>"><?php echo htmlspecialchars($senderEmail ?? ''); ?></a></td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Subject</td><td><?php echo htmlspecialchars($subject ?? '(no subject)'); ?></td></tr>
  <tr><td style="padding:6px 0;color:#64748b;">Received</td><td><?php echo htmlspecialchars($submittedAt ?? ''); ?></td></tr>
</table>
<div class="notice">
  <?php echo nl2br(htmlspecialchars($messageBody ?? '')); ?>
</div>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($inboxUrl ?? '#'); ?>">View in admin inbox</a>
</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'New contact message - ' . ($siteName ?? 'Vertext CMS');
include __DIR__ . '/base.php';
