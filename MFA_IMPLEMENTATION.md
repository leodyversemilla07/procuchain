# Multi-Factor Authentication (MFA) Implementation

## Overview
This implementation adds TOTP (Time-based One-Time Password) Multi-Factor Authentication to the ProcuChain system using Google Authenticator or similar apps. The system provides an additional security layer while maintaining user experience.

## Features Implemented

### 1. Database Schema
- **Table**: Modified `users` table
- **New Fields**: 
  - `google2fa_secret` (encrypted secret key for TOTP)
  - `mfa_enabled` (boolean flag)
  - `mfa_enabled_at` (timestamp when MFA was enabled)
  - `backup_codes` (JSON array of hashed backup codes)
  - `backup_codes_generated_at` (timestamp when backup codes were generated)

### 2. Backend Components

#### Models
- **User Model**: Extended with MFA-related methods
  - `hasMfaEnabled()`: Check if MFA is active
  - `generateBackupCodes()`: Create 8 backup codes for emergency access
  - `verifyBackupCode()`: Validate and consume backup codes
  - `getRemainingBackupCodesCount()`: Get count of unused backup codes

#### Controllers
- **MfaController**: Handles all MFA operations
  - `edit()`: Display MFA settings page
  - `setup()`: Generate QR code and secret for setup
  - `enable()`: Enable MFA after code verification
  - `disable()`: Disable MFA with verification
  - `regenerateBackupCodes()`: Create new backup codes
  - `verify()`: Handle MFA verification during login

#### Middleware
- **RequireMfa**: Intercepts authenticated requests to enforce MFA verification
  - Checks if user has MFA enabled
  - Redirects to MFA verification if not completed
  - Allows MFA settings access to prevent circular dependency

#### Services Integration
- **LoginTrackingService**: Enhanced to work with MFA flow
- **Google2FA**: TOTP generation and verification
- **QR Code Generator**: Visual QR codes for easy setup

### 3. Frontend Components

#### Settings Page (`/settings/mfa`)
- **MFA Status Card**: Shows current authentication status
- **Enable MFA Section**: 
  - QR code generation and display
  - Secret key manual entry option
  - TOTP code verification
  - Password confirmation
- **Disable MFA Section**:
  - TOTP or backup code verification
  - Password confirmation
- **Backup Codes Management**:
  - Display remaining backup codes count
  - Generate new backup codes
  - Download/copy backup codes functionality
  - Security warnings and instructions

#### MFA Verification Page (`/mfa/verify`)
- Clean, focused interface for code entry
- Support for both TOTP codes and backup codes
- Clear instructions and error handling
- Automatic redirect after verification

### 4. Security Features

#### TOTP Implementation
- Uses industry-standard RFC 6238 TOTP algorithm
- 30-second time windows with 6-digit codes
- Secure secret generation and storage
- Time synchronization tolerance

#### Backup Codes
- 8 single-use backup codes per user
- SHA-256 hashed storage for security
- Automatic removal after use
- Regeneration capability with password confirmation

#### Session Management
- MFA verification tracked per session
- Automatic logout on MFA disable
- Session invalidation on security changes
- Rate limiting integration

### 5. User Experience

#### Setup Flow
1. User navigates to MFA settings
2. Clicks "Get Started" to generate QR code
3. Scans QR code with authenticator app
4. Enters verification code and password
5. Receives backup codes for safekeeping
6. MFA is now active

#### Login Flow (MFA Enabled)
1. User enters email and password
2. System validates credentials
3. User redirected to MFA verification page
4. User enters TOTP code or backup code
5. System completes authentication
6. User redirected to appropriate dashboard

#### Disable Flow
1. User navigates to MFA settings
2. Enters TOTP code or backup code
3. Confirms with password
4. MFA is disabled and secrets cleared

## Routes Added

