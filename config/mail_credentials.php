<?php
// Gmail credentials for sending OTP emails
// Replace these with your actual Gmail credentials

// Your Gmail email address
define('GMAIL_USERNAME', 'your_email@gmail.com');

// Your Gmail app password 
// To get this: 
// 1. Enable 2-step verification on your Google account
// 2. Go to Google Account > Security > App Passwords
// 3. Select "Mail" as app and "Other" as device (name it "Job Portal")
// 4. Copy the generated 16-character password
define('GMAIL_PASSWORD', 'your_app_password');

// Name that will appear as the sender
define('GMAIL_SENDER_NAME', 'Job Portal Admin');
?> 