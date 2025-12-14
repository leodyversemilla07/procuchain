# Email Verification "Invalid Signature" Fix

## Problem
Users getting "403 Invalid signature" error when clicking email verification links.

## Root Causes

### 1. APP_URL Mismatch (Most Common)
The `APP_URL` in `.env` must **exactly** match your production domain:

```env
# ❌ WRONG
APP_URL=http://localhost
APP_URL=http://procuchain.tech
APP_URL=https://www.procuchain.tech

# ✅ CORRECT (for production)
APP_URL=https://procuchain.tech
```

### 2. APP_KEY Changed
If `APP_KEY` changed after the verification email was sent, old links become invalid.

### 3. Cached Configuration
Old configuration cached in production.

## Solution Steps

### Step 1: Fix APP_URL in Production

SSH into your server and update `.env`:

```bash
# Edit .env file
nano .env

# Update APP_URL to match your domain EXACTLY
APP_URL=https://procuchain.tech
```

### Step 2: Clear All Caches

```bash
# Clear application cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Optimize for production
php artisan optimize
```

### Step 3: Restart Services (if using queue workers)

```bash
# Restart queue workers
php artisan queue:restart

# If using Laravel Octane
php artisan octane:reload

# If using PHP-FPM
sudo systemctl restart php8.3-fpm

# If using supervisor for queues
sudo supervisorctl restart all
```

### Step 4: Test Verification

1. Register a new test account
2. Check the verification email
3. Click the link - should work now
4. If it still fails, check logs: `tail -f storage/logs/laravel.log`

## For Affected Users

Users who received verification emails **before** the fix need to:

1. Request a new verification email from the login page
2. Click "Resend verification email" link
3. The new email will have a valid signature

## Prevention

### In .env (Production)
```env
APP_NAME=Procuchain
APP_ENV=production
APP_DEBUG=false
APP_URL=https://procuchain.tech
APP_KEY=base64:YOUR_KEY_HERE

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=procuchain
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Mail settings
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@procuchain.tech"
MAIL_FROM_NAME="${APP_NAME}"
```

### After Deployment Checklist

```bash
# 1. Always clear caches after deployment
php artisan optimize:clear
php artisan optimize

# 2. Restart queues
php artisan queue:restart

# 3. Test email sending
php artisan tinker
# Then in tinker:
Auth::user()->sendEmailVerificationNotification();
```

## Monitoring

Add to your deployment script:

```bash
#!/bin/bash
# deployment.sh

echo "Deploying Procuchain..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear and rebuild caches
php artisan optimize:clear
php artisan optimize

# Run migrations
php artisan migrate --force

# Restart services
php artisan queue:restart

# Test configuration
php artisan config:show app.url
echo "Deployment complete!"
```

## Debugging

### Check Current Configuration

```bash
# Check APP_URL
php artisan tinker
config('app.url')

# Should output: https://procuchain.tech
```

### View Generated URL

```bash
php artisan tinker
$user = User::find(1);
$url = URL::temporarySignedRoute(
    'verification.verify',
    now()->addMinutes(60),
    ['id' => $user->id, 'hash' => sha1($user->email)]
);
echo $url;
# Should start with: https://procuchain.tech/verify-email/...
```

### Check Logs

```bash
# View recent errors
tail -100 storage/logs/laravel.log | grep -i "signature"

# Monitor live
tail -f storage/logs/laravel.log
```

## Custom Error Page

We've added a custom error page at `resources/views/errors/verification-failed.blade.php` that shows when signature verification fails. Users will see:

- Clear explanation of the error
- Reasons why it might have occurred
- Button to request a new verification link
- Link to contact support

## Support Response Template

When users report this issue:

```
Hi [User],

It looks like your email verification link has expired or was generated with old configuration.

Please follow these steps:

1. Go to https://procuchain.tech/login
2. Enter your email and password
3. Click "Resend verification email"
4. Check your email for the new verification link
5. Click the new link to verify your email

The new link will work correctly. This issue has been resolved on our end.

If you continue to have issues, please reply to this email.

Best regards,
Procuchain Support
```

## Related Files

- `app/Notifications/VerifyEmailNotification.php` - Custom verification notification
- `resources/views/emails/verify-email.blade.php` - Email template
- `resources/views/errors/verification-failed.blade.php` - Error page
- `bootstrap/app.php` - Exception handler for signature errors
- `app/Models/User.php` - `sendEmailVerificationNotification()` override
