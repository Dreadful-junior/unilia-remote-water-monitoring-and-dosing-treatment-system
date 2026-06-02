<?php
require 'db_connect.php';
require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email_config = include('config/email.php');

echo "<h2>Email Connection Test</h2>";
echo "Testing SMTP connection to: " . $email_config['smtp_host'] . "<br>";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = $email_config['smtp_host'];
    $mail->SMTPAuth   = $email_config['smtp_auth'];
    $mail->Username   = $email_config['smtp_username'];
    $mail->Password   = $email_config['smtp_password'];
    $mail->SMTPSecure = $email_config['smtp_secure'];
    $mail->Port       = $email_config['smtp_port'];

    $mail->setFrom($email_config['from_email'], $email_config['from_name']);
    $mail->addAddress($email_config['smtp_username']); // Send to self

    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'This is a test email to verify SMTP settings.';

    echo "<pre>";
    $mail->send();
    echo "</pre>";
    echo "<h3 style='color:green'>Success! Email sent.</h3>";
} catch (Exception $e) {
    echo "<pre>";
    echo "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
    echo "</pre>";
    echo "<h3 style='color:red'>Failed! Check the error above.</h3>";
}
?>
