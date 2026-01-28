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

<style>
  /* RIOT STYLE MODERN GAMING THEME */
  :root {
    --riot-red: #ff4655;
    --riot-dark: #0f1923;
    --riot-light: #ece8e1;
    --riot-gray: #1f2326;
    --bg-dark: #080d12;
  }

  body {
    background-color: var(--bg-dark);
    color: var(--riot-light);
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    overflow-x: hidden;
  }

  /* Background & Overlays */
  .main-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    background: radial-gradient(circle at center, #1a2a33 0%, #080d12 100%);
  }

  .grid-overlay {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 50px 50px;
  }

  .strike-overlay {
    position: fixed;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
  }

  .strike {
    position: absolute;
    background: rgba(255, 70, 85, 0.05);
    transform: rotate(-45deg);
  }

  .strike-1 {
    width: 100%;
    height: 2px;
    top: 20%;
  }

  .strike-2 {
    width: 100%;
    height: 100px;
    top: 50%;
  }

  /* Gradients */
  .gradient-red-pink {
    background: linear-gradient(135deg, #ff4655, #ff858d);
  }

  .gradient-purple-indigo {
    background: linear-gradient(135deg, #7b2ff7, #3f51b5);
  }

  .gradient-orange-yellow {
    background: linear-gradient(135deg, #ff9800, #ffeb3b);
  }

  .gradient-rose-orange {
    background: linear-gradient(135deg, #f43f5e, #fb923c);
  }

  .gradient-cyan-indigo {
    background: linear-gradient(135deg, #06b6d4, #6366f1);
  }

  .gradient-green-teal {
    background: linear-gradient(135deg, #10b981, #14b8a6);
  }

  .gradient-blue-cyan {
    background: linear-gradient(135deg, #3b82f6, #06b6d4);
  }

  .gradient-gray {
    background: #333;
  }

  .gradient-text-alt {
    background: linear-gradient(90deg, var(--riot-red), #fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase;
    font-weight: 900;
    letter-spacing: 2px;
  }

  /* Container & Layout */
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 80px 20px;
    position: relative;
    z-index: 1;
  }

  .section-header {
    border-left: 5px solid var(--riot-red);
    padding-left: 20px;
    margin-bottom: 40px;
  }

  .section-title {
    font-size: 3rem;
    margin: 0;
    text-transform: uppercase;
  }

  .section-subtitle {
    opacity: 0.6;
    font-weight: 600;
    letter-spacing: 1px;
  }

  /* Grid & Cards */
  .games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
  }

  /* UNIQUE FULL SIZE FOR TOURNAMENT DETAILS */
  .full-size-card {
    grid-column: 1 / -1;
    width: 100%;
  }

  .game-card {
    background: var(--riot-gray);
    position: relative;
    padding: 40px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    clip-path: polygon(0 0, 100% 0, 100% 95%, 95% 100%, 0 100%);
    transition: 0.3s transform ease;
  }

  .game-card:hover {
    transform: translateY(-5px);
    border-color: var(--riot-red);
  }

  .game-icon {
    width: 100px;
    height: 100px;
    padding: 10px;
    margin-bottom: 20px;
    clip-path: polygon(20% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 20%);
  }

  .game-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .game-name {
    font-size: 2rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 15px;
  }

  .game-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
    margin-bottom: 35px;
  }

  .game-stat {
    display: flex;
    flex-direction: column;
  }

  .game-stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    opacity: 0.5;
    letter-spacing: 1px;
    margin-bottom: 5px;
  }

  .game-stat-value {
    font-weight: 700;
    font-size: 1.1rem;
    color: #fff;
  }

  /* Buttons */
  .btn-game {
    display: inline-block;
    min-width: 250px;
    text-align: center;
    padding: 18px 30px;
    text-transform: uppercase;
    font-weight: 900;
    text-decoration: none;
    letter-spacing: 2px;
    color: #fff;
    transition: 0.3s;
    border: none;
    cursor: pointer;
  }

  .btn-game:hover {
    filter: brightness(1.2);
    box-shadow: 0 0 25px rgba(255, 70, 85, 0.4);
  }

  .game-glow {
    position: absolute;
    bottom: -30px;
    right: -30px;
    width: 250px;
    height: 250px;
    filter: blur(80px);
    opacity: 0.15;
    z-index: -1;
  }

  .mt-5 {
    margin-top: 100px;
  }

  /* Mobile */
  @media (max-width: 768px) {
    .section-title {
      font-size: 2rem;
    }

    .games-grid {
      grid-template-columns: 1fr;
    }

    .game-stats {
      grid-template-columns: 1fr 1fr;
    }

    .btn-game {
      width: 100%;
    }
  }
</style>

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
      <div class="game-card full-size-card">
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
            <span class="game-stat-value">
              <?php echo date('d M Y', strtotime($tournament['start_date'])); ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Registration Period</span>
            <span class="game-stat-value">
              <?php echo date('d M Y', strtotime($tournament['registration_start_date'])); ?> - <?php echo date('d M Y', strtotime($tournament['registration_deadline'])); ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Team Size</span>
            <span class="game-stat-value"><?php echo (int)$tournament['team_size']; ?> Players</span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Slot Limit</span>
            <span class="game-stat-value"><?php echo (int)$tournament['max_participants']; ?> Teams</span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Teams Joined</span>
            <span class="game-stat-value">
              <?php echo count($teams); ?> / <?php echo (int)$tournament['max_participants']; ?>
            </span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Entry Fee</span>
            <span class="game-stat-value">$<?php echo number_format((float)$tournament['fee'], 2); ?></span>
          </div>

          <div class="game-stat">
            <span class="game-stat-label">Prize Pool</span>
            <span class="game-stat-value" style="font-size: 1.5rem; color: var(--riot-red);">
              $<?php echo number_format((float)$tournament['prize_pool'], 2); ?>
            </span>
          </div>
        </div>

        <?php if ($registration_open): ?>
          <a href="join_tournament.php?tournament_id=<?php echo (int)$tournament['tournament_id']; ?>"
            class="btn-game gradient-<?php echo $gradient; ?>">Join Tournament</a>
        <?php else: ?>
          <span class="btn-game gradient-gray">Registration Closed</span>
        <?php endif; ?>

        <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
      </div>
    </div>

    <div class="section-header mt-5">
      <h3 class="section-title" style="font-size: 1.8rem;">Registered Teams</h3>
    </div>

    <div class="games-grid">
      <?php if (empty($teams)): ?>
        <p style="opacity: 0.5; grid-column: 1/-1;">No teams have joined yet. Be the first!</p>
      <?php else: ?>
        <?php foreach ($teams as $index => $team): ?>
          <div class="game-card">
            <div class="game-icon gradient-<?php echo $gradient; ?>" style="width: 60px; height: 60px;">
              <img src="images/default_team.png" alt="Team Logo" class="game-img">
            </div>
            <h3 class="game-name" style="font-size: 1.3rem;"><?php echo htmlspecialchars($team['team_name']); ?></h3>
            <div class="game-stats" style="grid-template-columns: 1fr; gap: 10px;">
              <div class="game-stat">
                <span class="game-stat-label">Leader</span>
                <span class="game-stat-value"><?php echo htmlspecialchars($team['leader_name']); ?></span>
              </div>
              <div class="game-stat">
                <span class="game-stat-label">Roster</span>
                <span class="game-stat-value"><?php echo (int)$team['player_count']; ?> Players</span>
              </div>
            </div>
            <div class="game-glow gradient-<?php echo $gradient; ?>"></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include('partial/footer.php'); ?>