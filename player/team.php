<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$message = '';
$errors = [];

if (!$team_id) {
  die("Missing team_id");
}

/* Fetch team, tournament, members, invitations, join requests */
$stmt = $conn->prepare("
    SELECT t.team_id, t.team_name, t.leader_id, tr.tournament_id, tr.title, tr.team_size
    FROM teams t
    JOIN tournaments tr ON t.tournament_id = tr.tournament_id
    WHERE t.team_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$team) die("Team not found.");

$is_leader = ($team['leader_id'] == $user_id);

/* Members */
$members = [];
$m = $conn->prepare("
    SELECT tm.team_member_id, u.user_id, u.username, tm.role
    FROM team_members tm
    JOIN users u ON tm.user_id = u.user_id
    WHERE tm.team_id = ?
    ORDER BY tm.role DESC, u.username ASC
");
$m->bind_param("i", $team_id);
$m->execute();
$res = $m->get_result();
while ($r = $res->fetch_assoc()) $members[] = $r;
$m->close();

/* Pending invitations */
$invites = [];
$vi = $conn->prepare("SELECT invite_id, invited_user_id, invited_email, token, status, created_at FROM team_invitations WHERE team_id = ? ORDER BY created_at DESC");
$vi->bind_param("i", $team_id);
$vi->execute();
$res = $vi->get_result();
while ($r = $res->fetch_assoc()) {
  if ($r['invited_user_id']) {
    $u = $conn->prepare("SELECT username,email FROM users WHERE user_id = ? LIMIT 1");
    $u->bind_param("i", $r['invited_user_id']);
    $u->execute();
    $userRow = $u->get_result()->fetch_assoc();
    $u->close();
    $r['invited_username'] = $userRow['username'] ?? null;
    $r['invited_email'] = $userRow['email'] ?? $r['invited_email'];
  }
  $invites[] = $r;
}
$vi->close();

/* Pending join requests */
$requests = [];
$jr = $conn->prepare("
    SELECT r.request_id, r.user_id, u.username, r.message, r.status, r.created_at
    FROM team_join_requests r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.team_id = ? AND r.status = 'pending'
    ORDER BY r.created_at ASC
");
$jr->bind_param("i", $team_id);
$jr->execute();
$res = $jr->get_result();
while ($r = $res->fetch_assoc()) $requests[] = $r;
$jr->close();

/* Actions: only leader can send invites, approve requests, revoke invites, kick members.
   Non-leaders can request to join.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'send_invite' && $is_leader) {
    $by = trim($_POST['by'] ?? ''); // username or email
    if ($by === '') {
      $errors[] = "Provide a username or email to invite.";
    } else {
      // try to find user by username or email
      $user = null;
      $q = $conn->prepare("SELECT user_id, email, username FROM users WHERE username = ? OR email = ? LIMIT 1");
      $q->bind_param("ss", $by, $by);
      $q->execute();
      $user = $q->get_result()->fetch_assoc();
      $q->close();

      $token = bin2hex(random_bytes(16));
      if ($user) {
        // if user already member
        $chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ? LIMIT 1");
        $chk->bind_param("ii", $team_id, $user['user_id']);
        $chk->execute();
        $rchk = $chk->get_result();
        if ($rchk->num_rows > 0) {
          $errors[] = "User is already a member of the team.";
          $chk->close();
        } else {
          $chk->close();
          $ins = $conn->prepare("INSERT INTO team_invitations (team_id, invited_user_id, token) VALUES (?, ?, ?)");
          $ins->bind_param("iis", $team_id, $user['user_id'], $token);
          if ($ins->execute()) {
            $message = "Invite created for user " . htmlspecialchars($user['username']) . ". Accept link: " . htmlspecialchars("/player/accept_invite.php?token={$token}");
          } else {
            $errors[] = "Failed to create invite: " . $ins->error;
          }
          $ins->close();
        }
      } else {
        // treat as email: basic validation
        if (!filter_var($by, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid email address.";
        } else {
          $ins = $conn->prepare("INSERT INTO team_invitations (team_id, invited_email, token) VALUES (?, ?, ?)");
          $ins->bind_param("iss", $team_id, $by, $token);
          if ($ins->execute()) {
            $message = "Invite created for email " . htmlspecialchars($by) . ". Accept link: " . htmlspecialchars("/player/accept_invite.php?token={$token}");
          } else {
            $errors[] = "Failed to create invite: " . $ins->error;
          }
          $ins->close();
        }
      }
    }
  } elseif ($action === 'request_join') {
    // request by non-leader: user requests to join
    $msg = trim($_POST['message'] ?? '');
    // check not already member
    $chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ? LIMIT 1");
    $chk->bind_param("ii", $team_id, $user_id);
    $chk->execute();
    $rchk = $chk->get_result();
    if ($rchk->num_rows > 0) {
      $errors[] = "You are already a member of this team.";
      $chk->close();
    } else {
      $chk->close();
      // check existing pending request
      $q = $conn->prepare("SELECT request_id FROM team_join_requests WHERE team_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
      $q->bind_param("ii", $team_id, $user_id);
      $q->execute();
      $rq = $q->get_result();
      if ($rq->num_rows > 0) {
        $errors[] = "You already have a pending join request for this team.";
        $q->close();
      } else {
        $q->close();
        $ins = $conn->prepare("INSERT INTO team_join_requests (team_id, user_id, message) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $team_id, $user_id, $msg);
        if ($ins->execute()) {
          $message = "Join request sent to the team leader.";
        } else {
          $errors[] = "Failed to submit request: " . $ins->error;
        }
        $ins->close();
      }
    }
  } elseif ($action === 'approve_request' && $is_leader) {
    $request_id = (int)$_POST['request_id'];
    // fetch request
    $rq = $conn->prepare("SELECT request_id, team_id, user_id, status FROM team_join_requests WHERE request_id = ? LIMIT 1");
    $rq->bind_param("i", $request_id);
    $rq->execute();
    $requestRow = $rq->get_result()->fetch_assoc();
    $rq->close();
    if (!$requestRow || $requestRow['team_id'] != $team_id || $requestRow['status'] !== 'pending') {
      $errors[] = "Invalid request.";
    } else {
      // check capacity: tournament team_size vs current members
      $teamSize = (int)$team['team_size'];
      $cntQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM team_members WHERE team_id = ?");
      $cntQ->bind_param("i", $team_id);
      $cntQ->execute();
      $currentMembers = (int)$cntQ->get_result()->fetch_assoc()['cnt'];
      $cntQ->close();

      if ($currentMembers >= $teamSize) {
        $errors[] = "Team is already full.";
      } else {
        // add member inside transaction
        $conn->begin_transaction();
        try {
          $add = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
          $add->bind_param("ii", $team_id, $requestRow['user_id']);
          if (!$add->execute()) throw new Exception("Failed to add member: " . $add->error);
          $add->close();

          $upd = $conn->prepare("UPDATE team_join_requests SET status = 'approved' WHERE request_id = ?");
          $upd->bind_param("i", $request_id);
          if (!$upd->execute()) throw new Exception("Failed to update request: " . $upd->error);
          $upd->close();

          $conn->commit();
          $message = "Request approved and user added to team.";
        } catch (Exception $e) {
          $conn->rollback();
          $errors[] = $e->getMessage();
        }
      }
    }
  } elseif ($action === 'reject_request' && $is_leader) {
    $request_id = (int)$_POST['request_id'];
    $upd = $conn->prepare("UPDATE team_join_requests SET status = 'rejected' WHERE request_id = ? AND team_id = ?");
    $upd->bind_param("ii", $request_id, $team_id);
    if ($upd->execute()) {
      $message = "Request rejected.";
    } else {
      $errors[] = "Failed to reject request: " . $upd->error;
    }
    $upd->close();
  } elseif ($action === 'revoke_invite' && $is_leader) {
    $invite_id = (int)$_POST['invite_id'];
    $upd = $conn->prepare("UPDATE team_invitations SET status = 'revoked' WHERE invite_id = ? AND team_id = ?");
    $upd->bind_param("ii", $invite_id, $team_id);
    if ($upd->execute()) {
      $message = "Invite revoked.";
    } else {
      $errors[] = "Failed to revoke invite: " . $upd->error;
    }
    $upd->close();
  } elseif ($action === 'leave_team') {
    // allow members (non-leader) to leave
    // leaders cannot leave (or would need transfer)
    $chk = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND user_id = ? LIMIT 1");
    $chk->bind_param("ii", $team_id, $user_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$row) {
      $errors[] = "You are not a member.";
    } elseif ($row['role'] === 'leader') {
      $errors[] = "Leader cannot leave. Transfer leadership or disband the team.";
    } else {
      $del = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
      $del->bind_param("ii", $team_id, $user_id);
      if ($del->execute()) {
        $message = "You left the team.";
      } else {
        $errors[] = "Failed to leave team: " . $del->error;
      }
      $del->close();
    }
  } elseif ($action === 'kick_member' && $is_leader) {
    $kick_user_id = (int)$_POST['user_id'];
    if ($kick_user_id == $team['leader_id']) {
      $errors[] = "Cannot kick the leader.";
    } else {
      $del = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
      $del->bind_param("ii", $team_id, $kick_user_id);
      if ($del->execute()) {
        $message = "Member removed.";
      } else {
        $errors[] = "Failed to remove member: " . $del->error;
      }
      $del->close();
    }
  }

  // refresh members, invites, requests after actions
  header("Location: team.php?team_id={$team_id}");
  exit;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Team: <?= htmlspecialchars($team['team_name']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
  <div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($team['team_name']) ?></h1>
    <p class="text-sm text-gray-600 mb-4">Tournament: <?= htmlspecialchars($team['title']) ?> — Team size: <?= (int)$team['team_size'] ?></p>

    <?php if ($message): ?>
      <div class="text-green-700 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="text-red-700 mb-4">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="bg-white p-4 rounded shadow mb-4">
      <h2 class="font-semibold mb-2">Members (<?= count($members) ?>)</h2>
      <ul>
        <?php foreach ($members as $m): ?>
          <li class="mb-1">
            <?= htmlspecialchars($m['username']) ?> <?= $m['role'] === 'leader' ? '(Leader)' : '' ?>
            <?php if ($is_leader && $m['role'] !== 'leader'): ?>
              <form method="post" class="inline-block ml-2">
                <input type="hidden" name="action" value="kick_member">
                <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                <button class="text-red-600" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($is_leader): ?>
      <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold mb-2">Invite Player</h2>
        <form method="post" class="flex gap-2">
          <input class="input" type="text" name="by" placeholder="username or email" required>
          <input type="hidden" name="action" value="send_invite">
          <button class="btn btn-primary" type="submit">Send Invite</button>
        </form>
        <p class="text-sm text-gray-500 mt-2">If username exists the invite goes to that user. Otherwise you can invite by email (user will need to register, then accept).</p>
      </div>

      <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold mb-2">Pending Join Requests</h2>
        <?php if (empty($requests)): ?>
          <p class="text-gray-600">No pending requests.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($requests as $req): ?>
              <li class="mb-2">
                <strong><?= htmlspecialchars($req['username']) ?></strong> — <?= htmlspecialchars($req['message']) ?>
                <div class="mt-1">
                  <form method="post" class="inline-block mr-2">
                    <input type="hidden" name="action" value="approve_request">
                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                    <button class="text-green-600" type="submit">Approve</button>
                  </form>
                  <form method="post" class="inline-block">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                    <button class="text-red-600" type="submit">Reject</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold mb-2">Pending Invites</h2>
        <?php if (empty($invites)): ?>
          <p class="text-gray-600">No pending invites.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($invites as $inv): if ($inv['status'] !== 'pending') continue; ?>
              <li class="mb-2">
                <?= htmlspecialchars($inv['invited_username'] ?? $inv['invited_email'] ?? 'Unknown') ?>
                — <?= htmlspecialchars($inv['created_at']) ?>
                <div class="mt-1">
                  <a class="text-blue-600" href="<?= htmlspecialchars("/player/accept_invite.php?token={$inv['token']}") ?>" target="_blank">Accept link</a>
                  <form method="post" class="inline-block ml-2">
                    <input type="hidden" name="action" value="revoke_invite">
                    <input type="hidden" name="invite_id" value="<?= (int)$inv['invite_id'] ?>">
                    <button class="text-red-600" type="submit">Revoke</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold mb-2">Request to Join</h2>
        <form method="post">
          <input type="hidden" name="action" value="request_join">
          <textarea name="message" class="input mb-2" placeholder="Short message to the leader (optional)"></textarea>
          <button class="btn btn-primary" type="submit">Request to Join</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="mt-6">
      <a href="../tournaments.php" class="text-blue-600">Back to tournaments</a>
    </div>
  </div>
</body>

</html>
?>