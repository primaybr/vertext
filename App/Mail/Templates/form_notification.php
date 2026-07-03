<?php
/**
 * Email: new form submission notification.
 * Variables: $formName, $labels (id => label), $data (id => value|array), $siteName, $inboxUrl
 */
ob_start();
?>
<h2>New form submission</h2>
<p>A new response was submitted to <strong><?php echo htmlspecialchars($formName ?? 'a form'); ?></strong> on <?php echo htmlspecialchars($siteName ?? 'your site'); ?>.</p>
<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
  <?php foreach (($data ?? []) as $fieldId => $value): ?>
  <tr>
    <td style="padding:6px 8px;color:#64748b;width:35%;vertical-align:top;border-bottom:1px solid #e2e8f0;">
      <?php echo htmlspecialchars($labels[$fieldId] ?? $fieldId); ?>
    </td>
    <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">
      <?php echo nl2br(htmlspecialchars(is_array($value) ? implode(', ', $value) : (string) $value)); ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($inboxUrl ?? '#'); ?>">Open submissions inbox</a>
</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'New submission: ' . ($formName ?? '');
include __DIR__ . '/base.php';
