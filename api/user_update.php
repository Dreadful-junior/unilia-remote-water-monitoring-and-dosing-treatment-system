<?php
session_start();
header('Content-Type: application/json');
require '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($fullname) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Name and Email are required']);
    exit();
}

try {
    // 1. Handle Avatar Upload
    $avatar_path = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images allowed.']);
            exit();
        }

        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'error' => 'File size too large (Max 5MB)']);
            exit();
        }

        $new_name = "avatar_" . $user_id . "_" . time() . "." . $file_ext;
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);

        $target_path = $upload_dir . $new_name;
        $db_path = 'uploads/avatars/' . $new_name;

        if (move_uploaded_file($file_tmp, $target_path)) {
            // Delete old avatar if not default
            $old_res = $conn->query("SELECT avatar FROM users WHERE id = $user_id");
            $old_data = $old_res->fetch_assoc();
            if ($old_data && !empty($old_data['avatar']) && strpos($old_data['avatar'], 'default') === false) {
                if (file_exists('../' . $old_data['avatar']))
                    unlink('../' . $old_data['avatar']);
            }

            $avatar_path = $db_path;
            $conn->query("UPDATE users SET avatar = '$db_path' WHERE id = $user_id");
        }
    }

    // 2. Update Info
    $sql = "UPDATE users SET fullname = ?, email = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $fullname, $email, $user_id);
    $stmt->execute();
    $stmt->close();

    // 3. Update Password if provided
    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_pw->bind_param("si", $hashed, $user_id);
        $stmt_pw->execute();
        $stmt_pw->close();
    }

    // 4. Update Session
    $_SESSION['username'] = $fullname;

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
