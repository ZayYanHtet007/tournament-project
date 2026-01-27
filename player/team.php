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

if (!$team_id) die("Missing team_id");

/* ---------- FETCH TEAM ---------- */
$stmt = $conn->prepare("SELECT * FROM teams WHERE team_id = ? LIMIT 1");
$stmt->bind_param("i", $team_id);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$team) die("Team not found.");

/* ---------- ENSURE LEADER EXISTS IN team_members ---------- */
$chkLeader = $conn->prepare("
  SELECT team_member_id FROM team_members
  WHERE team_id = ? AND user_id = ? AND role = 'leader'
  LIMIT 1
");
$chkLeader->bind_param("ii", $team_id, $team['leader_id']);
$chkLeader->execute();
$exists = $chkLeader->get_result()->num_rows;
$chkLeader->close();

if (!$exists) {
  $ins = $conn->prepare("
    INSERT IGNORE INTO team_members (team_id, user_id, role)
    VALUES (?, ?, 'leader')
  ");
  $ins->bind_param("ii", $team_id, $team['leader_id']);
  $ins->execute();
  $ins->close();
}

/* ---------- FETCH MEMBERS ---------- */
$members = [];
$m = $conn->prepare("
  SELECT tm.team_member_id, tm.user_id, u.username, tm.role
  FROM team_members tm
  JOIN users u ON u.user_id = tm.user_id
  WHERE tm.team_id = ?
  ORDER BY tm.role DESC, u.username ASC
");
$m->bind_param("i", $team_id);
$m->execute();
$res = $m->get_result();
while ($r = $res->fetch_assoc()) $members[] = $r;
$m->close();

/* ---------- CHECK IF CURRENT USER IS LEADER ---------- */
$is_leader = false;
foreach ($members as $mb) {
  if ($mb['user_id'] == $user_id && $mb['role'] === 'leader') {
    $is_leader = true;
    break;
  }
}

/* ---------- FETCH INVITES ---------- */
$invites = [];
$vi = $conn->prepare("
  SELECT invite_id, invited_user_id, invited_email, token, status, created_at
  FROM team_invitations
  WHERE team_id = ?
  ORDER BY created_at DESC
");
$vi->bind_param("i", $team_id);
$vi->execute();
$res = $vi->get_result();
while ($r = $res->fetch_assoc()) {
  if ($r['invited_user_id']) {
    $u = $conn->prepare("SELECT username,email FROM users WHERE user_id = ? LIMIT 1");
    $u->bind_param("i", $r['invited_user_id']);
    $u->execute();
    $ur = $u->get_result()->fetch_assoc();
    $u->close();
    $r['invited_username'] = $ur['username'] ?? null;
    $r['invited_email'] = $ur['email'] ?? $r['invited_email'];
  }
  $invites[] = $r;
}
$vi->close();

/* ---------- FETCH JOIN REQUESTS ---------- */
$requests = [];
$jr = $conn->prepare("
  SELECT r.request_id, r.user_id, u.username, r.message, r.created_at
  FROM team_join_requests r
  JOIN users u ON u.user_id = r.user_id
  WHERE r.team_id = ? AND r.status = 'pending'
  ORDER BY r.created_at ASC
");
$jr->bind_param("i", $team_id);
$jr->execute();
$res = $jr->get_result();
while ($r = $res->fetch_assoc()) $requests[] = $r;
$jr->close();

/* ======================
   HANDLE POST ACTIONS
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* ---------- SEND INVITE ---------- */
  if ($action === 'send_invite' && $is_leader) {
    $by = trim($_POST['by'] ?? '');

    if ($by === '') {
      $errors[] = "Provide username or email.";
    } elseif (count($members) >= (int)$team['players']) {
      $errors[] = "Team is full.";
    } else {
      $token = bin2hex(random_bytes(16));

      $u = $conn->prepare("SELECT user_id, username FROM users WHERE username = ? OR email = ? LIMIT 1");
      $u->bind_param("ss", $by, $by);
      $u->execute();
      $user = $u->get_result()->fetch_assoc();
      $u->close();

      if ($user) {
        $chk = $conn->prepare("
          SELECT team_member_id FROM team_members
          WHERE team_id = ? AND user_id = ?
        ");
        $chk->bind_param("ii", $team_id, $user['user_id']);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
          $errors[] = "User already in team.";
        } else {
          $ins = $conn->prepare("
            INSERT INTO team_invitations (team_id, invited_user_id, token)
            VALUES (?, ?, ?)
          ");
          $ins->bind_param("iis", $team_id, $user['user_id'], $token);
          $ins->execute();
          $ins->close();
          $message = "Invite sent to {$user['username']}";
        }
        $chk->close();
      } else {
        if (!filter_var($by, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid email.";
        } else {
          $ins = $conn->prepare("
            INSERT INTO team_invitations (team_id, invited_email, token)
            VALUES (?, ?, ?)
          ");
          $ins->bind_param("iss", $team_id, $by, $token);
          $ins->execute();
          $ins->close();
          $message = "Invite sent to email.";
        }
      }
    }
  }

  /* ---------- REQUEST JOIN ---------- */ elseif ($action === 'request_join') {
    if (count($members) >= (int)$team['players']) {
      $errors[] = "Team is full.";
    } else {
      $chk = $conn->prepare("
        SELECT team_member_id FROM team_members
        WHERE team_id = ? AND user_id = ?
      ");
      $chk->bind_param("ii", $team_id, $user_id);
      $chk->execute();
      if ($chk->get_result()->num_rows > 0) {
        $errors[] = "Already a member.";
      } else {
        $ins = $conn->prepare("
          INSERT INTO team_join_requests (team_id, user_id, message)
          VALUES (?, ?, ?)
        ");
        $msg = trim($_POST['message'] ?? '');
        $ins->bind_param("iis", $team_id, $user_id, $msg);
        $ins->execute();
        $ins->close();
        $message = "Join request sent.";
      }
      $chk->close();
    }
  }

  /* ---------- APPROVE REQUEST ---------- */ elseif ($action === 'approve_request' && $is_leader) {
    $rid = (int)$_POST['request_id'];

    $rq = $conn->prepare("
      SELECT user_id FROM team_join_requests
      WHERE request_id = ? AND team_id = ? AND status = 'pending'
    ");
    $rq->bind_param("ii", $rid, $team_id);
    $rq->execute();
    $req = $rq->get_result()->fetch_assoc();
    $rq->close();

    if (!$req) {
      $errors[] = "Invalid request.";
    } elseif (count($members) >= (int)$team['players']) {
      $errors[] = "Team is full.";
    } else {
      $conn->begin_transaction();
      try {
        $ins = $conn->prepare("
          INSERT INTO team_members (team_id, user_id, role)
          VALUES (?, ?, 'member')
        ");
        $ins->bind_param("ii", $team_id, $req['user_id']);
        $ins->execute();
        $ins->close();

        $del = $conn->prepare("
          DELETE FROM team_join_requests
          WHERE team_id = ? AND user_id = ?
        ");
        $del->bind_param("ii", $team_id, $req['user_id']);
        $del->execute();
        $del->close();

        $conn->commit();
        $message = "Request approved.";
      } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Failed to approve.";
      }
    }
  }

  /* ---------- REJECT REQUEST ---------- */ elseif ($action === 'reject_request' && $is_leader) {
    $rid = (int)$_POST['request_id'];
    $del = $conn->prepare("DELETE FROM team_join_requests WHERE request_id = ? AND team_id = ?");
    $del->bind_param("ii", $rid, $team_id);
    $del->execute();
    $del->close();
    $message = "Request rejected.";
  }

  /* ---------- KICK MEMBER ---------- */ elseif ($action === 'kick_member' && $is_leader) {
    $uid = (int)$_POST['user_id'];
    if ($uid == $team['leader_id']) {
      $errors[] = "Cannot kick leader.";
    } else {
      $del = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
      $del->bind_param("ii", $team_id, $uid);
      $del->execute();
      $del->close();
      $message = "Member removed.";
    }
  }

  header("Location: team.php?team_id=$team_id");
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
    <p class="text-sm text-gray-600 mb-4">
      Leader: <?= htmlspecialchars($team['leader_id'] == $user_id ? 'You' : 'Leader #' . $team['leader_id']) ?> —
      Members: <?= count($members) ?> / <?= (int)($team['players'] ?? 0) ?>
    </p>

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
                <?= htmlspecialchars($inv['invited_username'] ?? $inv['invited_email'] ?? 'Unknown') ?> — <?= htmlspecialchars($inv['created_at']) ?>
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