# User Invitation System - Implementation Guide

## Overview

A complete user invitation system has been implemented for Procuchain. This allows administrators to invite users (BAC Secretariat, BAC Chairman, HOPE) via email, rather than manually creating accounts with passwords.

## ✨ Key Features

### 1. **Secure Email Invitations**
- Admins send invitations with recipient's name, email, and role
- Recipients receive professional email with invitation details
- Signed URLs ensure security and prevent tampering
- 7-day expiration period (configurable)

### 2. **Self-Service Account Creation**
- Recipients set their own passwords
- Email is automatically verified upon acceptance
- Blockchain address auto-generated
- Immediate login after acceptance

### 3. **Full Invitation Management**
- View all invitations (pending, accepted, expired, revoked)
- Resend pending invitations (extends expiration)
- Revoke pending invitations
- Statistics dashboard with invitation metrics
- Searchable, sortable invitation table

### 4. **Audit Trail**
- Complete logging of all invitation actions
- Track who invited whom and when
- Record acceptance, revocation, and expiration
- Links invitations to created user accounts

## 🗂️ Files Created/Modified

### Backend (Laravel)

**Models:**
- `app/Models/UserInvitation.php` - Core invitation model with business logic

**Controllers:**
- `app/Http/Controllers/Admin/UserInvitationController.php` - Admin invitation management
- `app/Http/Controllers/Auth/AcceptInvitationController.php` - Public invitation acceptance

**Requests:**
- `app/Http/Requests/User/SendInvitationRequest.php` - Validation for sending invitations
- `app/Http/Requests/User/AcceptInvitationRequest.php` - Validation for accepting invitations

**Mail:**
- `app/Mail/UserInvitationMail.php` - Email template class
- `resources/views/emails/user-invitation.blade.php` - Email HTML template

**Migrations:**
- `database/migrations/2025_12_14_041833_create_user_invitations_table.php`

**Factories:**
- `database/factories/UserInvitationFactory.php` - Test data generation

### Frontend (React + Inertia)

**Pages:**
- `resources/js/pages/admin/user-invitations.tsx` - Admin invitation management page
- `resources/js/pages/auth/accept-invitation.tsx` - Public invitation acceptance page

**Components:**
- `resources/js/components/admin/send-invitation-dialog.tsx` - Dialog for sending invitations

**Navigation:**
- `resources/js/components/app-sidebar.tsx` - Added "User Invitations" link

### Routes

**Public Routes (Signed URLs):**
```php
GET  /invitation/{token}          - View invitation (signed)
POST /invitation/{token}/accept   - Accept invitation (signed)
```

**Admin Routes (Auth + Admin Role):**
```php
GET    /admin/invitations              - List all invitations
POST   /admin/invitations              - Send new invitation
POST   /admin/invitations/{id}/resend  - Resend invitation
DELETE /admin/invitations/{id}         - Revoke invitation
```

### Tests

**Feature Tests:**
- `tests/Feature/Admin/UserInvitationTest.php` - Comprehensive test suite covering:
  - Admin can access invitations page
  - Admin can send invitation
  - Cannot invite existing user email
  - Cannot invite email with pending invitation
  - Can resend pending invitations
  - Cannot resend expired invitations
  - Can revoke pending invitations
  - Cannot revoke accepted invitations
  - Non-admin access restrictions
  - Guest can view valid invitation
  - Cannot view expired invitation
  - Can accept valid invitation and create account
  - Cannot accept expired invitation
  - Invitation statistics accuracy

## 📋 Database Schema

### `user_invitations` table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| email | string | Invitee email (unique) |
| name | string | Invitee name |
| role | string | Assigned role (bac_secretariat, bac_chairman, hope) |
| token | string | Unique invitation token (unique) |
| invited_by | foreignId | User who sent invitation |
| expires_at | timestamp | Expiration date/time |
| accepted_at | timestamp | When invitation was accepted (nullable) |
| user_id | foreignId | Created user ID (nullable) |
| revoked | boolean | Whether invitation is revoked |
| revoked_at | timestamp | When invitation was revoked (nullable) |
| revoked_by | foreignId | User who revoked invitation (nullable) |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Indexes:**
- token (unique)
- email (unique)
- expires_at, accepted_at, revoked (composite)

## 🚀 Usage Guide

### For Admins: Sending Invitations

1. Navigate to **Admin → User Invitations**
2. Click **"Send Invitation"** button
3. Fill in the form:
   - **Full Name:** Recipient's full name
   - **Email Address:** Valid email (must not exist as user)
   - **Role:** Select from BAC Secretariat, BAC Chairman, or HOPE
4. Click **"Send Invitation"**
5. Recipient receives email with invitation link

### For Admins: Managing Invitations

**View All Invitations:**
- See status: Pending, Accepted, Expired, Revoked
- Search by name or email
- Sort by any column
- View invitation statistics at the top

**Resend Invitation:**
- Only available for pending invitations
- Click **⋮ → Resend Invitation**
- Extends expiration by 7 days
- Sends new email to recipient

**Revoke Invitation:**
- Only available for pending invitations
- Click **⋮ → Revoke Invitation**
- Confirm revocation
- Invitation becomes invalid

