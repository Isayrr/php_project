# OTP Email Troubleshooting Guide

## Problem: Not Receiving OTP Emails

If you're not receiving the OTP emails when using the "Forgot Password" feature, follow these steps to resolve the issue:

## Quick Solution

1. **Access the Debug Tool**: Go to `/auth/debug_email.php` in your browser
2. **Update Email Configuration**: 
   - Enter your Gmail email address
   - Generate and enter an App Password (explained below)
   - Set a sender name
   - Click "Save Configuration"
3. **Test the Email**: Send a test OTP to verify it works

## Step 1: Check Email Configuration

The most common reason for not receiving OTP emails is that the email credentials have not been properly configured.

1. Open the file `config/mail_credentials.php`
2. Check if it contains placeholder values:

```php
define('GMAIL_USERNAME', 'your_email@gmail.com');
define('GMAIL_PASSWORD', 'your_app_password');
```

If you see these values, they need to be replaced with your actual Gmail credentials.

## Step 2: Set Up Gmail for App Passwords

You **must** use an App Password from Gmail, not your regular account password:

1. **Enable 2-Step Verification**:
   - Go to [Google Account Security](https://myaccount.google.com/security)
   - Enable 2-Step Verification if not already enabled

2. **Generate an App Password**:
   - Go to [App Passwords](https://myaccount.google.com/apppasswords)
   - Select "Mail" as the app
   - Select "Other" as the device (name it "Job Portal")
   - Click "Generate"
   - Copy the 16-character password

## Step 3: Update Your Configuration

Either use the debug tool or manually edit the configuration file:

### Option 1: Using the Debug Tool (Recommended)

1. Go to `/auth/debug_email.php` in your browser
2. Fill in your Gmail email address
3. Enter the App Password you generated
4. Set a sender name
5. Click "Save Configuration"
6. Send a test email to verify it works

### Option 2: Manually Edit the Configuration File

1. Open `config/mail_credentials.php`
2. Replace the placeholders with your actual credentials:

```php
// Your Gmail email address
define('GMAIL_USERNAME', 'your.actual.gmail@gmail.com');

// Your App Password (16 characters, no spaces)
define('GMAIL_PASSWORD', 'abcdefghijklmnop');

// Name that will appear as the sender
define('GMAIL_SENDER_NAME', 'Your Company Name');
```

## Step 4: Test the Configuration

After updating your email configuration:

1. Use the debug tool to send a test email
2. Check both your inbox and spam/junk folders
3. If you receive the test email, try the "Forgot Password" feature again

## Common Issues and Solutions

1. **Emails Going to Spam**: Check your spam/junk folders
2. **Gmail Security Blocks**: Gmail might block login attempts from "less secure apps"
   - Solution: Use App Passwords as explained above
3. **SMTP Connection Failures**: Your server might block outgoing SMTP connections
   - Solution: Ensure your host allows outgoing connections to smtp.gmail.com:587
4. **Invalid Credentials**: Incorrect username or password
   - Solution: Double-check your Gmail address and App Password
5. **Gmail Rate Limits**: Gmail limits how many emails you can send per day
   - Solution: Don't send too many test emails in a short period

## For Developers

If you need to further debug email issues:

1. Enable verbose SMTP debugging in `sendOTPEmail()` function:
   ```php
   $mail->SMTPDebug = 2; // Change from 0 to 2
   ```
2. Check the server error logs for any SMTP or mail-related errors
3. Ensure PHP has the required extensions: OpenSSL, SMTP, and mail

## Support

If you continue to experience issues, contact your system administrator or developer for assistance. 