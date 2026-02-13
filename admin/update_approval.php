<?php
require_once __DIR__ . '/../database/dbConfig.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($tournament_id === 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }


    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    $sql = "UPDATE tournaments SET admin_status = ? WHERE tournament_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $tournament_id);

    if ($stmt->execute()) {
        $organizer_id = 0;
        $tournament_title = "";
        $tStmt = $conn->prepare("SELECT organizer_id, title FROM tournaments WHERE tournament_id = ? LIMIT 1");
        if ($tStmt) {
            $tStmt->bind_param("i", $tournament_id);
            $tStmt->execute();
            $tRow = $tStmt->get_result()->fetch_assoc();
            if ($tRow) {
                $organizer_id = (int)$tRow['organizer_id'];
                $tournament_title = (string)$tRow['title'];
            }
            $tStmt->close();
        }

        if ($organizer_id > 0) {
            $notiTitle = ($action === 'approve') ? "Tournament Approved" : "Tournament Rejected";
            if ($action === 'approve') {
                $notiMessage = "Your tournament \"{$tournament_title}\" (tournament ID #{$tournament_id}) has been approved.";
                $notiType = "tournament_approved";
            } else {
                $reasonText = $reason !== '' ? $reason : 'Not specified';
                $notiMessage = "Your tournament \"{$tournament_title}\" (tournament ID #{$tournament_id}) was rejected: {$reasonText}. Please edit and resubmit.";
                $notiType = "tournament_rejected";
            }

            $nStmt = $conn->prepare("INSERT INTO organizer_notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            if ($nStmt) {
                $nStmt->bind_param("isss", $organizer_id, $notiTitle, $notiMessage, $notiType);
                $nStmt->execute();
                $nStmt->close();
            } else {
                $fallback = $conn->prepare("INSERT INTO organizer_notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                if ($fallback) {
                    $fallback->bind_param("iss", $organizer_id, $notiTitle, $notiMessage);
                    $fallback->execute();
                    $fallback->close();
                }
            }
        }

        echo json_encode(['success' => true, 'message' => "Tournament " . ucfirst($new_status)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    $stmt->close();
}
