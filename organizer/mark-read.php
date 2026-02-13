<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  exit("Login required");
}

if (!isset($_POST['id'])) {
  http_response_code(400);
  exit("Invalid request");
}

$id = (int)$_POST['id'];

$stmt = $conn->prepare("UPDATE organizer_notifications SET is_read=1 WHERE notification_id=? AND user_id=?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
