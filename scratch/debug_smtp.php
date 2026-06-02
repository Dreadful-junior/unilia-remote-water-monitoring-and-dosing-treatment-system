<?php
require 'includes/mailer.php';
require_once 'includes/PHPMailer/Exception.php';
require_once 'includes/PHPMailer/PHPMailer.php';
require_once 'includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$config = include('config/email.php');

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = $config['smtp_auth'];
    $mail->Username   = $config['smtp_username'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = $config['smtp_secure'];
    $mail->Port       = $config['smtp_port'];

    // Disable SSL verification for local test
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom($config['from_email'], 'Test');
    $mail->addAddress($config['smtp_username'], 'Me');
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'This is a test email.';

    if($mail->send()) {
        echo "Email sent successfully!";
    }
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
