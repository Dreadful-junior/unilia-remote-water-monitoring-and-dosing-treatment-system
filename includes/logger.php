<?php
function logActivity($conn, $user_id, $action, $details = "") {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
    $stmt->execute();
    $stmt->close();
}
?>
