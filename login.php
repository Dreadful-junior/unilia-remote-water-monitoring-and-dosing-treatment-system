<?php
session_start();
require 'db_connect.php';

$message = "";
$msg_type = "";

// Quietly report errors, but log them for debugging if needed
mysqli_report(MYSQLI_REPORT_OFF);

if (isset($_GET['registered'])) {
    if (isset($_GET['verify']) && $_GET['verify'] === 'required') {
        $message = "Registration successful! We've sent a verification link to your email. Please verify your account before signing in.";
        $msg_type = "info";
    } else {
        $message = "Registration successful! Please sign in.";
        $msg_type = "success";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $msg_type = "error";
    } else {
        // Robust query preparation (including verification status and attempts)
        $stmt = $conn->prepare("SELECT id, fullname, password, role, account_status, is_verified, is_approved, login_attempts, last_attempt_time FROM users WHERE email = ?");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $user_id = $row['id'];

                // 10. Limit login attempts (Lockout for 15 mins after 5 attempts)
                $max_attempts = 5;
                $lockout_time = 15; // minutes
                $now = new DateTime();
                $last_attempt = $row['last_attempt_time'] ? new DateTime($row['last_attempt_time']) : null;

                if ($row['login_attempts'] >= $max_attempts && $last_attempt) {
                    $diff = $now->getTimestamp() - $last_attempt->getTimestamp();
                    if ($diff < ($lockout_time * 60)) {
                        $remaining = $lockout_time - ceil($diff / 60);
                        $message = "Too many failed attempts. Please try again in $remaining minutes.";
                        $msg_type = "error";
                        $stmt->close();
                        goto render_page; // Jump to end of logic
                    }
                }

                // Check if account is suspended
                if (($row['account_status'] ?? 'active') === 'suspended') {
                    $message = "Your account has been suspended. Please contact the administrator.";
                    $msg_type = "error";
                } 
                // Check if account is verified
                else if (!($row['is_verified'] ?? 0)) {
                    $message = "Your email address has not been verified yet. Please check your inbox for the verification link.";
                    $msg_type = "info";
                }
                // Check if account is approved by admin
                else if (!($row['is_approved'] ?? 0) && !in_array($row['role'], ['admin', 'manager'])) {
                    // Admins and Managers are auto-approved to prevent lockouts, but technicians need approval
                    $message = "Your email is verified, but your account is pending administrative approval. Please check back later.";
                    $msg_type = "info";
                }
                else if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['fullname'];
                    $_SESSION['role'] = $row['role'];

                    // Update last login AND Reset attempts
                    $conn->query("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = $user_id");

                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Update failed attempts
                    $conn->query("UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() WHERE id = $user_id");
                    $message = "Invalid email or password";
                    $msg_type = "error";
                }
            } else {
                $message = "Invalid email or password";
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $message = "System error: Please ensure database setup is complete.";
            $msg_type = "error";
        }
    }
}

render_page: // Target for the lockout jump
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | UniLi Water System</title>
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
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
            max-width: 400px;
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

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            margin-left: 0.25rem;
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

        .input-control:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px var(--primary-50);
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

        .btn-signin {
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

        .btn-signin:hover {
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

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-align: center;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* Hide browser-native password reveal buttons */
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--gray-500);
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="auth-card">
        <div class="brand-header">
            <div class="logo-box">
                <img src="assets/img/logo.png" alt="Unilia Logo">
            </div>
            <h1 class="brand-name">UNILIA</h1>
            <p class="brand-tagline">Remote Water Monitoring System</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <input type="email" name="email" class="input-control" placeholder="Email address" required autofocus>
            </div>

            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="input-control" placeholder="Password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Keep me signed in</span>
                    </label>
                    <a href="forgot.php"
                        style="color: var(--primary-600); text-decoration: none; font-weight: 600;">Forgot Password?</a>
                </div>
            </div>

            <button type="submit" class="btn-signin" id="submitBtn">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const loginToggles = document.querySelectorAll('.toggle-password');

        loginToggles.forEach(toggle => {
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                submitBtn.style.opacity = '0.8';
                submitBtn.style.pointerEvents = 'none';
            });
        }
    </script>
</body>

</html>
