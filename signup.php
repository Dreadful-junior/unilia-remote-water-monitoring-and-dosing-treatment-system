<?php
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Check if empty
    if (empty($email)) {
        $message = "Email field must not be empty.";
    } 
    // 2. No spaces
    else if (strpos($email, ' ') !== false) {
        $message = "Email must not contain spaces.";
    }
    // 3. Min/Max Length
    else if (strlen($email) < 5) {
        $message = "Email is too short (min 5 characters).";
    }
    else if (strlen($email) > 254) {
        $message = "Email is too long (max 254 characters).";
    }
    // 4. Valid format (includes @)
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email format.";
    } 
    else {
        // 5. Valid domain check (basic)
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, "MX") && !checkdnsrr($domain, "A")) {
            $message = "Email domain is invalid or unreachable.";
        } 
        else if ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } 
        // --- PASSWORD VALIDATION RULES ---
        else if (strlen($password) < 8) {
            $message = "Password is too short (min 8 characters).";
        }
        else if (strlen($password) > 64) {
            $message = "Password is too long (max 64 characters).";
        }
        else if (!preg_match('/[A-Z]/', $password)) {
            $message = "Password must contain at least one uppercase letter.";
        }
        else if (!preg_match('/[a-z]/', $password)) {
            $message = "Password must contain at least one lowercase letter.";
        }
        else if (!preg_match('/[0-9]/', $password)) {
            $message = "Password must contain at least one number.";
        }
        else if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $message = "Password must contain at least one special character.";
        }
        else if ($password === $email || $password === $fullname) {
            $message = "Password cannot be the same as your email or name.";
        }
        // ---------------------------------
        else {
            // 6. Convert to lowercase before uniqueness check and saving
            $email = strtolower($email);
            $email_escaped = $conn->real_escape_string($email);

            // 7. Check if email already exists (Uniqueness)
            $check_email = "SELECT id FROM users WHERE email = '$email_escaped'";
            $result = $conn->query($check_email);

            if ($result->num_rows > 0) {
                $message = "This email is already registered.";
            } else {
                // 8. Generate Verification Token
                $token = bin2hex(random_bytes(32));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (fullname, email, password, verification_token, is_verified) 
                        VALUES ('$fullname', '$email_escaped', '$hashed_password', '$token', 0)";

                if ($conn->query($sql) === TRUE) {
                    // Send Verification Email
                    require_once 'includes/mailer.php';
                    $email_config = file_exists('config/email.php') ? include('config/email.php') : [];
                    $base_url = $email_config['base_url'] ?? 'http://localhost/water%20system';
                    
                    $verify_link = "{$base_url}/verify.php?token={$token}";

                    $subject = 'Verify Your UniLi Account';
                    $body = "
                    <h2 style='color: #0f172a;'>Account Verification Required</h2>
                    <p>Thank you for signing up for the <strong>UniLi Water Monitoring System</strong>. To activate your account, please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verify_link}' style='display: inline-block; background-color: #2563eb; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600;'>Verify Email Address</a>
                    </div>

                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='font-size: 13px; color: #64748b;'>{$verify_link}</p>
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                    <p style='font-size: 14px; color: #64748b;'>If you did not create an account, you can safely ignore this email.</p>
                    ";

                    sendSystemEmail($email, $fullname, $subject, $body);

                    header("Location: login.php?registered=true&verify=required");
                    exit();
                } else {
                    $message = "Database Error: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | UniLi Water System</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f0f4f8;
            /* Soft neutral background */
            margin: 0;
            padding: 20px;
            font-family: var(--font-main);
        }

        /* Subtle animated background interest */
        body::after {
            content: '';
            position: fixed;
            top: -10%;
            right: -10%;
            width: 40%;
            height: 40%;
            background: radial-gradient(circle, var(--primary-100) 0%, transparent 70%);
            z-index: -1;
            filter: blur(60px);
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.6s ease-out;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 1.5rem;
                border-radius: 20px;
            }
            .brand-name {
                font-size: 1.25rem;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-box {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            margin-bottom: 0.25rem;
        }

        .brand-tagline {
            font-size: 0.85rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .form-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .input-control {
            width: 100%;
            background: white;
            border: 1px solid var(--gray-200);
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s;
            color: var(--gray-900);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .input-control {
            padding-right: 3rem;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
        }

        .toggle-password:focus {
            outline: none;
        }

        .input-control:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px var(--primary-50);
        }

        .btn-signup {
            width: 100%;
            background: var(--primary-500);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }

        .btn-signup:hover {
            background: var(--primary-600);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(14, 165, 233, 0.2);
        }

        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .auth-footer a {
            color: var(--primary-600);
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
            border: 1px solid #fecaca;
            font-weight: 500;
        }

        /* Hide browser-native password reveal buttons */
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="auth-card">
        <div class="brand-header">
            <div class="logo-box">
                <img src="assets/img/logo.png" alt="Unilia Logo">
            </div>
            <h1 class="brand-name">Create Account</h1>
            <p class="brand-tagline">Join the UniLi Remote Water Monitoring System</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-error">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="signupForm">
            <div class="form-group">
                <input type="text" name="fullname" class="input-control" placeholder="Full Name" required autofocus>
            </div>

            <div class="form-group">
                <input type="email" name="email" class="input-control" placeholder="Email Address" required>
            </div>

            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="input-control" placeholder="Password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="input-control" placeholder="Confirm Password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-signup" id="submitBtn">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('signupForm');
        const btn = document.getElementById('submitBtn');
        const signupToggles = document.querySelectorAll('.toggle-password');

        signupToggles.forEach(toggle => {
            toggle.addEventListener('click', function () {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.innerHTML = `<i class="fas ${isPassword ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });

        if (form) {
            form.addEventListener('submit', function () {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
                btn.style.opacity = '0.8';
                btn.style.pointerEvents = 'none';
            });
        }
    </script>
</body>

</html>
