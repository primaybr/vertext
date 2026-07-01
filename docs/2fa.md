# Two-Factor Authentication (2FA)

Vertext supports TOTP-based two-factor authentication (RFC 6238) via the **TwoFactor** module. Once installed and enabled, users must enter a time-based one-time code from an authenticator app (such as Google Authenticator or Authy) after entering their password.

---

## Requirements

- The **TwoFactor** module must be installed from Admin - Module Manager.
- The install wizard prompts for a **TOTP issuer name** (displayed in the authenticator app, e.g. "My Site Admin"). This is stored as the `totp_issuer` setting.

---

## Enabling 2FA for Your Account

1. Log in and go to **Admin - My Profile** (`/admin/profile`).
2. Click the **Two-Factor Authentication** link in the profile navigation.
3. On the 2FA setup page (`/admin/profile/2fa`):
   - Scan the QR code with your authenticator app.
   - If you cannot scan, use the manual entry code shown below the QR code.
4. Enter a 6-digit verification code from the app to confirm setup, then click **Enable 2FA**.
5. On success, you are shown your **backup codes**. Save these somewhere safe - each code can only be used once.

---

## Logging In With 2FA

After entering your email and password:

1. You are redirected to the **2FA verification screen** (`/admin/login/2fa`).
2. Enter the 6-digit code from your authenticator app.
3. Optionally check **"Trust this device for 30 days"** to skip the 2FA step on this browser for a month.
4. Click **Verify**.

If a trusted-device cookie is set, the 2FA screen is bypassed automatically.

---

## Backup Codes

Backup codes let you log in when you do not have access to your authenticator app.

- You receive 8 one-time backup codes when you first enable 2FA.
- To regenerate codes, go to **My Profile - 2FA - View Backup Codes** (`/admin/profile/2fa/backup-codes`).
- Each code is shown only once per generation; they are stored as bcrypt hashes.
- Entering a backup code on the 2FA screen consumes it permanently.

---

## Disabling 2FA

1. Go to **Admin - My Profile - Two-Factor Authentication**.
2. Click **Disable 2FA** and confirm.
3. Your TOTP secret and backup codes are deleted. Future logins skip the 2FA step.

---

## Developer Notes

### TotpHelper

`App\CMS\TotpHelper` is the standalone TOTP utility (no external dependencies):

```php
$secret = TotpHelper::generateSecret();           // Base32 secret
$otpUrl = TotpHelper::otpUrl($secret, $email, $issuer); // otpauth:// URI for QR
$valid  = TotpHelper::verifyCode($secret, $code); // bool, 30-second window
```

### Install Setting

| Key | Label | Purpose |
|-----|-------|---------|
| `totp_issuer` | Issuer Name | Shown in the authenticator app alongside the account |

Configure this during module installation or update it in Admin - Settings.

### Database Tables

| Table | Purpose |
|-------|---------|
| `two_factor_secrets` | Per-user TOTP secret (one row per user, replaced on re-setup) |
| `two_factor_backup_codes` | Bcrypt-hashed one-time codes (8 per user) |
