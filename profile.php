<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fullname, email, role, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initials = 'U';
$parts = explode(' ', $user_data['fullname']);
$initials = strtoupper(substr($parts[0], 0, 1));
if (isset($parts[1]))
    $initials .= strtoupper(substr($parts[1], 0, 1));

// Fallback to default avatar if none exists
$avatar_url = (!empty($user_data['avatar']) && file_exists($user_data['avatar'])) ? $user_data['avatar'] : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings | UniLi Water System</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(203, 213, 225, 0.5);
        }

        .profile-container {
            max-width: 700px;
            margin: 0 auto;
            padding-bottom: 3rem;
        }

        .profile-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--glass-border);
            background: rgba(248, 250, 252, 0.4);
        }

        .card-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .card-header p {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin: 0.25rem 0 0 0;
        }

        .card-body {
            padding: 2rem;
        }

        /* Avatar Row */
        .avatar-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .large-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
            color: white;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(14, 165, 233, 0.2);
            overflow: hidden;
        }

        .large-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .upload-btn {
            background: white;
            color: var(--gray-800);
            border: 1px solid var(--gray-300);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .upload-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .avatar-hint {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* Form Fields */
        .form-group {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group input {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            font-size: 0.95rem;
            color: var(--gray-900);
            background: white;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:disabled {
            background: var(--gray-50);
            color: var(--gray-500);
            cursor: not-allowed;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        .save-btn-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .save-btn {
            background: var(--primary-600);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(14, 165, 233, 0.3);
        }

        .save-btn:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        #avatar-input {
            display: none;
        }
    </style>
</head>

<body class="web-dashboard-body">

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">

            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <button class="mobile-toggle" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="welcome-title" style="margin-bottom: 0;">Profile Settings</h1>
                    </div>
                    <p class="welcome-subtitle">Manage your personal information and account security.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <div class="profile-container">
                <form id="profile-form">
                    
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <h3>Profile Picture</h3>
                            <p>Update your avatar to personalize your account.</p>
                        </div>
                        <div class="card-body">
                            <div class="avatar-row">
                                <div class="large-avatar" id="avatar-preview">
                                    <?php if ($avatar_url): ?>
                                        <img src="<?php echo $avatar_url; ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-actions">
                                    <label for="avatar-input" class="upload-btn">Change picture</label>
                                    <input type="file" id="avatar-input" accept="image/*">
                                    <span class="avatar-hint">JPG, GIF or PNG. 5MB max.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Info Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <h3>Personal Information</h3>
                            <p>Update your name and email address.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($user_data['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="role">Account Role</label>
                                <input type="text" id="role" value="<?php echo ucfirst($user_data['role']); ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Security Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <h3>Security Settings</h3>
                            <p>Ensure your account is using a long, random password to stay secure.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" name="new_password" id="new_password" placeholder="Leave blank to keep current password">
                            </div>
                        </div>
                    </div>

                    <!-- Save Action -->
                    <div class="save-btn-container">
                        <button type="submit" class="save-btn" id="save-btn">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div id="toast-container" class="toast-container"></div>
        </main>
    </div>

    <script>

        // Preview and auto-upload avatar
        document.getElementById('avatar-input').onchange = async function (evt) {
            const [file] = evt.target.files;
            if (file) {
                // Preview
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('avatar-preview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);

                // Auto Upload
                const formData = new FormData();
                formData.append('avatar', file);
                // We must append existing name and email because the API requires them
                formData.append('fullname', document.getElementById('fullname').value);
                formData.append('email', document.getElementById('email').value);

                try {
                    showToast('Uploading picture...', 'info');
                    const res = await fetch('api/user_update.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        showToast('Profile picture updated successfully!', 'success');
                        // Reload to update the top right corner
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(data.error || 'Upload failed', 'error');
                    }
                } catch (err) {
                    showToast('An error occurred during upload', 'error');
                }
            }
        };

        // Handle form submission for text fields
        document.getElementById('profile-form').onsubmit = async function (e) {
            e.preventDefault();
            const btn = document.getElementById('save-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const formData = new FormData(this);
            // Avatar is handled automatically above, but we can send it again if it's there
            const avatarFile = document.getElementById('avatar-input').files[0];
            if (avatarFile) {
                formData.append('avatar', avatarFile);
            }

            try {
                const res = await fetch('api/user_update.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Profile updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error || 'Update failed', 'error');
                }
            } catch (err) {
                showToast('An error occurred during update', 'error');
            } finally {
                btn.innerHTML = 'Save Changes';
                btn.disabled = false;
            }
        };

        function showToast(message, severity = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `web-toast ${severity}`;
            toast.innerHTML = `
                <i class="fas ${severity === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="font-size: 1.25rem;"></i>
                <div style="flex:1">
                    <div style="font-weight:700; font-size:0.9rem;">${message}</div>
                </div>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>

</html>
