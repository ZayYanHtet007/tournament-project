<?php
session_start();
require_once "../database/dbConfig.php";
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Login required']);
  exit;
}

$uid = (int)$_SESSION['user_id'];
$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

if ($since > 0) {
  $stmt = $conn->prepare("SELECT notification_id, title, message,  is_read, created_at FROM organizer_notifications WHERE user_id = ? AND notification_id > ? ORDER BY notification_id DESC LIMIT 20");
  $stmt->bind_param("ii", $uid, $since);
} else {
  $stmt = $conn->prepare("SELECT notification_id, title, message,  is_read, created_at FROM organizer_notifications WHERE user_id = ? ORDER BY notification_id DESC LIMIT 20");
  $stmt->bind_param("i", $uid);
}

$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'notifications' => $res]);
