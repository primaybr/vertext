<?php
/**
 * Base email HTML wrapper.
 * Usage: include this file; set $emailTitle, $emailContent, $siteName, $siteUrl before including.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($emailTitle ?? ''); ?></title>
<style>
body{margin:0;padding:0;background:#f4f4f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:15px;color:#333}
.wrapper{max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.header{background:#1e293b;padding:24px 32px;text-align:center}
.header h1{margin:0;color:#fff;font-size:20px;font-weight:600;letter-spacing:.3px}
.body{padding:32px}
.footer{background:#f8fafc;padding:16px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
.footer a{color:#64748b;text-decoration:none}
.btn{display:inline-block;padding:10px 24px;background:#3b82f6;color:#fff!important;text-decoration:none;border-radius:6px;font-size:14px;font-weight:500;margin:16px 0}
.notice{background:#f0f9ff;border-left:4px solid #3b82f6;padding:12px 16px;border-radius:4px;margin:16px 0;font-size:13px}
h2{margin:0 0 12px;font-size:18px;color:#1e293b}
p{margin:0 0 12px;line-height:1.6;color:#475569}
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1><?php echo htmlspecialchars($siteName ?? 'Vertext CMS'); ?></h1>
  </div>
  <div class="body">
    <?php echo $emailContent ?? ''; ?>
  </div>
  <div class="footer">
    &copy; <?php echo date('Y'); ?> <a href="<?php echo htmlspecialchars($siteUrl ?? '#'); ?>"><?php echo htmlspecialchars($siteName ?? 'Vertext CMS'); ?></a>
    &nbsp;&middot;&nbsp; Powered by <a href="https://github.com/primaybr/vertext">Vertext CMS</a>
  </div>
</div>
</body>
</html>
