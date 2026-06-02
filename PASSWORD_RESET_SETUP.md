# Password Reset System Setup Guide

## Overview
This system includes a complete password reset functionality with email notifications using PHPMailer.

## Installation Steps

### 1. Install PHPMailer
Run this command in your project directory:
```bash
composer require phpmailer/phpmailer
```

### 2. Configure Email Settings
Edit `config/email.php` and update the following:
- `smtp_username`: Your Gmail address
- `smtp_password`: Your Gmail App Password (NOT your regular password)
- `from_email`: Your email address
- `base_url`: Your website URL (change `localhost/water system` to your domain)

### 3. Gmail App Password Setup
1. Go to your Google Account: https://myaccount.google.com
2. Enable 2-Step Verification (if not already enabled)
3. Go to App Passwords: https://myaccount.google.com/apppasswords
4. Select "Mail" and "Other (Custom name)"
5. Enter "UniLi Water System" as the name
6. Click "Generate"
7. Copy the 16-character password and paste it in `config/email.php`

### 4. Run Database Setup
Visit: `http://localhost/water system/setup_db.php`
This will create the `password_resets` table automatically.

## Files Created

1. **forgot.php** - Request password reset page
2. **reset.php** - Reset password with token validation
3. **config/email.php** - Email configuration file
4. **setup_db.php** - Updated to include password_resets table

## Features

✅ Secure token-based password reset
✅ Email validation
✅ Token expiration (1 hour)
✅ Password strength indicator
✅ Password match validation
✅ HTML email templates
✅ Security best practices (doesn't reveal if email exists)
✅ Matches your system's design style

## Testing

1. Go to login page
2. Click "Forgot password?"
3. Enter your email address
4. Check your email (and spam folder)
5. Click the reset link
6. Enter new password
7. Login with new password

## Troubleshooting

### Email not sending?
- Check `config/email.php` settings
- Verify App Password is correct (not regular password)
- Check PHP error logs
- Try different SMTP settings for your email provider

### Token expired?
- Tokens expire after 1 hour
- Request a new reset link

### Database errors?
- Make sure `setup_db.php` has been run
- Check database connection in `db_connect.php`

## Security Notes

- Tokens are cryptographically secure (32 bytes)
- Tokens expire after 1 hour
- Used tokens are deleted immediately
- System doesn't reveal if email exists (prevents email enumeration)
- Passwords are hashed using PHP's password_hash()
