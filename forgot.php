<?php
session_start();
require 'db_connect.php';
require_once 'includes/mailer.php';

$message = "";
$msg_type = "";
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
$reset_email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : "";

// Handle Resend OTP
if (isset($_GET['resend']) && $step == 2 && $reset_email) {
    $otp = sprintf("%06d", random_int(0, 999999));
    $expires = date("Y-m-d H:i:s", strtotime('+15 minutes'));
    
    $stmt = $conn->prepare("UPDATE password_resets SET token = ?, expires = ? WHERE email = ?");
    $stmt->bind_param("sss", $otp, $expires, $reset_email);
    
    if ($stmt->execute()) {
        $stmt_user = $conn->prepare("SELECT fullname FROM users WHERE email = ?");
        $stmt_user->bind_param("s", $reset_email);
        $stmt_user->execute();
        $user = $stmt_user->get_result()->fetch_assoc();
        
        $subject = 'Your Reset Code - UniLi Water System';
        $body = "
        <p>Hello " . htmlspecialchars($user['fullname']) . ",</p>
        <p>Your password reset code is:</p>
        <div style='background: #f1f5f9; padding: 20px; text-align: center; border-radius: 12px; margin: 25px 0;'>
            <span style='font-size: 32px; font-weight: 800; letter-spacing: 10px; color: #0ea5e9; font-family: monospace;'>{$otp}</span>
        </div>
        <p style='font-size: 14px; color: #64748b;'>This code will expire in <strong>15 minutes</strong>.</p>
        ";
        
        sendSystemEmail($reset_email, $user['fullname'], $subject, $body);
        $message = "A new code has been sent to your email.";
        $msg_type = "success";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // STEP 1: Request Reset (Send OTP)
    if (isset($_POST['action']) && $_POST['action'] == 'request') {
        $email = trim($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $otp = sprintf("%06d", random_int(0, 999999));
                $expires = date("Y-m-d H:i:s", strtotime('+15 minutes'));
                
                // Clear old tokens
                $conn->query("DELETE FROM password_resets WHERE email = '$email'");
                
                // Store OTP
                $stmt_reset = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
                $stmt_reset->bind_param("sss", $email, $otp, $expires);
                
                if ($stmt_reset->execute()) {
                    $subject = 'Your Reset Code - UniLi Water System';
                    $body = "
                    <p>Hello " . htmlspecialchars($user['fullname']) . ",</p>
                    <p>Someone requested a password reset for your UniLi Water System account. Use the code below to proceed:</p>
                    <div style='background: #f1f5f9; padding: 25px; text-align: center; border-radius: 12px; margin: 25px 0; border: 1px dashed #0ea5e9;'>
                        <span style='font-size: 32px; font-weight: 800; letter-spacing: 10px; color: #0ea5e9; font-family: monospace;'>{$otp}</span>
                    </div>
                    <p style='font-size: 14px; color: #64748b;'>This code will expire in <strong>15 minutes</strong> for security reasons. If you didn't request this, you can safely ignore this email.</p>
                    ";
                    
                    $res = sendSystemEmail($email, $user['fullname'], $subject, $body);
                    if ($res['success']) {
                        $_SESSION['reset_step'] = 2;
                        $_SESSION['reset_email'] = $email;
                        $step = 2;
                        $reset_email = $email;
                        $message = "A 6-digit reset code has been sent to your email.";
                        $msg_type = "success";
                    } else {
                        $message = "Failed to send email. Please check your SMTP settings.";
                        $msg_type = "error";
                    }
                }
            } else {
                // Security: Don't reveal email status
                $message = "If an account exists, a reset code has been sent.";
                $msg_type = "success";
            }
        }
    }
    
    // STEP 2: Verify OTP & Reset Password
    elseif (isset($_POST['action']) && $_POST['action'] == 'reset') {
        $otp = trim($_POST['otp']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        
        if (strlen($password) < 6) {
            $message = "Password must be at least 6 characters.";
            $msg_type = "error";
        } elseif ($password !== $confirm) {
            $message = "Passwords do not match.";
            $msg_type = "error";
        } else {
            $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires > NOW()");
            $stmt->bind_param("ss", $reset_email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update->bind_param("ss", $hashed, $reset_email);
                
                if ($update->execute()) {
                    $conn->query("DELETE FROM password_resets WHERE email = '$reset_email'");
                    unset($_SESSION['reset_step']);
                    unset($_SESSION['reset_email']);
                    $step = 3; // Success state
                    $message = "Password reset successfully! You can now login.";
                    $msg_type = "success";
                }
            } else {
                $message = "Invalid or expired reset code.";
                $msg_type = "error";
            }
        }
    }
}

// Reset process if back to login
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | UniLi Remote Water Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f8fafc;
            padding: 20px;
        }
        
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .icon-box {
            width: 64px;
            height: 64px;
            background: #f0f9ff;
            color: #0ea5e9;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.5rem;
        }
        
        .auth-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.025em;
        }
        
        .auth-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }
        
        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3.25rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }
        
        .otp-input {
            letter-spacing: 0.5em;
            text-align: center;
            padding-left: 1rem !important;
            font-weight: 800;
            font-size: 1.25rem;
        }

        /* Hide browser-native password reveal buttons */
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-input {
            padding-right: 3.5rem;
        }

        .toggle-password {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0;
            font-size: 1.1rem;
            z-index: 10;
        }

        .toggle-password:hover {
            color: #0ea5e9;
        }
        
        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .btn-primary:hover {
            background: #0284c7;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
        
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .back-link:hover { color: #0ea5e9; }
        
        .resend-box {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .resend-box a { color: #0ea5e9; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="icon-box">
                <?php if ($step == 1): ?>
                    <i class="fas fa-key"></i>
                <?php elseif ($step == 2): ?>
                    <i class="fas fa-shield-alt"></i>
                <?php else: ?>
                    <i class="fas fa-check-circle"></i>
                <?php endif; ?>
            </div>
            <h1 class="auth-title">
                <?php 
                    if ($step == 1) echo "Forgot Password";
                    elseif ($step == 2) echo "Verify Identity";
                    else echo "Success!";
                ?>
            </h1>
            <p class="auth-subtitle">
                <?php 
                    if ($step == 1) echo "No worries, we'll send you a 6-digit reset code.";
                    elseif ($step == 2) echo "Enter the code sent to <b>$reset_email</b>";
                    else echo "Your password has been updated successfully.";
                ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <i class="fas <?php echo ($msg_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>" style="margin-top: 3px;"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="request">
                <label class="form-label">Institutional Email</label>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required autofocus>
                </div>
                <button type="submit" class="btn-primary">
                    <span>Send Reset Code</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>

        <?php elseif ($step == 2): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset">
                
                <label class="form-label">6-Digit Code</label>
                <div class="input-group">
                    <input type="text" name="otp" class="form-input otp-input" placeholder="000000" maxlength="6" required autofocus autocomplete="off">
                </div>

                <label class="form-label">New Password</label>
                <div class="input-group">
                    <div class="password-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-input" placeholder="Min. 6 characters" required minlength="6">
                        <button type="button" class="toggle-password" data-target="password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <div class="password-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Repeat password" required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Reset Password</span>
                    <i class="fas fa-shield-alt"></i>
                </button>
            </form>
            
            <div class="resend-box">
                Didn't receive the code? <a href="forgot.php?resend=1">Resend Code</a>
            </div>
            <a href="forgot.php?cancel=1" class="back-link">
                <i class="fas fa-times"></i> Cancel Reset
            </a>

        <?php else: ?>
            <a href="login.php" class="btn-primary">
                <span>Login with New Password</span>
                <i class="fas fa-sign-in-alt"></i>
            </a>
        <?php endif; ?>
    </div>

    <script>
        // Simple client-side UX
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('.btn-primary');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    btn.disabled = true;
                }
            });
        }

        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });
    </script>
</body>
</html>
