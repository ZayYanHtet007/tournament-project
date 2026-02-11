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

    // Check if team is disbanded
    if ($team['status'] === 'disbanded') {
      echo "<div class='p-10 text-center'><h1 class='neon-red'>This team has been disbanded.</h1>";
      echo "<a href='../index.php' class='btn-riot'>Back to Home</a></div>";
      exit;
    }



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

/* ---------- FETCH MEMBERS (LEADER ALWAYS FIRST) ---------- */
$members = [];
$m = $conn->prepare("
  SELECT tm.team_member_id, tm.user_id, u.username, tm.role
  FROM team_members tm
  JOIN users u ON u.user_id = tm.user_id
  WHERE tm.team_id = ?
  ORDER BY FIELD(tm.role, 'leader') DESC, u.username ASC
");
$m->bind_param("i", $team_id);
$m->execute();
$res = $m->get_result();
while ($r = $res->fetch_assoc()) $members[] = $r;
$m->close();

/* ---------- CHECK STATUSES FOR CURRENT USER ---------- */
$is_leader = false;
$is_member = false;
foreach ($members as $mb) {
  if ($mb['user_id'] == $user_id) {
    $is_member = true;
    if ($mb['role'] === 'leader') $is_leader = true;
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
$has_pending_request = false;
$jr = $conn->prepare("
  SELECT r.request_id, r.user_id, u.username, r.message, r.created_at, r.status
  FROM team_join_requests r
  JOIN users u ON u.user_id = r.user_id
  WHERE r.team_id = ? AND r.status = 'pending'
  ORDER BY r.created_at ASC
");
$jr->bind_param("i", $team_id);
$jr->execute();
$res = $jr->get_result();
while ($r = $res->fetch_assoc()) {
  if ($r['user_id'] == $user_id) $has_pending_request = true;
  $requests[] = $r;
}
$jr->close();

/* ======================
    HANDLE POST ACTIONS
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'send_invite' && $is_leader) {
    $by = trim($_POST['by'] ?? '');
    if ($by === '') {
      $errors[] = "Provide username or email.";
    } elseif (count($members) >= (int)$team['players']) {
      $errors[] = "Team is full.";
    } else {
      $token = bin2hex(random_bytes(16));
      $getToken = "accept_invite.php?token=$token";
      $u = $conn->prepare("SELECT user_id, username FROM users WHERE username = ? OR email = ? LIMIT 1");
      $u->bind_param("ss", $by, $by);
      $u->execute();
      $user = $u->get_result()->fetch_assoc();
      $u->close();

      if ($user) {
        $chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ?");
        $chk->bind_param("ii", $team_id, $user['user_id']);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
          $errors[] = "User already in team.";
        } else {
          $ins = $conn->prepare("INSERT INTO team_invitations (team_id, invited_user_id, token) VALUES (?, ?, ?)");
          $ins->bind_param("iis", $team_id, $user['user_id'], $token);
          $ins->execute();
          $ins->close();

          $invitMsg = "Whould you like to join our team?";
          $title      = "We need you in our team to be better.";
          $notiInsert = $conn->prepare("INSERT INTO notifications (user_id, message, title ,created_at,token) VALUES (?,?,?,NOW(),?)");
          $notiInsert->bind_param("isss", $user['user_id'], $invitMsg, $title, $token);
          $notiInsert->execute();
          $notiInsert->close();
          $message = "Invite sent to {$user['username']}";
        }
        $chk->close();
      } else {
        if (!filter_var($by, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid email.";
        } else {
          $ins = $conn->prepare("INSERT INTO team_invitations (team_id, invited_email, token) VALUES (?, ?, ?)");
          $ins->bind_param("iss", $team_id, $by, $token);
          $ins->execute();
          $ins->close();
          $message = "Invite sent to email.";
        }
      }
    }
  } elseif ($action === 'request_join') {
    if (count($members) >= (int)$team['players']) {
      $errors[] = "Team is full.";
    } else {
      $chk = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ?");
      $chk->bind_param("ii", $team_id, $user_id);
      $chk->execute();
      if ($chk->get_result()->num_rows > 0) {
        $errors[] = "Already a member.";
      } else {
        $ins = $conn->prepare("INSERT INTO team_join_requests (team_id, user_id, message) VALUES (?, ?, ?)");
        $msg = trim($_POST['message'] ?? '');
        $ins->bind_param("iis", $team_id, $user_id, $msg);
        $ins->execute();
        $ins->close();
        $message = "Join request sent.";
      }
      $chk->close();
    }
  } elseif ($action === 'approve_request' && $is_leader) {
    $rid = (int)$_POST['request_id'];
    $rq = $conn->prepare("SELECT user_id FROM team_join_requests WHERE request_id = ? AND team_id = ? AND status = 'pending'");
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
        $ins = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
        $ins->bind_param("ii", $team_id, $req['user_id']);
        $ins->execute();
        $ins->close();

        $del = $conn->prepare("DELETE FROM team_join_requests WHERE team_id = ? AND user_id = ?");
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
  } elseif ($action === 'reject_request' && $is_leader) {
    $rid = (int)$_POST['request_id'];
    $del = $conn->prepare("DELETE FROM team_join_requests WHERE request_id = ? AND team_id = ?");
    $del->bind_param("ii", $rid, $team_id);
    $del->execute();
    $del->close();
    $message = "Request rejected.";
  } elseif ($action === 'kick_member' && $is_leader) {
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
  } elseif ($action === 'revoke_invite' && $is_leader) {
    $iid = (int)$_POST['invite_id'];
    $del = $conn->prepare("DELETE FROM team_invitations WHERE invite_id = ? AND team_id = ?");
    $del->bind_param("ii", $iid, $team_id);
    $del->execute();
    $del->close();
    $message = "Invite revoked.";
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
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --riot-red: #ff4655;
      --riot-dark: #0f1419;
      --riot-gray: #1f2326;
    }

    body {
      background-color: var(--riot-dark);
      color: #ece8e1;
      font-family: 'Rajdhani', sans-serif;
      background-image: linear-gradient(rgba(255, 70, 85, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 70, 85, 0.05) 1px, transparent 1px);
      background-size: 30px 30px;
    }

    h1,
    h2 {
      font-family: 'Orbitron', sans-serif;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .neon-red {
      text-shadow: 0 0 10px rgba(255, 70, 85, 0.5);
      color: var(--riot-red);
    }

    .glass-card {
      background: rgba(31, 35, 38, 0.8);
      border: 1px solid rgba(255, 70, 85, 0.2);
      border-left: 4px solid var(--riot-red);
      transition: all 0.3s ease;
    }

    .glass-card:hover {
      border-color: var(--riot-red);
      box-shadow: 0 0 15px rgba(255, 70, 85, 0.1);
    }

    .input-riot {
      background: #0f1419;
      border: 1px solid #383e42;
      color: white;
      padding: 10px;
      width: 100%;
      border-radius: 2px;
    }

    .input-riot:focus {
      outline: none;
      border-color: var(--riot-red);
    }

    .btn-riot {
      background: var(--riot-red);
      color: white;
      font-weight: bold;
      padding: 10px 20px;
      text-transform: uppercase;
      clip-path: polygon(10% 0, 100% 0, 100% 70%, 90% 100%, 0 100%, 0% 30%);
      transition: all 0.2s;
    }

    .btn-riot:hover {
      background: #ff5e6a;
      transform: scale(1.02);
      cursor: pointer;
    }

    .status-pill {
      background: rgba(255, 70, 85, 0.1);
      border: 1px solid var(--riot-red);
      color: var(--riot-red);
      padding: 2px 10px;
      font-size: 0.75rem;
      font-weight: bold;
    }
  </style>
</head>

<body class="p-4 md:p-10">
  <div class="max-w-4xl mx-auto">

    <header class="mb-10 border-b border-gray-800 pb-6">
      <h1 class="text-3xl md:text-5xl font-bold neon-red mb-2"><?= htmlspecialchars($team['team_name']) ?></h1>
      <p class="text-lg text-gray-400">
        Leader: <span class="text-white"><?= htmlspecialchars($team['leader_id'] == $user_id ? 'You' : 'Leader #' . $team['leader_id']) ?></span> —
        <span class="status-pill">MEMBERS: <?= count($members) ?> / <?= (int)($team['players'] ?? 0) ?></span>
      </p>
    </header>

    <?php if ($message): ?>
      <div class="bg-green-900/20 border border-green-500 text-green-400 p-4 mb-6 uppercase font-bold text-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="bg-red-900/20 border border-red-500 text-red-500 p-4 mb-6 uppercase font-bold text-sm">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <div class="md:col-span-2 glass-card p-6 rounded">
        <h2 class="text-xl font-bold mb-4 flex items-center">
          <span class="w-2 h-6 bg-red-600 mr-3"></span>Members (<?= count($members) ?>)
        </h2>

        <div class="space-y-2">
          <?php foreach ($members as $m): ?>
            <div class="flex justify-between items-center bg-black/40 p-4 border border-gray-800 hover:border-red-900 transition-all">
              <span class="text-lg <?= $m['role'] === 'leader' ? 'text-red-500 font-bold' : 'text-gray-200' ?>">
                <?= htmlspecialchars($m['username']) ?> <?= $m['role'] === 'leader' ? '<small class="ml-2 text-[10px] tracking-tighter">(LEADER)</small>' : '' ?>
              </span>
              <?php if ($is_leader && $m['role'] !== 'leader'): ?>
                <form method="post" onsubmit="return confirm('Confirm removal?');">
                  <input type="hidden" name="action" value="kick_member">
                  <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                  <button class="text-red-600 hover:text-white font-bold text-xs uppercase" type="submit">Remove</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?><br>
          <?php if ($is_member): ?>
            <div class="mb-4 text-right">
              <form action="team-leave.php?team_id=<?= $team_id ?>" method="POST"
                onsubmit="return confirm('Are you sure you want to leave? <?= $is_leader ? 'Leadership will pass to the next member.' : '' ?>');">
                <button type="submit" class="text-gray-400 hover:text-red-500 font-bold uppercase text-xs tracking-widest border border-gray-700 px-3 py-1 hover:border-red-500 transition-all">
                  Leave Team
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="space-y-6">
        <?php if ($is_leader): ?>
          <div class="glass-card p-6 rounded">
            <h2 class="text-sm font-bold mb-4 text-red-500 uppercase">Invite Player</h2>
            <form method="post" class="space-y-3">
              <input class="input-riot" type="text" name="by" placeholder="username or email" required>
              <input type="hidden" name="action" value="send_invite">
              <button class="btn-riot w-full" type="submit">Send Invite</button>
            </form>
          </div>

          <div class="glass-card p-6 rounded">
            <h2 class="text-sm font-bold mb-4 text-red-500 uppercase tracking-tighter">Pending Join Requests</h2>
            <?php if (empty($requests)): ?>
              <p class="text-gray-600 text-sm italic">No pending requests.</p>
            <?php else: ?>
              <ul class="space-y-4">
                <?php foreach ($requests as $req): ?>
                  <li class="border-b border-gray-800 pb-3">
                    <strong class="text-white block"><?= htmlspecialchars($req['username']) ?></strong>
                    <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($req['message']) ?></p>
                    <div class="flex gap-2">
                      <form method="post" class="inline-block">
                        <input type="hidden" name="action" value="approve_request">
                        <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                        <button class="text-green-500 text-xs font-bold uppercase" type="submit">Approve</button>
                      </form>
                      <form method="post" class="inline-block">
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                        <button class="text-red-600 text-xs font-bold uppercase" type="submit">Reject</button>
                      </form>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

        <?php elseif (!$is_member): ?>
          <div class="glass-card p-6 rounded">
            <h2 class="text-sm font-bold mb-4 text-red-500 uppercase">Request to Join</h2>
            <?php if ($has_pending_request): ?>
              <p class="text-yellow-500 text-sm font-bold italic">Your request is currently pending review.</p>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="action" value="request_join">
                <textarea name="message" class="input-riot mb-3 h-24" placeholder="Short message to the leader (optional)"></textarea>
                <button class="btn-riot w-full" type="submit">Request Join</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($is_leader): ?>
      <div class="mt-10 glass-card p-6 rounded">
        <h2 class="text-sm font-bold mb-4 text-gray-400 uppercase">Pending Invites</h2>
        <?php if (empty($invites)): ?>
          <p class="text-gray-600 text-sm">No pending invites.</p>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($invites as $inv): if ($inv['status'] !== 'pending') continue; ?>
              <div class="bg-black/30 p-3 border border-gray-800 flex justify-between items-center">
                <div class="text-sm">
                  <div class="text-white font-bold"><?= htmlspecialchars($inv['invited_username'] ?? $inv['invited_email'] ?? 'Unknown') ?></div>
                  <div class="text-[10px] text-gray-500"><?= htmlspecialchars($inv['created_at']) ?></div>
                </div>
                <div class="flex items-center gap-3">
                  <a class="text-red-500 text-[10px] uppercase font-bold hover:underline" href="<?= htmlspecialchars("../accept_invite.php?token={$inv['token']}") ?>" target="_blank">Link</a>
                  <form method="post">
                    <input type="hidden" name="action" value="revoke_invite">
                    <input type="hidden" name="invite_id" value="<?= (int)$inv['invite_id'] ?>">
                    <button class="text-gray-500 hover:text-red-600 text-[10px] uppercase font-bold" type="submit">Revoke</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="mt-12 mb-10 border-t border-gray-800 pt-6">
      <a href="../index.php" class="text-red-500 font-bold uppercase tracking-widest text-sm hover:text-white transition-colors">
        &larr; Back to tournaments
      </a>
    </div>
  </div>