### Settings Routes
```php
// MFA Management (no MFA middleware to avoid circular dependency)
Route::get('settings/mfa', [MfaController::class, 'edit'])->name('mfa.edit');
Route::post('settings/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
Route::post('settings/mfa/enable', [MfaController::class, 'enable'])->name('mfa.enable');
Route::post('settings/mfa/disable', [MfaController::class, 'disable'])->name('mfa.disable');
Route::post('settings/mfa/backup-codes/regenerate', [MfaController::class, 'regenerateBackupCodes'])->name('mfa.backup-codes.regenerate');
```

### MFA Verification Routes
```php
// MFA Verification (outside auth middleware)
Route::get('mfa/verify', function () {
    if (!session('mfa_user_id')) {
        return redirect()->route('login');
    }
    return Inertia::render('auth/mfa-verify');
})->name('mfa.verify.form');

Route::post('mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify');
```

## Dependencies

### PHP Packages
- `pragmarx/google2fa-laravel`: TOTP implementation
- `simplesoftwareio/simple-qrcode`: QR code generation

### Database Migration
- Migration file: `2025_06_20_075906_add_mfa_fields_to_users_table.php`
- Adds MFA-related fields to users table

## Configuration

### Environment Variables
No additional environment variables required. Uses existing app configuration.

### App Configuration
- App name used in TOTP issuer field
- Session management for MFA verification state
- Rate limiting integration for security

## Security Considerations

### Protection Measures
1. **Secret Storage**: Google2FA secrets are stored encrypted
2. **Backup Codes**: Hashed with SHA-256, single-use only
3. **Session Security**: MFA verification tied to session
4. **Rate Limiting**: Failed attempts tracked and limited
5. **Password Confirmation**: Required for sensitive operations
6. **Automatic Cleanup**: Secrets cleared when MFA disabled

### Best Practices Implemented
1. **Time Window Tolerance**: Accounts for clock drift
2. **Secure Random Generation**: Cryptographically secure secrets
3. **Proper Error Handling**: No information disclosure
4. **Audit Trail**: All MFA actions logged
5. **Graceful Degradation**: Backup codes for device loss
6. **User Education**: Clear instructions and warnings

## Testing

### Test Coverage
- MFA settings page access
- QR code and secret generation
- MFA enable/disable with TOTP codes
- MFA enable/disable with backup codes
- Login flow with MFA verification
- Backup code generation and usage
- Error handling and validation

### Test Commands
```bash
# Run all MFA tests
php artisan test tests/Feature/MfaTest.php

# Run with coverage
php artisan test tests/Feature/MfaTest.php --coverage
```

## Maintenance

### Regular Tasks
1. Monitor backup code usage patterns
2. Review MFA adoption rates
3. Update TOTP algorithm if needed
4. Backup QR code generation logs

### Monitoring
- Track MFA enable/disable events
- Monitor backup code consumption
- Alert on unusual MFA patterns
- Regular security audits

## Future Enhancements

### Planned Features
1. **SMS Backup**: SMS-based backup codes
2. **Hardware Keys**: WebAuthn/FIDO2 support
3. **App Notifications**: Push notifications for login attempts
4. **Admin Controls**: Force MFA for specific roles
5. **Recovery Options**: Admin-assisted recovery
6. **Multiple Devices**: Support for multiple authenticator devices

### Security Improvements
1. **Risk-Based Auth**: Adaptive authentication based on risk
2. **Device Registration**: Trusted device management
3. **Geolocation**: Location-based verification
4. **Biometric Integration**: Fingerprint/face verification

## Troubleshooting

### Common Issues
1. **Time Sync**: Ensure server and device clocks are synchronized
2. **QR Code Display**: Check SVG rendering in browsers
3. **Session Conflicts**: Clear browser sessions if issues persist
4. **Database Schema**: Verify migration ran successfully

### Support Procedures
1. **Lost Device**: Use backup codes for access
2. **No Backup Codes**: Admin can disable MFA for user
3. **Code Invalid**: Check time synchronization
4. **Setup Issues**: Regenerate QR code and try again

This comprehensive MFA implementation provides enterprise-grade security while maintaining excellent user experience and following security best practices.
