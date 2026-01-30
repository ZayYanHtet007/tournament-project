<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Unauthorized");
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)($_POST['team_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$team_id) {
  http_response_code(400);
  exit("Missing team_id");
}

/* ---------- FETCH TEAM ---------- */
$t = $conn->prepare("SELECT players FROM teams WHERE team_id = ? LIMIT 1");
$t->bind_param("i", $team_id);
$t->execute();
$team = $t->get_result()->fetch_assoc();
$t->close();

if (!$team) {
  http_response_code(404);
  exit("Team not found");
}

/* ---------- CHECK CAPACITY ---------- */
$cnt = $conn->prepare("
  SELECT COUNT(*) AS total FROM team_members WHERE team_id = ?
");
$cnt->bind_param("i", $team_id);
$cnt->execute();
$total = $cnt->get_result()->fetch_assoc()['total'];
$cnt->close();

if ($total >= (int)$team['players']) {
  http_response_code(409);
  exit("Team is full");
}

/* ---------- CHECK MEMBER ---------- */
$chk = $conn->prepare("
  SELECT team_member_id FROM team_members
  WHERE team_id = ? AND user_id = ?
");
$chk->bind_param("ii", $team_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  exit("Already a member");
}
$chk->close();

/* ---------- CHECK PENDING REQUEST ---------- */
$chk2 = $conn->prepare("
  SELECT request_id FROM team_join_requests
  WHERE team_id = ? AND user_id = ? AND status = 'pending'
");
$chk2->bind_param("ii", $team_id, $user_id);
$chk2->execute();
if ($chk2->get_result()->num_rows > 0) {
  exit("Request already sent");
}
$chk2->close();

/* ---------- INSERT REQUEST ---------- */
$ins = $conn->prepare("
  INSERT INTO team_join_requests (team_id, user_id, message)
  VALUES (?, ?, ?)
");
$ins->bind_param("iis", $team_id, $user_id, $message);
$ins->execute();
$ins->close();

echo "request_sent";
