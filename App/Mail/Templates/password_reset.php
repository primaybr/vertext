<?php
/**
 * Email: admin password reset link.
 * Variables: $userName, $resetUrl, $siteName, $expiryHours
 */
ob_start();
?>
<h2>Reset your password</h2>
<p>Hi <?php echo htmlspecialchars($userName ?? 'there'); ?>,</p>
<p>We received a request to reset the password for your <?php echo htmlspecialchars($siteName ?? 'Vertext CMS'); ?> admin account. Click the button below to choose a new password.</p>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($resetUrl ?? '#'); ?>">Reset password</a>
</p>
<p style="font-size:13px;color:#64748b;">This link expires in <?php echo (int) ($expiryHours ?? 24); ?> hours and can only be used once.</p>
<p style="font-size:13px;color:#94a3b8;">If you did not request a password reset, you can safely ignore this email - your password will not change.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'Reset your password';
include __DIR__ . '/base.php';
