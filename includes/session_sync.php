<?php
// Session Synchronization - Ensures role updates are reflected immediately
if (isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fullname, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['role'] = $row['role'];
        $_SESSION['username'] = $row['fullname'];
    }
    $stmt->close();
}
?>
