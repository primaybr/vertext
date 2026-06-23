# Mail System

Vertext ships a lightweight mail system in `App/Mail/`. It has no external dependencies — it uses PHP's built-in `mail()` function or a native SMTP driver built on `fsockopen`.

## Classes

| Class | Description |
| ----- | ----------- |
| `App\Mail\Mailer` | Sends a `MailMessage` via the configured driver |
| `App\Mail\MailMessage` | Fluent message builder (to, subject, body, from) |
| `App\Mail\MailTemplate` | Renders an HTML template from `App/Mail/Templates/` |
| `App\Mail\MailerConfig` | Reads mail settings from the `settings` table |

## Configuration

Mail settings are managed through **Admin → Settings → Mail** and stored in the `settings` table (`grp = 'mail'`).

| Setting key | Default | Description |
| ----------- | ------- | ----------- |
| `mail_driver` | `mail` | `mail` (PHP mail()) or `smtp` |
| `mail_host` | — | SMTP hostname |
| `mail_port` | `587` | SMTP port |
| `mail_username` | — | SMTP username |
| `mail_password` | — | SMTP password |
| `mail_encryption` | `tls` | `tls`, `ssl`, or empty |
| `mail_from_address` | — | Sender address (falls back to `admin_email`) |
| `mail_from_name` | — | Sender name (falls back to `site_name`) |

Use the **Send Test Email** button in Admin → Settings → Mail to verify your configuration.

## Sending Mail

```php
use App\Mail\Mailer;
use App\Mail\MailMessage;

$message = (new MailMessage())
    ->to('recipient@example.com', 'Jane Doe')
    ->subject('Hello from Vertext')
    ->htmlBody('<p>This is a <strong>test</strong> email.</p>')
    ->textBody('This is a test email.');

$mailer = Mailer::make(); // reads settings from DB automatically
$ok     = $mailer->send($message);

if (!$ok) {
    error_log($mailer->getLastError());
}
```

`Mailer::make()` is the standard factory — it reads all mail settings from the database and builds the appropriate driver.

## MailMessage API

```php
$msg = (new MailMessage())
    ->to(string $email, string $name = '')        // recipient
    ->subject(string $subject)
    ->htmlBody(string $html)                       // HTML part
    ->textBody(string $text)                       // plain-text part (auto-generated from HTML if omitted)
    ->from(string $email, string $name = '')       // override sender
    ->replyTo(string $email);                      // Reply-To header
```

## HTML Email Templates

`MailTemplate::render()` renders a PHP file from `App/Mail/Templates/` into an HTML string. Templates receive variables via `extract()` and wrap their content in `base.php` (the shared email layout).

```php
use App\Mail\MailTemplate;

$html = MailTemplate::render('welcome', [
    'userName'  => 'Alice',
    'userEmail' => 'alice@example.com',
    'loginUrl'  => 'https://mysite.test/admin/login',
    'siteName'  => 'My Site',
    'siteUrl'   => 'https://mysite.test',
]);
```

### Bundled Templates

| Template | Variables | Used by |
| -------- | --------- | ------- |
| `welcome` | `userName`, `userEmail`, `loginUrl`, `siteName`, `siteUrl` | UsersController — new user welcome |
| `comment_approved` | `authorName`, `postTitle`, `postUrl`, `commentBody`, `siteName`, `siteUrl` | CommentsController — approval notification |
| `comment_pending` | `authorName`, `authorEmail`, `postTitle`, `postUrl`, `moderateUrl`, `commentBody`, `siteName`, `siteUrl` | CommentsController — pending review alert |
| `contact_notification` | `senderName`, `senderEmail`, `subject`, `messageBody`, `submittedAt`, `inboxUrl`, `siteName`, `siteUrl` | Contact module — admin notification |
| `contact_autoreply` | `senderName`, `customMessage`, `siteName`, `siteUrl` | Contact module — visitor auto-reply |

### Creating a Custom Template

Create `App/Mail/Templates/my_template.php`:

```php
<?php ob_start(); ?>
<h2>Hello <?php echo htmlspecialchars($recipientName); ?>!</h2>
<p>Your custom message here.</p>
<?php
$emailContent = ob_get_clean();
$emailTitle   = 'My Email — ' . ($siteName ?? 'Vertext');
include __DIR__ . '/base.php';
```

Then render it with:

```php
$html = MailTemplate::render('my_template', [
    'recipientName' => 'Bob',
    'siteName'      => 'My Site',
    'siteUrl'       => 'https://mysite.test',
]);
```

## Error Handling

`Mailer::send()` returns `bool`. On failure, `getLastError()` returns a descriptive message. All exceptions are caught internally and written to the application log — a failed notification never crashes the user-facing request.

```php
try {
    Mailer::make()->send($msg);
} catch (\Throwable) {
    // never thrown — Mailer catches internally
}
```

The recommended pattern inside module controllers:

```php
try {
    Mailer::make()->send($msg);
} catch (\Throwable) {
    // notification failure must not break the user flow
}
```
