<?php
session_start();
require_once "../database/dbConfig.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$token = $_GET['token'] ?? '';

if ($token === '') {
    die("Missing invitation token.");
}

// Fetch the invitation together with team details
$stmt = $conn->prepare("
    SELECT i.*, t.team_name, t.players AS max_players, t.status AS team_status,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.team_id) AS member_count
    FROM team_invitations i
    JOIN teams t ON i.team_id = t.team_id
    WHERE i.token = ? AND i.status = 'pending'
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$invite = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invite) {
    echo "<script>
        alert('Invite not found or already used.');
        window.location.replace('../index.php');
    </script>";
    exit;
}


// Check team status
if ($invite['team_status'] === 'disbanded') {
    die("The team has been disbanded.");
}

// Verify the invite belongs to the logged-in user
$user_email = null;
if ($invite['invited_user_id'] && $invite['invited_user_id'] != $user_id) {
    die("This invite is for another user.");
} elseif ($invite['invited_email']) {
    // For email invites, check if the logged-in user's email matches
    $emailQ = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $emailQ->bind_param("i", $user_id);
    $emailQ->execute();
    $user_email = $emailQ->get_result()->fetch_assoc()['email'] ?? '';
    $emailQ->close();
    if (strcasecmp($invite['invited_email'], $user_email) !== 0) {
        die("This invite is for a different email address.");
    }
}

$team_id = (int)$invite['team_id'];
$team_name = htmlspecialchars($invite['team_name']);
$member_count = (int)$invite['member_count'];
$max_players = (int)$invite['max_players'];
$team_full = ($member_count >= $max_players);

// Check if user is already a member of this team
$member_check = $conn->prepare("SELECT team_member_id FROM team_members WHERE team_id = ? AND user_id = ?");
$member_check->bind_param("ii", $team_id, $user_id);
$member_check->execute();
$already_member = $member_check->get_result()->num_rows > 0;
$member_check->close();

