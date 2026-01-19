<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
$message = trim($_POST['message'] ?? '');

if (!$team_id) {
  die("Missing team_id");
}

// check not already member
$chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ? LIMIT 1");
$chk->bind_param("ii", $team_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  $chk->close();
  die("You are already a member.");
}
$chk->close();

// check existing pending request
$q = $conn->prepare("SELECT request_id FROM team_join_requests WHERE team_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
$q->bind_param("ii", $team_id, $user_id);
$q->execute();
if ($q->get_result()->num_rows > 0) {
  $q->close();
  die("You already have a pending request.");
}
$q->close();

// create request
$ins = $conn->prepare("INSERT INTO team_join_requests (team_id, user_id, message) VALUES (?, ?, ?)");
$ins->bind_param("iis", $team_id, $user_id, $message);
if ($ins->execute()) {
  header("Location: team.php?team_id={$team_id}&requested=1");
  exit;
} else {
  die("Failed to create request: " . $ins->error);
}