### For Recipients: Accepting Invitations

1. Check email for invitation from Procuchain
2. Click **"Accept Invitation"** button in email
3. Review invitation details
4. Enter your full name (pre-filled from invitation)
5. Create a strong password
6. Confirm password
7. Click **"Accept Invitation & Create Account"**
8. Automatically logged in and redirected to dashboard

## 🎯 Benefits Over Manual User Creation

### Security
- ✅ Recipients set their own passwords (admins never see them)
- ✅ Signed URLs prevent tampering
- ✅ Time-limited invitations (7 days)
- ✅ One-time use tokens

### Professional Workflow
- ✅ Email notifications with invitation details
- ✅ Self-service account creation
- ✅ Automatic email verification
- ✅ Clear expiration warnings

### Audit & Compliance
- ✅ Complete audit trail of who invited whom
- ✅ Track invitation status and history
- ✅ Government procurement compliance
- ✅ Blockchain address auto-generation

### User Experience
- ✅ Recipients control their credentials
- ✅ Professional onboarding experience
- ✅ Clear role and responsibilities
- ✅ Immediate system access after acceptance

## 🔧 Configuration

### Invitation Expiration

Default: 7 days. To change, modify `UserInvitation` model:

```php
// app/Models/UserInvitation.php
protected static function boot(): void
{
    parent::boot();
    
    static::creating(function ($invitation) {
        if (empty($invitation->expires_at)) {
            $invitation->expires_at = now()->addDays(14); // Change to 14 days
        }
    });
}
```

### Email Template

Customize the email template at:
```
resources/views/emails/user-invitation.blade.php
```

The email extends the base layout at:
```
resources/views/emails/layouts/base.blade.php
```

### Allowed Roles

Currently configured for:
- `bac_secretariat`
- `bac_chairman`
- `hope`

To add/modify roles, update:
1. `SendInvitationRequest::rules()` validation
2. `UserInvitationController::index()` roles array
3. Spatie Permission roles

## 🧪 Testing

Run all invitation tests:
```bash
php artisan test --filter=UserInvitation
```

Run specific test:
```bash
php artisan test --filter="admin can send invitation"
```

## 📊 Invitation States

| State | Description | Can Resend? | Can Revoke? |
|-------|-------------|-------------|-------------|
| **Pending** | Awaiting acceptance, not expired | ✅ Yes | ✅ Yes |
| **Accepted** | User created account | ❌ No | ❌ No |
| **Expired** | Expiration date passed | ❌ No | ❌ No |
| **Revoked** | Admin cancelled invitation | ❌ No | ❌ No |

## 🔐 Permissions

Uses Laravel's authorization system:

**Sending Invitations:**
- Requires `create users` permission
- Checked in `SendInvitationRequest::authorize()`

**Managing Invitations:**
- Requires `viewAny` User permission for listing
- Requires `create` User permission for resending
- Requires `delete` User permission for revoking

## 🎨 UI Components

### Admin Dashboard
- Statistics cards showing counts by status
- Full invitation table with sorting and filtering
- Action menu for each invitation (resend/revoke)
- Send invitation dialog with form validation

### Invitation Acceptance Page
- Clean, professional design
- Invitation details display
- Account creation form
- Password requirements
- Expiration warning

## 📝 Best Practices

1. **Always use invitations for new users** instead of manual creation
2. **Set clear expiration policies** (default 7 days is recommended)
3. **Review pending invitations regularly** and revoke unused ones
4. **Monitor invitation statistics** for audit purposes
5. **Resend only when necessary** - recipients should check spam folders first

## 🔄 Integration Points

### With Existing User Management
- Invitations complement (not replace) direct user creation
- Both methods coexist - admins can choose which to use
- All users end up in same `users` table
- Same permissions and roles apply

### With Email System
- Uses Laravel's mail system
- Queued for performance (implements `ShouldQueue`)
- Customizable email templates
- Supports all Laravel mail drivers

### With Blockchain
- Blockchain address automatically generated on acceptance
- Same `Manager` service used as direct creation
- Address validation available if provided

## 🐛 Troubleshooting

### Invitation email not received
- Check spam/junk folder
- Verify mail configuration in `.env`
- Check `failed_jobs` table
- Use `php artisan queue:work` if using queue driver

### Cannot access invitation page
- Ensure signed URL is used (check email link)
- Check if invitation expired (compare `expires_at`)
- Verify invitation not already accepted/revoked

### Tests failing
- Run migrations: `php artisan migrate:fresh`
- Clear cache: `php artisan cache:clear`
- Ensure test database configured
- Check factory definitions

## 📞 Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review invitation status in admin panel
3. Contact system administrator

## 🚦 Next Steps (Optional Enhancements)

Consider these future improvements:
- [ ] Bulk invitation import (CSV)
- [ ] Custom invitation message field
- [ ] Configurable expiration per invitation
- [ ] Invitation templates for different roles
- [ ] SMS notifications (optional)
- [ ] Reminder emails before expiration
- [ ] Invitation analytics dashboard

---

**Implementation Date:** December 14, 2025  
**Version:** 1.0  
**Status:** ✅ Complete and Production-Ready