// Check if user is in any other team
$other_team = null;
$other_check = $conn->prepare("
    SELECT t.team_id, t.team_name
    FROM team_members tm
    JOIN teams t ON tm.team_id = t.team_id
    WHERE tm.user_id = ? AND tm.team_id != ?
    LIMIT 1
");
$other_check->bind_param("ii", $user_id, $team_id);
$other_check->execute();
$other_result = $other_check->get_result();
if ($other_result->num_rows > 0) {
    $other_team = $other_result->fetch_assoc();
}
$other_check->close();

// Handle POST actions
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        // Re-check all conditions inside a transaction
        if ($already_member) {
            $error = "You are already a member of this team.";
        } elseif ($team_full) {
            $error = "This team is now full.";
        } elseif ($other_team) {
            $error = "You are already a member of another team (<strong>" . htmlspecialchars($other_team['team_name']) . "</strong>). Please leave that team first.";
        } else {
            // Double-check member count and other team again to avoid race conditions
            $conn->begin_transaction();
            try {
                // Lock the team row to prevent concurrent additions
                $lock = $conn->prepare("SELECT players, (SELECT COUNT(*) FROM team_members WHERE team_id = ?) AS cnt FROM teams WHERE team_id = ? FOR UPDATE");
                $lock->bind_param("ii", $team_id, $team_id);
                $lock->execute();
                $lock_res = $lock->get_result()->fetch_assoc();
                $lock->close();

                if ($lock_res['cnt'] >= $lock_res['players']) {
                    throw new Exception("Team is full.");
                }

                // Check other team again (with locking if needed, but simplified here)
                $other_check2 = $conn->prepare("SELECT team_id FROM team_members WHERE user_id = ? AND team_id != ? LIMIT 1");
                $other_check2->bind_param("ii", $user_id, $team_id);
                $other_check2->execute();
                if ($other_check2->get_result()->num_rows > 0) {
                    throw new Exception("You are already in another team.");
                }
                $other_check2->close();

                // Add user as member
                $add = $conn->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
                $add->bind_param("ii", $team_id, $user_id);
                $add->execute();
                $add->close();

                // Update invitation status
                $upd = $conn->prepare("UPDATE team_invitations SET status = 'accepted' WHERE invite_id = ?");
                $upd->bind_param("i", $invite['invite_id']);
                $upd->execute();
                $upd->close();

                // Delete any pending join requests from this user to this team
                $del = $conn->prepare("DELETE FROM team_join_requests WHERE team_id = ? AND user_id = ?");
                $del->bind_param("ii", $team_id, $user_id);
                $del->execute();
                $del->close();

                // Mark the corresponding notification as read (if any)
                $notif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE token = ? AND user_id = ?");
                $notif->bind_param("si", $token, $user_id);
                $notif->execute();
                $notif->close();

                $conn->commit();

                // Redirect to the team page
                header("Location: team.php?team_id=$team_id");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to accept invite: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reject') {
        // Update invitation status to declined
        $rej = $conn->prepare("UPDATE team_invitations SET status = 'declined' WHERE invite_id = ?");
        $rej->bind_param("i", $invite['invite_id']);
        $rej->execute();
        $rej->close();

        // Mark notification as read
        $notif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE token = ? AND user_id = ?");
        $notif->bind_param("si", $token, $user_id);
        $notif->execute();
        $notif->close();

        // Redirect somewhere sensible, e.g., index.php with a message
        header("Location: ../index.php?msg=invite_declined");
        exit;
    }
}

// If we have a message or error after POST, we'll display it below.
// Otherwise, show the decision page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Team Invitation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --riot-red: #ff4655; --riot-dark: #0f1419; }
        body {
            background: var(--riot-dark);
            color: #ece8e1;
            font-family: 'Rajdhani', sans-serif;
            background-image: linear-gradient(rgba(255,70,85,0.05) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,70,85,0.05) 1px, transparent 1px);
        }
        h1 { font-family: 'Orbitron', sans-serif; text-transform: uppercase; letter-spacing: 2px; }
        .neon-red { text-shadow: 0 0 10px rgba(255,70,85,0.5); color: var(--riot-red); }
        .glass-card {
            background: rgba(31,35,38,0.8);
            border: 1px solid rgba(255,70,85,0.2);
            border-left: 4px solid var(--riot-red);
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
        .btn-riot:hover { background: #ff5e6a; transform: scale(1.02); cursor: pointer; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--riot-red);
            color: var(--riot-red);
            padding: 10px 20px;
            text-transform: uppercase;
            transition: all 0.2s;
        }
        .btn-outline:hover { background: rgba(255,70,85,0.1); }
    </style>
</head>
<body class="p-6 flex items-center justify-center min-h-screen">
    <div class="max-w-2xl w-full glass-card p-8 rounded">
        <h1 class="text-3xl md:text-4xl font-bold neon-red mb-2">Team Invitation</h1>
        <p class="text-gray-400 mb-6">You have been invited to join <span class="text-white font-bold"><?= $team_name ?></span></p>

        <?php if ($message): ?>
            <div class="bg-green-900/20 border border-green-500 text-green-400 p-4 mb-6 uppercase text-sm"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-900/20 border border-red-500 text-red-500 p-4 mb-6 uppercase text-sm"><?= $error ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
            <div class="bg-black/40 p-3 border border-gray-800">
                <span class="text-gray-500">Team</span>
                <div class="text-white font-bold"><?= $team_name ?></div>
            </div>
            <div class="bg-black/40 p-3 border border-gray-800">
                <span class="text-gray-500">Members</span>
                <div class="text-white font-bold"><?= $member_count ?> / <?= $max_players ?></div>
            </div>
        </div>

        <?php if ($already_member): ?>
            <p class="text-yellow-500 mb-4">You are already a member of this team.</p>
            <a href="team.php?team_id=<?= $team_id ?>" class="btn-riot inline-block">Go to Team</a>
        <?php elseif ($team_full): ?>
            <p class="text-red-500 mb-4">This team is already full.</p>
            <a href="../index.php" class="btn-outline inline-block">Back to Home</a>
        <?php elseif ($other_team): ?>
            <div class="bg-yellow-900/20 border border-yellow-600 text-yellow-500 p-4 mb-4">
                <p class="font-bold">You are already in another team:</p>
                <p class="text-white mt-1"><?= htmlspecialchars($other_team['team_name']) ?></p>
                <p class="mt-2 text-sm">Please leave that team before joining this one.</p>
                <a href="team.php?team_id=<?= $other_team['team_id'] ?>" class="inline-block mt-3 text-red-500 underline">Go to your current team</a>
            </div>
            <form method="post" class="inline-block mr-2">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn-outline">Decline Invite</button>
            </form>
        <?php else: ?>
            <div class="flex gap-4 mt-6">
                <form method="post" class="inline-block">
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn-riot">Accept & Join</button>
                </form>
                <form method="post" class="inline-block">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-outline">Decline</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>