<?php
include('partial/header.php');
require_once "database/dbConfig.php";

// Get game_id from URL
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
if (!$game_id) {
  die("Invalid game selection.");
}

// Fetch the game info
$stmt = $conn->prepare("SELECT name, image FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result();
$game = $game_result->fetch_assoc();
$stmt->close();

// Fetch tournaments and count teams/players for each tournament
$sql = "
    SELECT 
        t.*,
        u.username AS organizer_name,
        g.name AS game_name,
        g.image AS game_image,
        COUNT(DISTINCT tt.team_id) AS teams_joined,
        COUNT(DISTINCT tm.user_id) AS players_joined
    FROM tournaments t
    INNER JOIN users u ON t.organizer_id = u.user_id
    INNER JOIN games g ON t.game_id = g.game_id
    LEFT JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
    LEFT JOIN teams te ON te.team_id = tt.team_id
    LEFT JOIN team_members tm ON tm.team_id = te.team_id
    WHERE t.game_id = ? AND t.admin_status = 'approved'
    GROUP BY t.tournament_id
    ORDER BY t.start_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result = $stmt->get_result();

$tournaments = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $tournaments[] = $row;
  }
}
$stmt->close();

// UI gradient
$gradients = [
  'League of Legends' => 'red-pink',
  'Dota 2'            => 'purple-indigo',
  'Counter-Strike '   => 'orange-yellow',
  'Valorant'          => 'rose-orange',
  'PUBG'              => 'cyan-indigo',
  'MLBB'              => 'red-pink',
  'FIFA 24'           => 'green-teal'
];
$gradient = $gradients[$game['name']] ?? 'blue-cyan';
$image = !empty($game['image']) ? $game['image'] : 'default.png';
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

<section class="games-section" id="tournaments">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <span class="gradient-text-alt">
          <?php echo htmlspecialchars($game['name'] ?? 'Tournaments'); ?>
        </span>
      </h2>
      <p class="section-subtitle">
        Browse all tournaments for this game and join the competition
      </p>
    </div>

    <div class="games-grid">
      <?php if (empty($tournaments)): ?>
        <p>No tournaments available for this game yet.</p>
      <?php else: ?>
        <?php foreach ($tournaments as $index => $tournament): ?>
          <div class="game-card game-card-<?php echo $index; ?>">
            <div class="game-bg gradient-<?php echo $gradient; ?>"></div>

            <div class="game-icon gradient-<?php echo $gradient; ?>">
              <img src="images/games/<?php echo htmlspecialchars($image); ?>"
                alt="<?php echo htmlspecialchars($game['name']); ?>"
                class="game-img">
            </div>

            <h3 class="game-name">
              <?php echo htmlspecialchars($tournament['title']); ?>
            </h3>

            <div class="game-stats">
              <div class="game-stat">
                <span class="game-stat-label">Organizer</span>
                <span class="game-stat-value">
                  <?php echo htmlspecialchars($tournament['organizer_name']); ?>
                </span>
              </div>

              <div class="game-stat">
                <span class="game-stat-label">Start Date</span>
                <span class="game-stat-value gradient-<?php echo $gradient; ?>">
                  <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
                </span>
              </div>

              <div class="game-stat">
                <span class="game-stat-label">Teams Joined</span>
                <span class="game-stat-value gradient-<?php echo $gradient; ?>">
                  <?php echo (int)$tournament['teams_joined']; ?>
                </span>
              </div>

              <div class="game-stat">
                <span class="game-stat-label">Players Joined</span>
                <span class="game-stat-value gradient-<?php echo $gradient; ?>">
                  <?php echo (int)$tournament['players_joined']; ?>
                </span>
              </div>

              <div class="game-stat">
                <span class="game-stat-label">Team Size</span>
                <span class="game-stat-value">
                  <?php echo (int)$tournament['team_size']; ?>
                </span>
              </div>

              <div class="game-stat">
                <span class="game-stat-label">Max Teams</span>
                <span class="game-stat-value">
                  <?php echo (int)$tournament['max_participants']; ?>
                </span>
              </div>
            </div>

            <a href="tournament_details.php?tournament_id=<?php echo (int)$tournament['tournament_id']; ?>"
              class="btn-game gradient-<?php echo $gradient; ?>">
              View Details
            </a>

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