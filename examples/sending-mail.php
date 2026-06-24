<?php
/**
 * Example: Sending Email with the Vertext Mail System
 *
 * The mail system lives in App/Mail/. It uses PHP's built-in mail()
 * or a native SMTP driver - no Composer dependencies required.
 *
 * Configure mail settings in Admin → Settings → Mail before sending.
 */

// ── 1. Simple email ───────────────────────────────────────────────────────────
/*
use App\Mail\Mailer;
use App\Mail\MailMessage;

$message = (new MailMessage())
    ->to('recipient@example.com', 'Jane Doe')
    ->subject('Hello from Vertext')
    ->htmlBody('<p>This is a <strong>HTML</strong> email.</p>')
    ->textBody('This is a plain-text email.');

$mailer = Mailer::make(); // reads settings from DB automatically
if (!$mailer->send($message)) {
    error_log('Mail failed: ' . $mailer->getLastError());
}
*/

// ── 2. Using a template ───────────────────────────────────────────────────────
/*
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

$html = MailTemplate::render('welcome', [
    'userName'  => 'Alice',
    'userEmail' => 'alice@example.com',
    'loginUrl'  => 'https://mysite.test/admin/login',
    'siteName'  => 'My Site',
    'siteUrl'   => 'https://mysite.test',
]);

$message = (new MailMessage())
    ->to('alice@example.com', 'Alice')
    ->subject('Welcome to My Site')
    ->htmlBody($html);

Mailer::make()->send($message);
*/

// ── 3. Sending from inside a controller (non-fatal pattern) ───────────────────
/*
namespace App\Modules\MyModule\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

class MyController extends BaseController
{
    public function store(): void
    {
        // ... save item to DB ...

        // Send notification - failure must not break the user flow
        try {
            $html = MailTemplate::render('my_template', [
                'siteName' => 'My Site',
                'siteUrl'  => $this->baseUrl,
            ]);
            (new MailMessage())
                ->to('admin@example.com')
                ->subject('New item submitted')
                ->htmlBody($html);
            Mailer::make()->send($message);
        } catch (\Throwable) {
            // swallow - notification failure is non-critical
        }

        $this->json(['success' => true, 'message' => 'Item saved.']);
    }
}
*/

// ── 4. Custom template ────────────────────────────────────────────────────────
/*
// Create: App/Mail/Templates/order_confirmation.php

ob_start();
?>
<h2>Order Confirmed</h2>
<p>Hi <?php echo htmlspecialchars($customerName); ?>,</p>
<p>Your order <strong>#<?php echo htmlspecialchars($orderId); ?></strong> has been received.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'Order Confirmation - ' . ($siteName ?? 'Vertext');
include __DIR__ . '/base.php'; // shared HTML email wrapper

// Then render it:
$html = MailTemplate::render('order_confirmation', [
    'customerName' => 'Bob',
    'orderId'      => 'ORD-12345',
    'siteName'     => 'My Shop',
    'siteUrl'      => 'https://myshop.test',
]);
*/
