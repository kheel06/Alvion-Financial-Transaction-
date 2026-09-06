<?php
/**
 * Email Helper Functions using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';

/**
 * Send verification code email
 * 
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param string $verification_code The verification code to send
 * @return array ['success' => bool, 'message' => string]
 */
function sendVerificationCodeEmail($to_email, $to_name, $verification_code) {
    if (!EMAIL_ENABLED) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled. Please enable it in config/email.php'
        ];
    }

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Embed logo as attachment (CID method - more reliable for email clients)
        $logo_path = __DIR__ . '/../assets/img/alvion-logo-removebg.png';
        $logo_cid = '';
        if (file_exists($logo_path)) {
            $logo_cid = 'alvion_logo_' . time();
            $mail->addEmbeddedImage($logo_path, $logo_cid, 'alvion-logo.png', 'base64', 'image/png');
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Login Verification Code - ' . SITE_NAME;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 0; }
                .header { background-color: rgb(221, 221, 221); color: white; padding: 30px 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .logo { max-width: 180px; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
                .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .code-box { background-color: white; border: 2px dashed #0d9488; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .code { font-size: 32px; font-weight: bold; color: #0d9488; letter-spacing: 5px; font-family: monospace; }
                .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
                .warning { color: #dc2626; font-size: 14px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">' . 
                    ($logo_cid ? '<img src="cid:' . $logo_cid . '" alt="' . SITE_NAME . ' Logo" class="logo">' : '<h1>' . SITE_NAME . '</h1>') . '
                </div>
                <div class="content">
                    <h2>Login Verification Code</h2>
                    <p>Hello ' . htmlspecialchars($to_name) . ',</p>
                    <p>You have requested to log in to your account. Please use the following verification code to complete your login:</p>
                    
                    <div class="code-box">
                        <div class="code">' . htmlspecialchars($verification_code) . '</div>
                    </div>
                    
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you did not request this code, please ignore this email or contact support if you have concerns.</p>
                    
                    <p class="warning"><strong>⚠️ Security Notice:</strong> Never share this code with anyone. ' . SITE_NAME . ' staff will never ask for your verification code.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Hello {$to_name},\n\nYour login verification code is: {$verification_code}\n\nThis code will expire in 10 minutes.\n\nIf you did not request this code, please ignore this email.\n\n© " . date('Y') . " " . SITE_NAME . ". All rights reserved.";

        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Verification code sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Generate a random 6-digit verification code
 * 
 * @return string
 */
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send password reset email
 * 
 * @param string $to_email Recipient email address (original email for sending)
 * @param string $to_name Recipient name
 * @param string $reset_token The password reset token
 * @param string|null $url_email Optional: Email to use in URL (normalized). If not provided, uses $to_email
 * @return array ['success' => bool, 'message' => string]
 */
function sendPasswordResetEmail($to_email, $to_name, $reset_token, $url_email = null) {
    if (!EMAIL_ENABLED) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled. Please enable it in config/email.php'
        ];
    }

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients - use original email for sending
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Embed logo as attachment
        $logo_path = __DIR__ . '/../assets/img/alvion-logo-removebg.png';
        $logo_cid = '';
        if (file_exists($logo_path)) {
            $logo_cid = 'alvion_logo_' . time();
            $mail->addEmbeddedImage($logo_path, $logo_cid, 'alvion-logo.png', 'base64', 'image/png');
        }

        // In email_helper.php, in sendPasswordResetEmail function:
// Generate reset link in query string format: reset-password.php?token={token}&email={email}
$email_for_url = $url_email ?? $to_email;
// Hex tokens are URL-safe, so no encoding needed for token
// Email should be encoded for URL safety
$reset_link = BASE_URL . '/auth/reset-password.php?token=' . $reset_token . '&email=' . urlencode($email_for_url);

// Debug logging
error_log("Password reset email - Sending to: " . $to_email . ", URL email: " . $email_for_url . ", Token: " . substr($reset_token, 0, 30) . "...");
error_log("Generated reset link: " . $reset_link);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - ' . SITE_NAME;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 0; }
                .header { background-color: #008080; color: white; padding: 30px 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .logo { max-width: 180px; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
                .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .button { display: inline-block; padding: 12px 30px; background-color: #008080; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .button:hover { background-color: #0f766e; }
                .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
                .warning { color: #dc2626; font-size: 14px; margin-top: 20px; }
                .link-box { background-color: white; border: 1px solid #e5e7eb; padding: 15px; margin: 20px 0; border-radius: 5px; word-break: break-all; font-size: 12px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">' . 
                    ($logo_cid ? '<img src="cid:' . $logo_cid . '" alt="' . SITE_NAME . ' Logo" class="logo">' : '<h1>' . SITE_NAME . '</h1>') . '
                </div>
                <div class="content">
                    <h2>Password Reset Request</h2>
                    <p>Hello ' . htmlspecialchars($to_name) . ',</p>
                    <p>We received a request to reset your password for your ' . SITE_NAME . ' account.</p>
                    <p>Click the button below to reset your password:</p>
                    
                    <div style="text-align: center;">
                        <a href="' . htmlspecialchars($reset_link) . '" class="button">Reset Password</a>
                    </div>
                    
                    <p>Or copy and paste this link into your browser:</p>
                    <div class="link-box">' . htmlspecialchars($reset_link) . '</div>
                    
                    <p>This link will expire in <strong>60 minutes</strong>.</p>
                    <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <p class="warning"><strong>⚠️ Security Notice:</strong> Never share this link with anyone. ' . SITE_NAME . ' staff will never ask for your password reset link.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Hello {$to_name},\n\nWe received a request to reset your password for your " . SITE_NAME . " account.\n\nClick the following link to reset your password:\n{$reset_link}\n\nThis link will expire in 60 minutes.\n\nIf you did not request a password reset, please ignore this email.\n\n© " . date('Y') . " " . SITE_NAME . ". All rights reserved.";

        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Password reset email sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Password reset email sending failed: " . $mail->ErrorInfo);
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $mail->ErrorInfo
        ];
    }
}

