<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || empty(trim($data['name']))) {
    echo json_encode(['success' => false, 'error' => 'Invalid name']);
    exit;
}

$new_name = trim($data['name']);
$stmt = $conn->prepare("UPDATE hardware_settings SET active_chemical = ? WHERE id = 1");
$stmt->bind_param("s", $new_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
$conn->close();
?>
