<?php
require_once __DIR__ . '/../database/dbConfig.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($tournament_id === 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $sql = "UPDATE tournaments SET admin_status = ? WHERE tournament_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $tournament_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Tournament " . ucfirst($new_status)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    $stmt->close();
}
