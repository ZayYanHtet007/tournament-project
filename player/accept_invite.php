<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  die("You must be logged in to accept an invite.");
}

$user_id = (int)$_SESSION['user_id'];
$token = $_GET['token'] ?? '';

if ($token === '') {
  die("Invalid invite token.");
}

/* ---------- FETCH INVITE ---------- */
$inv = $conn->prepare("
  SELECT * FROM team_invitations
  WHERE token = ? AND status = 'pending'
  LIMIT 1
");
$inv->bind_param("s", $token);
$inv->execute();
$invite = $inv->get_result()->fetch_assoc();
$inv->close();

if (!$invite) {
  die("Invite not found or already used.");
}

/* ---------- CHECK USER MATCH ---------- */
if ($invite['invited_user_id'] && $invite['invited_user_id'] != $user_id) {
  die("This invite is not for your account.");
}

/* ---------- FETCH TEAM ---------- */
$team_id = (int)$invite['team_id'];
$tq = $conn->prepare("SELECT * FROM teams WHERE team_id = ? LIMIT 1");
$tq->bind_param("i", $team_id);
$tq->execute();
$team = $tq->get_result()->fetch_assoc();
$tq->close();

if (!$team) {
  die("Team not found.");
}

/* ---------- COUNT MEMBERS ---------- */
$cnt = $conn->prepare("
  SELECT COUNT(*) AS total FROM team_members WHERE team_id = ?
");
$cnt->bind_param("i", $team_id);
$cnt->execute();
$total = $cnt->get_result()->fetch_assoc()['total'];
$cnt->close();

if ($total >= (int)$team['players']) {
  die("Team is already full.");
}

/* ---------- CHECK IF ALREADY MEMBER ---------- */
$chk = $conn->prepare("
  SELECT team_member_id FROM team_members
  WHERE team_id = ? AND user_id = ?
  LIMIT 1
");
$chk->bind_param("ii", $team_id, $user_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  die("You are already a member of this team.");
}
$chk->close();

/* ---------- ACCEPT INVITE (TRANSACTION) ---------- */
$conn->begin_transaction();
try {
  $add = $conn->prepare("
    INSERT INTO team_members (team_id, user_id, role)
    VALUES (?, ?, 'member')
  ");
  $add->bind_param("ii", $team_id, $user_id);
  $add->execute();
  $add->close();

  $upd = $conn->prepare("
    UPDATE team_invitations SET status = 'accepted'
    WHERE invite_id = ?
  ");
  $upd->bind_param("i", $invite['invite_id']);
  $upd->execute();
  $upd->close();

  $del = $conn->prepare("
    DELETE FROM team_join_requests
    WHERE team_id = ? AND user_id = ?
  ");
  $del->bind_param("ii", $team_id, $user_id);
  $del->execute();
  $del->close();

  $conn->commit();

  header("Location: ../player/team.php?team_id=$team_id");
  exit;
} catch (Exception $e) {
  $conn->rollback();
  die("Failed to accept invite.");
}
