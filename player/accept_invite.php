<?php
session_start();
require_once "../database/dbConfig.php";

/*
 Accept invitation by token.
 - Must be logged in to accept.
 - Token must exist and be pending.
 - If invitation has invited_user_id then it must match the logged-in user.
 - If invited_email is set then the logged-in user's email must match invited_email.
 - Check team capacity and that user not already a member.
 - Insert team_members and mark invitation accepted in transaction.
*/

if (!isset($_SESSION['user_id'])) {
  // prompt user to login first; after login they can revisit link
  header("Location: ../login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$token = $_GET['token'] ?? '';
if (!$token) die("Missing token.");

$inv = null;
$q = $conn->prepare("SELECT invite_id, team_id, invited_user_id, invited_email, status FROM team_invitations WHERE token = ? LIMIT 1");
$q->bind_param("s", $token);
$q->execute();
$inv = $q->get_result()->fetch_assoc();
$q->close();

if (!$inv) die("Invalid invite token.");
if ($inv['status'] !== 'pending') die("Invite is not pending.");

if ($inv['invited_user_id']) {
  if ((int)$inv['invited_user_id'] !== $user_id) {
    die("This invite is not for your account.");
  }
} elseif ($inv['invited_email']) {
  // verify user's email matches invited_email
  $u = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
  $u->bind_param("i", $user_id);
  $u->execute();
  $ur = $u->get_result()->fetch_assoc();
  $u->close();
  if (!isset($ur['email']) || strtolower($ur['email']) !== strtolower($inv['invited_email'])) {
    die("This invite was sent to {$inv['invited_email']}. You must accept using the account with that email.");
  }
}

// verify not already a member
$chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ? LIMIT 1");
$chk->bind_param("ii", $inv['team_id'], $user_id);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
  $chk->close();
  die("You are already a member of this team.");
}
$chk->close();

// check team capacity (use tournament.team_size)
$capQ = $conn->prepare("
    SELECT tr.team_size, (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = ?) AS cur
    FROM teams t
    JOIN tournaments tr ON t.tournament_id = tr.tournament_id
    WHERE t.team_id = ?
    LIMIT 1
");
$capQ->bind_param("ii", $inv['team_id'], $inv['team_id']);
$capQ->execute();
$capInfo = $capQ->get_result()->fetch_assoc();
$capQ->close();

if (!$capInfo) die("Team not found.");
if ((int)$capInfo['cur'] >= (int)$capInfo['team_size']) die("Team is full.");

$conn->begin_transaction();
try {
  $ins = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
  $ins->bind_param("ii", $inv['team_id'], $user_id);
  if (!$ins->execute()) throw new Exception("Failed to add member: " . $ins->error);
  $ins->close();

  $upd = $conn->prepare("UPDATE team_invitations SET status = 'accepted' WHERE invite_id = ?");
  $upd->bind_param("i", $inv['invite_id']);
  if (!$upd->execute()) throw new Exception("Failed to update invite: " . $upd->error);
  $upd->close();

  $conn->commit();
  header("Location: team.php?team_id=" . $inv['team_id'] . "&joined=1");
  exit;
} catch (Exception $e) {
  $conn->rollback();
  die("Failed to accept invite: " . $e->getMessage());
}
