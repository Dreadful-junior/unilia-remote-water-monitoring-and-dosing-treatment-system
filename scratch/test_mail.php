<?php
require 'includes/mailer.php';

$toEmail = 'dalitsonyali@gmail.com';
$toName = 'Test User';
$subject = 'System Connectivity Test — ' . date('H:i:s');
$body = '<h1>Test Successful</h1><p>The SMTP mail server is correctly configured and communicating with PHPMailer.</p>';

echo "Attempting to send email to $toEmail...\n";
$result = sendSystemEmail($toEmail, $toName, $subject, $body);

if ($result['success']) {
    echo "SUCCESS: Email sent successfully.\n";
} else {
    echo "FAILED: " . $result['error'] . "\n";
}
?>
