<?php
include('partial/header.php');
require_once "database/dbConfig.php";

// Get tournament_id from URL
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
if (!$tournament_id) {
  die("Invalid tournament selection.");
}

// Fetch tournament info
$sql = "
    SELECT 
        t.*, 
        u.username AS organizer_name, 
        g.name AS game_name, 
        g.image AS game_image
    FROM tournaments t
    INNER JOIN users u ON t.organizer_id = u.user_id
    INNER JOIN games g ON t.game_id = g.game_id
    WHERE t.tournament_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$tournament = $result->fetch_assoc();
$stmt->close();

if (!$tournament) {
  die("Tournament not found.");
}

// Fetch registered teams and players
$sqlTeams = "
    SELECT te.team_id, te.team_name, u.username AS leader_name, COUNT(tm.user_id) AS player_count
    FROM tournament_teams tt
    INNER JOIN teams te ON tt.team_id = te.team_id
    INNER JOIN users u ON te.leader_id = u.user_id
    LEFT JOIN team_members tm ON tm.team_id = te.team_id
    WHERE tt.tournament_id = ?
    GROUP BY te.team_id
    ORDER BY te.team_name ASC
";
$stmt = $conn->prepare($sqlTeams);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$resultTeams = $stmt->get_result();
$teams = [];
while ($row = $resultTeams->fetch_assoc()) {
  $teams[] = $row;
}
$stmt->close();

// Determine gradient for UI
$gradients = [
  'League of Legends' => 'red-pink',
  'Dota 2'            => 'purple-indigo',
  'Counter-Strike '   => 'orange-yellow',
  'Valorant'          => 'rose-orange',
  'PUBG'              => 'cyan-indigo',
  'MLBB'              => 'red-pink',
  'FIFA 24'           => 'green-teal'
];
$gradient = $gradients[$tournament['game_name']] ?? 'blue-cyan';
$image = !empty($tournament['game_image']) ? $tournament['game_image'] : 'default.png';

// Check if registration is open
$today = date('Y-m-d');
$registration_open = ($today >= $tournament['registration_start_date'] && $today <= $tournament['registration_deadline']);
?>

<div class="strike-overlay">
  <div class="strike strike-1"></div>
  <div class="strike strike-2"></div>
</div>

<div class="main-wrapper">
  <div class="bg-gradient"></div>
  <div class="grid-overlay"></div>

  <div class="floating-elements">
    <?php for ($i = 0; $i < 8; $i++): ?>
      <div class="floating-cube cube-<?php echo $i; ?>">
        <div class="cube-inner"></div>
      </div>
    <?php endfor; ?>

    <?php for ($i = 0; $i < 5; $i++): ?>
      <div class="glowing-orb orb-<?php echo $i; ?>"></div>
    <?php endfor; ?>

    <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="hexagon hex-<?php echo $i; ?>">
        <svg viewBox="0 0 100 100">
          <polygon points="50 1 95 25 95 75 50 99 5 75 5 25"
            fill="none" stroke-width="2" opacity="0.3" />
        </svg>
      </div>
    <?php endfor; ?>
  </div>
</div>

<section class="games-section" id="tournament-details">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <span class="gradient-text-alt">
          <?php echo htmlspecialchars($tournament['title']); ?>
        </span>
      </h2>
      <p class="section-subtitle">
        Tournament Details - <?php echo htmlspecialchars($tournament['game_name']); ?>
      </p>
    </div>

    <div class="games-grid">
      <div class="game-card game-card-0">
        <div class="game-bg gradient-<?php echo $gradient; ?>"></div>

        <div class="game-icon gradient-<?php echo $gradient; ?>">
          <img src="images/games/<?php echo htmlspecialchars($image); ?>"
            alt="<?php echo htmlspecialchars($tournament['game_name']); ?>"
            class="game-img">
        </div>

        <h3 class="game-name"><?php echo htmlspecialchars($tournament['title']); ?></h3>

        <div class="game-stats">
          <div class="game-stat">
            <span class="game-stat-label">Organizer</span>
            <span class="game-stat-value"><?php echo htmlspecialchars($tournament['organizer_name']); ?></span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Start Date</span>
            <span class="game-stat-value gradient-<?php echo $gradient; ?>">
              <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Registration</span>
            <span class="game-stat-value gradient-<?php echo $gradient; ?>">
              <?php echo date('d M Y', strtotime($tournament['registration_start_date'])); ?>
              -
              <?php echo date('d M Y', strtotime($tournament['registration_deadline'])); ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Team Size</span>
            <span class="game-stat-value"><?php echo (int)$tournament['team_size']; ?></span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Max Teams</span>
            <span class="game-stat-value"><?php echo (int)$tournament['max_participants']; ?></span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Teams Joined</span>
            <span class="game-stat-value gradient-<?php echo $gradient; ?>">
              <?php echo count($teams); ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Players Joined</span>
            <span class="game-stat-value gradient-<?php echo $gradient; ?>">
              <?php
              $players = 0;
              foreach ($teams as $team) $players += $team['player_count'];
              echo $players;
              ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Fee</span>
            <span class="game-stat-value">$<?php echo number_format((float)$tournament['fee'], 2); ?></span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Prize Pool</span>
            <span class="game-stat-value">$<?php echo number_format((float)$tournament['prize_pool'], 2); ?></span>
          </div>
        </div>

        <?php if ($registration_open): ?>
          <a href="join_tournament.php?tournament_id=<?php echo (int)$tournament['tournament_id']; ?>"
            class="btn-game gradient-<?php echo $gradient; ?>">Join Tournament</a>
        <?php else: ?>
          <span class="btn-game gradient-gray">Registration Closed</span>
        <?php endif; ?>

        <div class="game-particles">
          <div class="game-particle particle-1 gradient-<?php echo $gradient; ?>"></div>
          <div class="game-particle particle-2 gradient-<?php echo $gradient; ?>"></div>
          <div class="game-particle particle-3 gradient-<?php echo $gradient; ?>"></div>
        </div>

        <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
      </div>
    </div>

    <div class="section-header mt-5">
      <h3>Registered Teams</h3>
    </div>

    <div class="games-grid">
      <?php if (empty($teams)): ?>
        <p>No teams have joined yet.</p>
      <?php else: ?>
        <?php foreach ($teams as $index => $team): ?>
          <div class="game-card game-card-<?php echo $index; ?>">
            <div class="game-bg gradient-<?php echo $gradient; ?>"></div>
            <div class="game-icon gradient-<?php echo $gradient; ?>">
              <img src="images/default_team.png" alt="Team Logo" class="game-img">
            </div>
            <h3 class="game-name"><?php echo htmlspecialchars($team['team_name']); ?></h3>
            <div class="game-stats">
              <div class="game-stat">
                <span class="game-stat-label">Leader</span>
                <span class="game-stat-value"><?php echo htmlspecialchars($team['leader_name']); ?></span>
              </div>
              <div class="game-stat">
                <span class="game-stat-label">Players</span>
                <span class="game-stat-value"><?php echo (int)$team['player_count']; ?></span>
              </div>
            </div>
            <div class="game-particles">
              <div class="game-particle particle-1 gradient-<?php echo $gradient; ?>"></div>
              <div class="game-particle particle-2 gradient-<?php echo $gradient; ?>"></div>
              <div class="game-particle particle-3 gradient-<?php echo $gradient; ?>"></div>
            </div>
            <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include('partial/footer.php'); ?>