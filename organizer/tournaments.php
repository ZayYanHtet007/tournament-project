<?php
session_start();
require_once "../database/dbConfig.php";
include("header.php");

/* ======================
   ACCESS CONTROL
====================== */
if (
  !isset($_SESSION['user_id']) ||
  !isset($_SESSION['is_organizer']) ||
  $_SESSION['is_organizer'] != 1
) {
  header("Location: ../login.php");
  exit;
}

$organizer_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Organizer';

/* FILTER LOGIC */
$category = $_GET['category'] ?? 'all';
$valid_categories = [ 'upcoming', 'ongoing', 'completed'];

$query = "
    SELECT 
        t.tournament_id,
        t.title,
        g.name AS game_name,
        t.status,
        t.admin_status,
        t.created_at
    FROM tournaments t
    JOIN games g ON t.game_id = g.game_id
    WHERE t.organizer_id = ? AND t.admin_status = 'approved'
";

if (in_array($category, $valid_categories)) {
    $query .= " AND t.status = ?";
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);

if (in_array($category, $valid_categories)) {
    $stmt->bind_param("is", $organizer_id, $category);
} else {
    $stmt->bind_param("i", $organizer_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tournament Management | Official Portal</title>
  <style>
    :root {
      --riot-blue: #00eeff;
      --riot-deep-blue: #051923;
      --riot-bg: #010a13;
      --riot-border: rgba(0, 238, 255, 0.3);
      --riot-gold: #c8aa6e;
      --riot-gray: #7e7e7e;
      --panel-bg: rgba(5, 25, 35, 0.8);
    }

    body {
      background-color: var(--riot-bg);
      color: #f0f5f5;
      font-family: 'Inter', 'Segoe UI', Helvetica, Arial, sans-serif;
      margin: 0;
      background-image: 
        radial-gradient(circle at 50% 0%, rgba(0, 238, 255, 0.1) 0%, transparent 50%);
    }

    /* Formal Header */
    .hero-content {
      padding: 60px 20px 20px;
      text-align: center;
      border-bottom: 1px solid var(--riot-border);
      background: linear-gradient(to bottom, rgba(0, 238, 255, 0.05), transparent);
      position: relative; /* Added for absolute positioning of dropdown */
    }

    /* Category Dropdown Styling */
    .category-filter {
        position: absolute;
        top: 60px;
        right: 140px;
        text-align: left;
    }

    .category-filter label {
        display: block;
        font-size: 0.65rem;
        color: var(--riot-gray);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }

    .category-filter select {
        background: var(--riot-deep-blue);
        color: var(--riot-blue);
        border: 1px solid var(--riot-border);
        padding: 8px 12px;
        font-size: 0.75rem;
        text-transform: uppercase;
        outline: none;
        cursor: pointer;
    }

    .hero-content h1 {
      font-size: 2rem;
      margin: 0;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 5px;
      font-weight: 300;
    }

    .hero-content .subtitle {
      color: var(--riot-blue);
      font-size: 0.8rem;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-top: 10px;
      opacity: 0.8;
    }

    /* Dashboard Grid */
    .dashboard {
      max-width: 1200px;
      margin: 50px auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      padding: 0 20px;
    }

    /* Formal Organizer Card */
    .riot-card {
      background: var(--panel-bg);
      border: 1px solid var(--riot-border);
      backdrop-filter: blur(10px);
      position: relative;
      padding: 25px;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    .riot-card:hover {
      border-color: var(--riot-blue);
      box-shadow: 0 0 20px rgba(0, 238, 255, 0.2);
      transform: translateY(-5px);
    }

    /* Technical details top right */
    .riot-card::before {
        content: "Tour ID : " attr(data-id);
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 9px;
        color: var(--riot-blue);
        font-family: monospace;
        opacity: 0.5;
    }

    .riot-card h3 {
      font-size: 1.2rem;
      color: #fff;
      margin: 15px 0 5px 0;
      letter-spacing: 1px;
      font-weight: 600;
    }

    .game-badge {
      color: var(--riot-blue);
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      border-left: 2px solid var(--riot-blue);
      padding-left: 10px;
    }

    .stats-row {
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      padding-top: 20px;
      margin-top: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .status-text {
      color: var(--riot-gold);
      text-transform: uppercase;
      font-weight: 700;
      font-size: 0.7rem;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .status-dot {
        width: 6px;
        height: 6px;
        background: var(--riot-gold);
        box-shadow: 0 0 10px var(--riot-gold);
        border-radius: 50%;
    }

    /* Action Button */
    .btn-riot {
      display: inline-block;
      padding: 10px 20px;
      text-transform: uppercase;
      font-weight: 700;
      font-size: 0.75rem;
      letter-spacing: 1px;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
      background: rgba(0, 238, 255, 0.1);
      border: 1px solid var(--riot-blue);
      color: var(--riot-blue);
    }

    .btn-riot:hover {
      background: var(--riot-blue);
      color: #000;
    }

    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 80px;
      border: 1px solid var(--riot-border);
      background: rgba(0, 238, 255, 0.02);
    }

    .date-label {
        color: var(--riot-gray);
        font-size: 0.7rem;
        text-transform: uppercase;
    }
  </style>
</head>

<body>

  <div id="mobileOverlay" class="mobile-overlay" tabindex="-1"></div>

    <div class="hero-content">
      <h1>Tournament Control Center</h1>
      <div class="subtitle">Operational Overview • Organizer System</div>

      <div class="category-filter">
          <label>Filter Status</label>
          <select onchange="window.location.href='?category=' + this.value">
              <option value="all" <?= $category == 'all' ? 'selected' : '' ?>>All</option>
              <option value="upcoming" <?= $category == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
              <option value="ongoing" <?= $category == 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
              <option value="completed" <?= $category == 'completed' ? 'selected' : '' ?>>Completed</option>
          </select>
      </div>
    </div>

  <section class="dashboard">

    <?php if ($result->num_rows === 0): ?>
      <div class="empty-state">
        <p style="font-size: 1rem; color: var(--riot-gray); margin-bottom: 25px; letter-spacing: 1px;">NO DEPLOYMENTS FOUND FOR THIS CATEGORY</p>
        <a href="createTournament.php" class="btn-riot" style="padding: 15px 50px;">Create New Tournament</a>
      </div>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="riot-card" data-id="<?= $row['tournament_id'] ?>">
        <div>
          <span class="game-badge"><?= htmlspecialchars($row['game_name']) ?></span>
          <h3><?= htmlspecialchars($row['title']) ?></h3>
          <div class="status-text">
              <span class="status-dot"></span>
              <?= htmlspecialchars($row['status']) ?>
          </div>
        </div>

        <div class="stats-row">
          <div>
              <div class="date-label">System Date</div>
              <div style="font-size: 0.85rem; font-weight: 500;"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
          </div>
          <a href="manageTournament.php?tournament_id=<?= $row['tournament_id'] ?>" class="btn-riot">
            Manage
          </a>
        </div>
      </div>
    <?php endwhile; ?>

  </section>

  <script>
    (function(){
      const btn = document.getElementById('mobileMenuBtn');
      const sidebar = document.getElementById('mobileSidebar');
      const overlay = document.getElementById('mobileOverlay');

      function openMenu(){
        document.body.classList.add('mobile-menu-open');
        if(btn) btn.setAttribute('aria-expanded','true');
        sidebar.setAttribute('aria-hidden','false');
      }
      function closeMenu(){
        document.body.classList.remove('mobile-menu-open');
        if(btn) btn.setAttribute('aria-expanded','false');
        sidebar.setAttribute('aria-hidden','true');
      }

      btn && btn.addEventListener('click', function(e){
        if(document.body.classList.contains('mobile-menu-open')) closeMenu(); else openMenu();
      });
      overlay && overlay.addEventListener('click', closeMenu);
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeMenu(); });
    })();
  </script>

<?php include("footer.php"); ?>
</body>
</html>