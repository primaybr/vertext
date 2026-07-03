<?php
/**
 * Email: site member email verification.
 * Variables: $userName, $verifyUrl, $siteName
 */
ob_start();
?>
<h2>Confirm your email address</h2>
<p>Hi <?php echo htmlspecialchars($userName ?? 'there'); ?>,</p>
<p>Thanks for creating an account on <?php echo htmlspecialchars($siteName ?? 'our site'); ?>. Click the button below to verify your email address and activate your account.</p>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($verifyUrl ?? '#'); ?>">Verify email address</a>
</p>
<p style="font-size:13px;color:#94a3b8;">If you did not create this account, you can safely ignore this email.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'Confirm your email address';
include __DIR__ . '/base.php';
