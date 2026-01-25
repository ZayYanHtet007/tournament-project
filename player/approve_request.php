<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Unauthorized");
}

$user_id = (int)$_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);

if (!$request_id) {
  http_response_code(400);
  exit("Missing request_id");
}

/* ---------- FETCH REQUEST ---------- */
$rq = $conn->prepare("
  SELECT r.team_id, r.user_id, t.leader_id, t.players
  FROM team_join_requests r
  JOIN teams t ON t.team_id = r.team_id
  WHERE r.request_id = ? AND r.status = 'pending'
");
$rq->bind_param("i", $request_id);
$rq->execute();
$data = $rq->get_result()->fetch_assoc();
$rq->close();

if (!$data) {
  http_response_code(404);
  exit("Request not found");
}

if ($data['leader_id'] != $user_id) {
  http_response_code(403);
  exit("Not team leader");
}

/* ---------- CAPACITY CHECK ---------- */
$cnt = $conn->prepare("
  SELECT COUNT(*) AS total FROM team_members WHERE team_id = ?
");
$cnt->bind_param("i", $data['team_id']);
$cnt->execute();
$total = $cnt->get_result()->fetch_assoc()['total'];
$cnt->close();

if ($total >= (int)$data['players']) {
  http_response_code(409);
  exit("Team is full");
}

/* ---------- TRANSACTION ---------- */
$conn->begin_transaction();
try {
  $add = $conn->prepare("
    INSERT INTO team_members (team_id, user_id, role)
    VALUES (?, ?, 'member')
  ");
  $add->bind_param("ii", $data['team_id'], $data['user_id']);
  $add->execute();
  $add->close();

  $del = $conn->prepare("
    DELETE FROM team_join_requests WHERE request_id = ?
  ");
  $del->bind_param("i", $request_id);
  $del->execute();
  $del->close();

  $conn->commit();
  echo "approved";
} catch (Exception $e) {
  $conn->rollback();
  http_response_code(500);
  echo "failed";
}
