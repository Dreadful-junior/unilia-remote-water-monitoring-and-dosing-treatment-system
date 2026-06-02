<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

require 'db_connect.php';

$msg = "";
$msgType = "success";

if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    $msgType = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : "success";
}

/* =====================================================
   ADD USER
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "add") {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $password = password_hash('password123', PASSWORD_DEFAULT);

    $check = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        header("Location: settings_users.php?msg=Email already exists&type=error");
        exit();
    } else {
        $sql = "INSERT INTO users(fullname, email, password, role, account_status) VALUES('$fullname', '$email', '$password', '$role', '$status')";
        if ($conn->query($sql)) {
            header("Location: settings_users.php?msg=User created successfully. Default password is password123&type=success");
            exit();
        }
    }
}

/* =====================================================
   EDIT USER
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "edit") {
    $id = intval($_POST['id']);
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    $status = $conn->real_escape_string($_POST['status']);

    $sql = "UPDATE users SET fullname='$fullname', email='$email', role='$role', account_status='$status' WHERE id=$id";
    if ($conn->query($sql)) {
        header("Location: settings_users.php?msg=User updated successfully&type=success");
        exit();
    }
}

/* =====================================================
   APPROVE/DISAPPROVE USER
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && ($_POST['action'] == "approve" || $_POST['action'] == "disapprove")) {
    $id = intval($_POST['id']);
    $approved = ($_POST['action'] == "approve") ? 1 : 0;
    
    $sql = "UPDATE users SET is_approved=$approved WHERE id=$id";
    if ($conn->query($sql)) {
        $status_msg = ($approved) ? "User approved successfully" : "User access revoked";
        header("Location: settings_users.php?msg=$status_msg&type=success");
        exit();
    }
}

/* =====================================================
   DELETE USER
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "delete") {
    $id = intval($_POST['id']);
    if ($id == $_SESSION['user_id']) {
        header("Location: settings_users.php?msg=Cannot delete your own account&type=error");
        exit();
    }
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: settings_users.php?msg=User deleted successfully&type=success");
    exit();
}

// Fetch Users
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where = $search ? "WHERE fullname LIKE '%$search%' OR email LIKE '%$search%'" : "";
$users = $conn->query("SELECT * FROM users $where ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | UniLi Remote Water Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <style>
        .users-table-container {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .search-wrapper {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            background: white;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-100);
            outline: none;
        }

        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .user-table th {
            text-align: left;
            padding: 1rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .user-row {
            background: white;
            transition: all 0.2s ease;
        }

        .user-row:hover {
            transform: scale(1.005);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .user-row td {
            padding: 1rem;
            border-top: 1px solid var(--gray-50);
            border-bottom: 1px solid var(--gray-50);
        }

        .user-row td:first-child {
            border-left: 1px solid var(--gray-50);
            border-radius: 12px 0 0 12px;
        }

        .user-row td:last-child {
            border-right: 1px solid var(--gray-50);
            border-radius: 0 12px 12px 0;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-active { background: #dcfce7; color: #15803d; }
        .status-suspended { background: #fee2e2; color: #b91c1c; }

        .approval-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .approval-verified { background: #dcfce7; color: #15803d; border: 1px solid #bdf0d1; }
        .approval-pending { background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }

        .role-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 4px;
        }

        .btn-edit { background: var(--primary-100); color: var(--primary-700); }
        .btn-reset { background: #fef3c7; color: #92400e; }
        .btn-delete { background: #fee2e2; color: #b91c1c; }

        .action-btn:hover { transform: translateY(-2px); filter: brightness(0.9); }

        /* Modal Enhancements */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center; justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <a href="settings.php" class="btn-back" style="text-decoration: none; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(14, 165, 233, 0.1); padding: 0.5rem 1rem; border-radius: 12px; transition: all 0.2s; font-weight: 700;">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back</span>
                        </a>
                        <h1 class="welcome-title" style="margin-bottom: 0;">User Management Portal</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration: none; color: inherit; opacity: 0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">Users</span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="alert <?= $msgType == 'success' ? 'alert-success' : 'alert-error' ?>" style="margin-top: 1rem;">
                    <i class="fas <?= $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <div class="users-table-container">
                <div class="table-header">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="userSearch" class="search-input" placeholder="Search by name or email..." 
                               value="<?= htmlspecialchars($search) ?>" onkeyup="handleSearch(event)">
                    </div>
                </div>

                <table class="user-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $users->fetch_assoc()): ?>
                            <tr class="user-row">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--primary-100); color: var(--primary-700); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                            <?= strtoupper(substr($row['fullname'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--gray-800);"><?= htmlspecialchars($row['fullname']) ?></div>
                                            <div style="font-size: 0.8rem; color: var(--gray-500);"><?= htmlspecialchars($row['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge"><?= ucfirst($row['role']) ?></span>
                                </td>
                                <td>
                                    <?php if ($row['is_approved']): ?>
                                        <span class="approval-badge approval-verified"><i class="fas fa-check-shield"></i> Approved</span>
                                    <?php else: ?>
                                        <span class="approval-badge approval-pending"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $row['account_status'] ?>">
                                        <?= $row['account_status'] ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--gray-600);">
                                    <?= $row['last_login'] ? date('M j, Y H:i', strtotime($row['last_login'])) : 'Never' ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($row['is_approved']): ?>
                                            <button class="action-btn btn-delete" title="Revoke Approval" 
                                                    onclick="submitAction('disapprove', <?= $row['id'] ?>)">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn btn-edit" title="Approve User" style="background: #dcfce7; color: #15803d;"
                                                    onclick="submitAction('approve', <?= $row['id'] ?>)">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn btn-edit" title="Edit User" 
                                            onclick='openEditModal(<?= json_encode($row) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <button class="action-btn btn-delete" title="Delete User"
                                                onclick="confirmDelete(<?= $row['id'] ?>, '<?= $row['fullname'] ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ADD USER MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem;">Create New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-input" required placeholder="Full Name">
                
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" required placeholder="email@unilia.ac.mw">
                
                <label class="form-label">Role</label>
                <select name="role" class="form-input">
                    <option value="technician">Technician</option>
                    <option value="manager">Manager</option>
                </select>

                <label class="form-label">Account Status</label>
                <select name="status" class="form-input">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT USER MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem;">Edit User Account</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" id="edit_fullname" class="form-input" required>
                
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="edit_email" class="form-input" required>
                
                <label class="form-label">Role</label>
                <select name="role" id="edit_role" class="form-input">
                    <option value="technician">Technician</option>
                    <option value="manager">Manager</option>
                </select>

                <label class="form-label">Account Status</label>
                <select name="status" id="edit_status" class="form-input">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- HIDDEN FORMS FOR ACTIONS -->
    <form id="actionForm" method="POST" style="display:none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="id" id="formId">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function openEditModal(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_fullname').value = user.fullname;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.account_status;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModals() {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }

        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete ${name}? This cannot be undone.`)) {
                submitAction('delete', id);
            }
        }

        function submitAction(action, id) {
            document.getElementById('formAction').value = action;
            document.getElementById('formId').value = id;
            document.getElementById('actionForm').submit();
        }

        function handleSearch(e) {
            if (e.key === 'Enter') {
                window.location.href = 'settings_users.php?search=' + e.target.value;
            }
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') closeModals();
        }
    </script>
</body>
</html>
