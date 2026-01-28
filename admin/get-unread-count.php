<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['admin_id'])) {
  http_response_code(403);
  exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) AS unread FROM admin_notifications WHERE admin_id=? AND is_read=0");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo $result['unread'];
