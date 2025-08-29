# Setting Up Gmail for OTP Email Verification

This guide will help you configure your Gmail account to send OTP verification emails for the password reset functionality.

## Step 1: Edit the mail_credentials.php file

Open the file `config/mail_credentials.php` and replace the placeholder values with your actual Gmail credentials:

```php
// Your Gmail email address
define('GMAIL_USERNAME', 'your_actual_email@gmail.com');

// Your Gmail app password 
define('GMAIL_PASSWORD', 'your_16_character_app_password');

// Name that will appear as the sender
define('GMAIL_SENDER_NAME', 'Your Company Name');
```

## Step 2: Create a Gmail App Password

For security reasons, Google requires you to use an "App Password" instead of your regular account password when sending emails from applications:

1. Enable 2-Step Verification on your Google Account
   - Go to your [Google Account Security Settings](https://myaccount.google.com/security)
   - Select "2-Step Verification" and follow the steps to turn it on

2. Generate an App Password
   - Go to [App Passwords](https://myaccount.google.com/apppasswords)
   - Select "Mail" as the app and "Other" as the device (name it "Job Portal")
   - Click "Generate"
   - Google will display a 16-character password - copy this password
   - Paste this password in the `mail_credentials.php` file as the value for `GMAIL_PASSWORD`

## Step 3: Test Your Configuration

1. Log in as an administrator
2. Navigate to the admin dashboard
3. Access the Email Test page at `/admin/test_email.php`
4. Enter a test email address (this can be any email address where you want to receive the test)
5. Click "Send Test Email with Sample OTP"
6. Check your inbox (including spam folder) to verify the test email was received
7. Verify that the email contains:
   - A sample OTP code
   - Your email address in the recipient field
   - Text at the bottom confirming it was sent to your email address

## Step 4: Verify OTP Email Delivery to Users

When a user requests a password reset:

1. The system checks if the email exists in the database
2. If found, it generates a unique 6-digit OTP code
3. The OTP is stored in the database with an expiration time (15 minutes)
4. The system sends an email to the **user's email address** containing the OTP
5. The user receives the email and enters the OTP on the verification page
6. If the OTP matches and hasn't expired, the user can reset their password

### Important Notes:

- The OTP email is sent ONLY to the email address that the user enters in the forgot password form
- This email must match the email address stored in your database
- For security reasons, we don't reveal whether an email exists in the database or not
- All email addresses are validated before attempting to send the OTP

## Troubleshooting

If emails are not being sent:

1. Make sure you entered the correct Gmail address and app password
2. Check that your Gmail account has 2-Step Verification enabled
3. Ensure the app password was generated correctly
4. Try enabling "Debug Mode" in the test page to see detailed error information
5. Check if your host/server allows outgoing SMTP connections
6. Verify that the PHP mail and openssl extensions are enabled
7. Check the server error logs for any SMTP or mail-related errors

### Common Gmail Issues:

- **Email in Spam Folder**: Ask users to check their spam/junk folders
- **Gmail Blocked Login**: Google may block logins from "less secure apps" - use the App Password method
- **Gmail Rate Limits**: Gmail has limits on how many emails can be sent per day
- **Gmail Security Alert**: You might receive a security alert from Google about a new sign-in

## Get Help

If you continue to experience issues, contact your system administrator or developer for assistance. 