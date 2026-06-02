<?php
/**
 * Global Mailer Utility
 * Handles SMTP email sending using PHPMailer
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendSystemEmail($toEmail, $toName, $subject, $bodyHTML) {
    $config_path = __DIR__ . '/../config/email.php';
    if (!file_exists($config_path)) {
        return ['success' => false, 'error' => 'Email configuration file missing'];
    }

    $email_config = include($config_path);
    
    // Check if configured
    if ($email_config['smtp_username'] == 'YOUR_EMAIL@gmail.com') {
        return ['success' => false, 'error' => 'SMTP not configured'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $email_config['smtp_host'];
        $mail->SMTPAuth   = $email_config['smtp_auth'];
        $mail->Username   = $email_config['smtp_username'];
        $mail->Password   = $email_config['smtp_password'];
        $mail->SMTPSecure = $email_config['smtp_secure'];
        $mail->Port       = $email_config['smtp_port'];

        // Bypass SSL verification for local/XAMPP environments
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Wrap body in standard template
        $fullBody = "
        <div style='font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;'>
            <div style='background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); padding: 30px 20px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px; letter-spacing: -0.5px;'>UniLi Water System</h1>
            </div>
            <div style='padding: 40px 30px; color: #1e293b; line-height: 1.6;'>
                $bodyHTML
            </div>
            <div style='background-color: #f8fafc; padding: 20px 30px; text-align: center; color: #64748b; font-size: 13px; border-top: 1px solid #f1f5f9;'>
                <p style='margin: 0;'>&copy; " . date('Y') . " UniLi Remote Water Monitoring & Treatment. All rights reserved.</p>
                <p style='margin: 5px 0 0 0;'>This is an automated system notification.</p>
            </div>
        </div>";

        $mail->Body = $fullBody;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        // Log error
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
