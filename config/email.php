<?php
/**
 * Email Configuration for PHPMailer
 * 
 * Configure your SMTP settings here
 * For Gmail: Use App Password (not regular password)
 * For other providers: Use their SMTP settings
 */

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP host
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'alvionhospital38@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'epje dxmv ynsk mqhc'); // Your email password or app password
define('SMTP_FROM_EMAIL', 'alvionhospital38@gmail.com'); // From email address
define('SMTP_FROM_NAME', SITE_NAME); // From name
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// Enable/Disable email sending (useful for development)
define('EMAIL_ENABLED', true);

