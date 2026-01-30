<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'database/dbConfig.php';

$tournament_id = $_GET['tournament_id'] ?? 0;
if (!$tournament_id) {
  die('Invalid tournament');
}

/* ================= FETCH TOURNAMENT ================= */
$sqlTournament = "
SELECT 
  t.tournament_id,
  t.title AS tournament_title,
  t.status,
  t.fee,
  t.prize_pool,
  t.max_participants,
  t.registration_deadline,
  t.registration_start_date,
  t.created_at,
  g.name AS game_name,
  g.genre,
  g.image AS game_image
FROM tournaments t
JOIN games g ON t.game_id = g.game_id
WHERE t.tournament_id = ?
";
$stmt = $pdo->prepare($sqlTournament);
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
  die('Tournament not found');
}

/* ================= FETCH ANNOUNCEMENT ================= */
$sqlAnnounce = "
SELECT rules, system_info
FROM tournament_announcements
WHERE tournament_id = ?
";
$stmt = $pdo->prepare($sqlAnnounce);
$stmt->execute([$tournament_id]);
$announce = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= JOINED TEAMS ================= */
$sqlJoined = "
SELECT COUNT(*) 
FROM tournament_teams
WHERE tournament_id = ?
";
$stmt = $pdo->prepare($sqlJoined);
$stmt->execute([$tournament_id]);
$joinedTeams = $stmt->fetchColumn();

/* ================= RULES ================= */
$rulesText = $announce['rules'] ?? "
All matches must follow fair-play rules.
Any form of cheating leads to disqualification.
Teams must be ready 15 minutes before match time.
Organizer decisions are final.
";

/* ================= DYNAMIC SYSTEM ================= */
function generateTournamentSystem($genre, $maxTeams) {
  if ($genre === 'BATTLE_ROYALE') {
    return "
Points Based League Stage
All Teams Will Play 3 Matches

Rank Points:
1 → 20
2 → 16
3 → 14
4 → 12
5 → 10
6 → 8
7 → 6
8 → 4
9+ → 1

Each Kill = 1 Point
Highest Total Points Wins
";
  }

if ($maxTeams === 12) {
    return "
Group Stage (3 Teams Per Group)
BO3 Matches
2:0 → 3 pts
2:1 → 2 pts (loser 1 pt)
Top 8 → Quarter Final
Top 4 → Semi Final
Semi Losers → 2nd Runner Up
Semi Losers → 2nd Runner-Up Final 
Semi Winners → Grand Final (BO5)
";
  }

  if ($maxTeams === 16) {
    return "
Group Stage (4 Teams Per Group)
BO3 Matches
2:0 → 3 pts
2:1 → 2 pts (loser 1 pt)
Top 8 → Quarter Final
Top 4 → Semi Final
Semi Losers → 2nd Runner-Up Final 
Semi Winners → Grand Final (BO5)
";
  }

  if ($maxTeams === 24) {
    return "
Group Stage (6 Teams Per Group)
BO3 Matches
2:0 → 3 pts
2:1 → 2 pts (loser 1 pt)
Top 8 → Quarter Final
Top 4 → Semi Final
Semi Losers → 2nd Runner-Up Final 
Semi Winners → Grand Final (BO5)
";
  }

  return "Single Elimination Format";
}

$systemText = $announce['system_info']
  ?? generateTournamentSystem($tournament['genre'], $tournament['max_participants']);

$isCompleted = ($tournament['status'] === 'completed');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tournament['tournament_title']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
  background:#121212;
  color:#fff;
  font-family:'Segoe UI', sans-serif;
  padding:20px;
}
.container {
  max-width:1000px;
  margin:auto;
  background:#1e1e1e;
  border-radius:14px;
  overflow:hidden;
}
.header {
  padding:20px;
}
.title {
  font-size:26px;
  color:#ffcc00;
  font-weight:bold;
}
.game {
  color:#ccc;
}
.image {
  height:260px;
  background-size:cover;
  background-position:center;
}

