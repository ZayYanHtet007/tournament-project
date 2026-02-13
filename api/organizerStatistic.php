<?php
session_start();
require_once "../partial/init.php";

header('Content-Type: application/json');


// -------------------- Backend API --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {
  header('Content-Type: application/json');

  if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_organizer']) || $_SESSION['is_organizer'] != 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }

  $organizer_id = $_SESSION['user_id'];

  // POST params
  $mode  = $_POST['mode'] ?? 'date';
  $month = isset($_POST['month']) ? intval($_POST['month']) : null;
  $year  = isset($_POST['year']) ? intval($_POST['year']) : null;

  $dateFilter = '';
  $params = [];
  $types = '';

  if ($mode === 'date' && $month && $year) {
    $dateFilter = " AND MONTH(t.created_at)=? AND YEAR(t.created_at)=?";
    $params[] = $month;
    $params[] = $year;
    $types .= 'ii';
  }

  // --- Bar Chart ---
  $barLabels = [];
  $barData = [];
  $sql = "
        SELECT TRIM(g.name) AS game_name, COUNT(tt.id) AS total_teams
        FROM games g
        LEFT JOIN tournaments t ON t.game_id = g.game_id
        LEFT JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id
        WHERE t.organizer_id = ? $dateFilter
        GROUP BY g.name
        HAVING total_teams > 0
        ORDER BY total_teams DESC
        LIMIT 4
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
  }
  $typesBar = 'i' . $types;
  $stmt->bind_param($typesBar, $organizer_id, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $barLabels[] = $row['game_name'];
    $barData[] = (int)$row['total_teams'];
  }
  $stmt->close();

  // --- Line Chart ---
  $lineData = array_fill(0, 12, 0);
  $sql = "
        SELECT MONTH(t.created_at) AS month_num, SUM(t.fee * IFNULL(tt_count.team_count,0)) AS revenue
        FROM tournaments t
        LEFT JOIN (
            SELECT tournament_id, COUNT(*) AS team_count
            FROM tournament_teams
            GROUP BY tournament_id
        ) tt_count ON tt_count.tournament_id = t.tournament_id
        WHERE t.organizer_id = ? " . ($mode === 'date' && $year ? " AND YEAR(t.created_at)=?" : "") . "
        GROUP BY MONTH(t.created_at)
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
  }
  if ($mode === 'date' && $year) {
    $stmt->bind_param('ii', $organizer_id, $year);
  } else {
    $stmt->bind_param('i', $organizer_id);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $monthIdx = (int)$row['month_num'] - 1;
    $lineData[$monthIdx] = (float)$row['revenue'];
  }
  $stmt->close();

  // --- Totals ---
  $sql = "SELECT COUNT(tt.id) AS total_teams FROM tournament_teams tt JOIN tournaments t ON t.tournament_id = tt.tournament_id WHERE t.organizer_id = ? $dateFilter";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
  }
  $stmt->bind_param('i' . $types, $organizer_id, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $totalTeams = $res->fetch_assoc()['total_teams'] ?? 0;
  $stmt->close();

  $sql = "SELECT SUM(t.team_size) AS total_players FROM tournaments t JOIN tournament_teams tt ON tt.tournament_id = t.tournament_id WHERE t.organizer_id = ? $dateFilter";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
  }
  $stmt->bind_param('i' . $types, $organizer_id, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $totalPlayers = $res->fetch_assoc()['total_players'] ?? 0;
  $stmt->close();

  echo json_encode([
    'barChart' => ['labels' => $barLabels, 'data' => $barData],
    'lineChart' => ['data' => $lineData],
    'totals' => ['teams' => (int)$totalTeams, 'players' => (int)$totalPlayers]
  ]);
  exit;
}

// -------------------- Frontend HTML --------------------
