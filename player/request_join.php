<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Error: You must be logged in.");
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)($_POST['team_id'] ?? 0);
$message = "I would like to join your team.";

if ($team_id <= 0) {
  http_response_code(400);
  exit("Error: Invalid Team ID.");
}

$stmt = $conn->prepare("SELECT players FROM teams WHERE team_id = ? LIMIT 1");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$team) {
  http_response_code(404);
  exit("Error: Team not found.");
}

$cnt = $conn->prepare("SELECT COUNT(*) AS total FROM team_members WHERE team_id = ?");
$cnt->bind_param("i", $team_id);
$cnt->execute();
$totalMembers = $cnt->get_result()->fetch_assoc()['total'];
$cnt->close();

if ($totalMembers >= (int)$team['players']) {
  http_response_code(409);
  exit("Team is full.");
}


$chk = $conn->prepare("SELECT request_id FROM team_join_requests WHERE team_id = ? AND user_id = ? AND status = 'pending'");
$chk->bind_param("ii", $team_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  http_response_code(400);
  exit("Request already pending.");
}
$chk->close();


$ins = $conn->prepare("INSERT INTO team_join_requests (team_id, user_id, message, status) VALUES (?, ?, ?, 'pending')");
$ins->bind_param("iis", $team_id, $user_id, $message);

if ($ins->execute()) {
  echo "Success! Request sent.";
} else {
  http_response_code(500);
  echo "Database error.";
}
$ins->close();
$conn->close();
