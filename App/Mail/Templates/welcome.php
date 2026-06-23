<?php
/**
 * Email: welcome to a new user.
 * Variables: $userName, $userEmail, $loginUrl, $siteName, $siteUrl
 */
ob_start();
?>
<h2>Welcome to <?php echo htmlspecialchars($siteName ?? 'Vertext CMS'); ?>!</h2>
<p>Hi <?php echo htmlspecialchars($userName ?? 'there'); ?>,</p>
<p>An account has been created for you. You can log in using the email address below.</p>
<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
  <tr><td style="padding:6px 0;color:#64748b;width:80px;">Email</td><td><?php echo htmlspecialchars($userEmail ?? ''); ?></td></tr>
</table>
<p>Use the button below to sign in and set your password if needed.</p>
<p>
  <a class="btn" href="<?php echo htmlspecialchars($loginUrl ?? '#'); ?>">Sign in to your account</a>
</p>
<p style="font-size:13px;color:#94a3b8;">If you did not expect this email, you can safely ignore it.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'Welcome to ' . ($siteName ?? 'Vertext CMS');
include __DIR__ . '/base.php';
