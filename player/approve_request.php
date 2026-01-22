<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$leader_id = (int)$_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
if (!$request_id) die("Missing request_id.");

// load request + team info and verify leader
$qr = $conn->prepare("
    SELECT r.request_id, r.team_id, r.user_id, r.status, t.leader_id, tr.team_size
    FROM team_join_requests r
    JOIN teams t ON r.team_id = t.team_id
    JOIN tournaments tr ON t.tournament_id = tr.tournament_id
    WHERE r.request_id = ?
    LIMIT 1
");
$qr->bind_param("i", $request_id);
$qr->execute();
$row = $qr->get_result()->fetch_assoc();
$qr->close();
if (!$row) die("Request not found.");
if ($row['leader_id'] != $leader_id) die("You are not the team leader.");
if ($row['status'] !== 'pending') die("Request not pending.");

// check capacity
$cntQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM team_members WHERE team_id = ?");
$cntQ->bind_param("i", $row['team_id']);
$cntQ->execute();
$currentMembers = (int)$cntQ->get_result()->fetch_assoc()['cnt'];
$cntQ->close();

if ($currentMembers >= (int)$row['team_size']) die("Team is full.");

// approve in transaction
$conn->begin_transaction();
try {
  $add = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
  $add->bind_param("ii", $row['team_id'], $row['user_id']);
  if (!$add->execute()) throw new Exception("Failed to add member: " . $add->error);
  $add->close();

  $upd = $conn->prepare("UPDATE team_join_requests SET status = 'approved' WHERE request_id = ?");
  $upd->bind_param("i", $request_id);
  if (!$upd->execute()) throw new Exception("Failed to update request: " . $upd->error);
  $upd->close();

  $conn->commit();
  header("Location: team.php?team_id=" . $row['team_id']);
  exit;
} catch (Exception $e) {
  $conn->rollback();
  die("Failed to approve request: " . $e->getMessage());
}
