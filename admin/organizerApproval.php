<?php
require_once __DIR__ . '/../database/dbConfig.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($user_id === 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    // Update organizer_status in users table
    $sql = "UPDATE users SET organizer_status = ? WHERE user_id = ? AND is_organizer = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Organizer " . ucfirst($new_status)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}