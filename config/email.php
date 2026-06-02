<?php
// Email Configuration
// Update these settings with your email credentials

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_auth' => true,
    'smtp_username' => 'dalitsonyali@gmail.com',  // Change this to your Gmail
    'smtp_password' => 'skvwrqivgqqikewx',      // Use App Password, not regular password
    'smtp_secure' => 'tls',                      // 'tls' or 'ssl'
    'smtp_port' => 587,                          // 587 for TLS, 465 for SSL
    'from_email' => 'dalitsonyali@gmail.com',      // Change this
    'from_name' => 'Unilia Water Monitoring and Treatment System',
    'base_url' => 'http://192.168.43.248/water%20system'  // Change this to your domain
];

/*
 * Gmail Setup Instructions:
 * 1. Go to your Google Account settings
 * 2. Enable 2-Step Verification
 * 3. Go to App Passwords: https://myaccount.google.com/apppasswords
 * 4. Generate an app password for "Mail"
 * 5. Use that app password in smtp_password above
 * 
 * For other email providers:
 * - Outlook/Hotmail: smtp-mail.outlook.com, Port 587
 * - Yahoo: smtp.mail.yahoo.com, Port 587
 * - Custom SMTP: Use your provider's settings
 */
