<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['admin_id'])) {
  http_response_code(403);
  exit("Login required");
}

if (!isset($_GET['id'])) {
  http_response_code(400);
  exit("Invalid request");
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("UPDATE admin_notifications SET is_read=1 WHERE notification_id=? AND admin_id=?");
$stmt->bind_param("ii", $id, $_SESSION['admin_id']);
$stmt->execute();


