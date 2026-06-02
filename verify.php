<?php
/**
 * Verify Email API
 * Handles account activation via verification token
 */
require 'db_connect.php';

$message = "";
$status = "info";

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);

    // Find user with this token
    $res = $conn->query("SELECT id, fullname FROM users WHERE verification_token = '$token' LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $user_id = $user['id'];

        // Mark as verified and clear token
        $update = $conn->query("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = $user_id");

        if ($update) {
            // Send Welcome Email
            require_once 'includes/mailer.php';
            $user_info = $conn->query("SELECT email, fullname FROM users WHERE id = $user_id")->fetch_assoc();
            
            if ($user_info) {
                $emailBody = "
                    <h2 style='color: #2563eb; margin-top: 0;'>Welcome to the Team, " . htmlspecialchars($user_info['fullname']) . "!</h2>
                    <p>Your account has been successfully verified and activated.</p>
                    <p>You now have full access to the <strong>UniLi Water Monitoring & Treatment Dashboard</strong>. Here you can monitor water quality in real-time, manage automated dosing, and receive critical system alerts.</p>
                    <p style='margin-top: 25px;'>To get started, please log in using the link below:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . (include('config/email.php'))['base_url'] . "/login.php' 
                           style='display: inline-block; background: #2563eb; color: white; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: bold;'>
                           Access Dashboard
                        </a>
                    </div>
                    <p style='font-size: 0.9rem; color: #64748b;'>If you have any questions or need technical support, please contact the system administrator.</p>";

                sendSystemEmail(
                    $user_info['email'], 
                    $user_info['fullname'], 
                    "Welcome to UniLi Water System!", 
                    $emailBody
                );
            }

            $message = "Congratulations " . htmlspecialchars($user['fullname']) . "! Your email has been verified successfully. Your account is now <strong>pending administrative approval</strong>. You will be notified once you can log in.";
            $status = "success";
        } else {
            $message = "An error occurred during verification. Please try again or contact support.";
            $status = "error";
        }
    } else {
        $message = "Invalid or expired verification token. If you've already verified your account, please try logging in.";
        $status = "error";
    }
} else {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | UniLi Water System</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; background: #f0f4f8; margin: 0; padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .verify-card {
            background: white; padding: 2.5rem; border-radius: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center;
            max-width: 450px; width: 100%; border: 1px solid #e2e8f0;
        }
        .icon-box {
            width: 70px; height: 70px; border-radius: 50%;
            margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
        }
        .icon-success { background: #ecfdf5; color: #10b981; }
        .icon-error { background: #fef2f2; color: #ef4444; }
        .icon-info { background: #eff6ff; color: #3b82f6; }
        
        h1 { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 1rem; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
        
        .btn-login {
            display: inline-block; background: #2563eb; color: white;
            padding: 0.85rem 2rem; border-radius: 12px; font-weight: 700;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="icon-box icon-<?php echo $status; ?>">
            <i class="fas <?php echo ($status === 'success') ? 'fa-check-circle' : (($status === 'error') ? 'fa-times-circle' : 'fa-info-circle'); ?>"></i>
        </div>
        <h1><?php echo ($status === 'success') ? 'Verification Successful' : 'Verification Status'; ?></h1>
        <p><?php echo $message; ?></p>
        <a href="login.php" class="btn-login">Proceed to Login</a>
    </div>
</body>
</html>