/* ===== DATE CARDS ===== */
.dates {
  display:flex;
  gap:12px;
  padding:20px;
}
.date-card {
  flex:1;
  background:#2a2a2a;
  border-radius:10px;
  padding:12px;
  text-align:center;
}
.date-card.reg-start { background:#00c6ff; color:#000; }
.date-card.reg-end { background:#ff416c; }
.date-card.tour-start { background:#ffcc00; color:#000; }

.date-label {
  font-size:12px;
  font-weight:bold;
}
.date-value {
  margin-top:4px;
  font-size:13px;
}

/* ===== PRIZE ===== */
.prize {
  padding:0 20px 20px;
  font-size:18px;
  color:#00ffcc;
  font-weight:bold;
}

/* ===== CONTENT ===== */
.content {
  padding:20px;
}
.grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:20px;
}
.section-title {
  font-size:17px;
  margin-bottom:8px;
  color:#00c6ff;
}

/* ===== SCROLL BOX ===== */
.scroll-box {
  max-height:260px;
  overflow-y:auto;
  background:#151515;
  border-radius:10px;
  padding:15px;
  line-height:1.6;
  white-space:pre-line;
}
.scroll-box::-webkit-scrollbar {
  width:6px;
}
.scroll-box::-webkit-scrollbar-thumb {
  background:#00c6ff;
  border-radius:10px;
}
.scroll-box::-webkit-scrollbar-track {
  background:#111;
}

/* ===== ACTIONS ===== */
.fee {
  margin-top:20px;
  font-size:15px;
  color:#ffcc00;
}
.joined {
  margin-top:5px;
  color:#ccc;
}
.actions {
  margin-top:20px;
}
.checkbox {
  display:flex;
  gap:10px;
  align-items:center;
}
.checkbox input {
  width:18px;
  height:18px;
}
.warning {
  display:none;
  margin-top:10px;
  background:#ff416c;
  padding:8px;
  border-radius:6px;
}
button {
  margin-top:15px;
  width:100%;
  padding:12px;
  border:none;
  border-radius:8px;
  font-weight:bold;
  cursor:pointer;
  background:linear-gradient(45deg,#00c6ff,#0072ff);
  color:#fff;
}
button:disabled {
  background:#555;
  cursor:not-allowed;
}
.readonly {
  margin-top:15px;
  background:#333;
  padding:12px;
  text-align:center;
  border-radius:8px;
}

@media(max-width:768px){
  .grid { grid-template-columns:1fr; }
  .dates { flex-direction:column; }
}
</style>
</head>

<body>

<div class="container">

  <div class="header">
    <div class="title"><?= htmlspecialchars($tournament['tournament_title']) ?></div>
    <div class="game"><?= htmlspecialchars($tournament['game_name']) ?></div>
  </div>

  <div class="image" style="background-image:url('<?= htmlspecialchars($tournament['game_image'] ?:'images/games/defaultTournament.jpg') ?>')"></div>

  <!-- DATE CARDS -->
  <div class="dates">
    <div class="date-card reg-start">
      <div class="date-label">Reg Start</div>
      <div class="date-value"><?= date('M d, Y', strtotime($tournament['created_at'])) ?></div>
    </div>
    <div class="date-card reg-end">
      <div class="date-label">Reg End</div>
      <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_deadline'])) ?></div>
    </div>
    <div class="date-card tour-start">
      <div class="date-label">Tournament Start</div>
      <div class="date-value"><?= date('M d, Y', strtotime($tournament['registration_start_date'])) ?></div>
    </div>
  </div>

  <div class="prize">💰 Prize Pool: $<?= number_format($tournament['prize_pool'],2) ?></div>

  <div class="content">

    <div class="grid">
      <div>
        <div class="section-title">📜 Rules & Regulations</div>
        <div class="scroll-box"><?= nl2br(htmlspecialchars($rulesText)) ?></div>
      </div>

      <div>
        <div class="section-title">⚙ Tournament System</div>
        <div class="scroll-box"><?= nl2br(htmlspecialchars($systemText)) ?></div>
      </div>
    </div>

    <div class="fee">
      Registration Fee: $<?= number_format($tournament['fee'],2) ?>
    </div>
    <div class="joined">
      Joined Teams: <?= $joinedTeams ?> / <?= $tournament['max_participants'] ?>
    </div>

    <?php if ($isCompleted): ?>
      <div class="readonly">Tournament completed. Registration closed.</div>
    <?php else: ?>
      <div class="actions">
        <div class="checkbox">
          <input type="checkbox" id="agree">
          <label for="agree">I agree to the rules & regulations</label>
        </div>
        <div id="warn" class="warning">Must agree rules and regulations to register</div>
        <button id="registerBtn" disabled>Register Now</button>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
const agree = document.getElementById('agree');
const btn = document.getElementById('registerBtn');
const warn = document.getElementById('warn');

if (agree) {
  agree.addEventListener('change', () => {
    btn.disabled = !agree.checked;
    warn.style.display = 'none';
  });
  btn.addEventListener('click', e => {
    if (!agree.checked) {
      e.preventDefault();
      warn.style.display = 'block';
    }
  });
}
</script>

</body>
</html>
