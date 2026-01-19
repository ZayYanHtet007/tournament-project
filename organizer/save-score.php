<?php
session_start();
require_once "../database/dbConfig.php";
require_once "bracket-advance.php";

/* Single-match score updater (kept for AJAX or inline single updates) */
/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['user_id']) || !$_SESSION['is_organizer'] || $_SESSION['organizer_status'] !== 'approved') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$match_id = (int)($_POST['match_id'] ?? 0);
$s1 = isset($_POST['team1_score']) ? (int)$_POST['team1_score'] : null;
$s2 = isset($_POST['team2_score']) ? (int)$_POST['team2_score'] : null;

if (!$match_id || $s1 === null || $s2 === null) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

/* ---------- GET MATCH ---------- */
$stmt = $conn->prepare("SELECT * FROM matches WHERE match_id = ?");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
if (!$match) {
  http_response_code(404);
  echo json_encode(['error' => 'Match not found']);
  exit;
}

/* Verify organizer owns the tournament for this match */
$stmt2 = $conn->prepare("SELECT organizer_id FROM tournaments WHERE tournament_id = ?");
$stmt2->bind_param("i", $match['tournament_id']);
$stmt2->execute();
$tRow = $stmt2->get_result()->fetch_assoc();
if (!$tRow || $tRow['organizer_id'] != $_SESSION['user_id']) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized for this tournament']);
  exit;
}

/* Determine winner. For knockout rounds, ties are not advanced. */
$winner = null;
if ($s1 > $s2) $winner = $match['team1_id'];
if ($s2 > $s1) $winner = $match['team2_id'];
$prev_winner = $match['winner_team_id'] ?? null;

/* ---------- SAVE SCORE ---------- */
$insertScore = $conn->prepare("
    INSERT INTO match_scores (match_id, team1_score, team2_score, set_number)
    VALUES (?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      team1_score = VALUES(team1_score),
      team2_score = VALUES(team2_score),
      created_at = CURRENT_TIMESTAMP
");
$insertScore->bind_param("iii", $match_id, $s1, $s2);
$insertScore->execute();

/* ---------- UPDATE MATCH ---------- */
/* For knockout rounds (non-group), only mark completed if there is a (non-null) winner */
if ($match['round'] !== 'group') {
  if ($winner !== null && $winner !== '') {
    $updateMatch = $conn->prepare("
            UPDATE matches
            SET winner_team_id = ?, status = 'completed', last_update = CURRENT_TIMESTAMP
            WHERE match_id = ?
        ");
    $updateMatch->bind_param("ii", $winner, $match_id);
    $updateMatch->execute();
  } else {
    // Tie or not decided -> clear winner and set pending
    $updateMatch = $conn->prepare("
            UPDATE matches
            SET winner_team_id = NULL, status = 'pending', last_update = CURRENT_TIMESTAMP
            WHERE match_id = ?
        ");
    $updateMatch->bind_param("i", $match_id);
    $updateMatch->execute();
  }
} else {
  // group matches can be completed even with ties
  $updateMatch = $conn->prepare("
        UPDATE matches
        SET winner_team_id = ?, status = 'completed', last_update = CURRENT_TIMESTAMP
        WHERE match_id = ?
    ");
  // allow NULL winner for tie in group - prepared statement with NULL is easiest via conditional SQL
  if ($winner === null) {
    $updateMatch = $conn->prepare("
            UPDATE matches
            SET winner_team_id = NULL, status = 'completed', last_update = CURRENT_TIMESTAMP
            WHERE match_id = ?
        ");
    $updateMatch->bind_param("i", $match_id);
  } else {
    $updateMatch->bind_param("ii", $winner, $match_id);
  }
  $updateMatch->execute();
}

/* ---------- ADVANCE BRACKET IF APPLICABLE ---------- */
if (in_array($match['round'], ['quarterfinal', 'semifinal'])) {
  // If previous winner existed and now cleared, advanceBracket will clear downstream assignments accordingly.
  advanceBracket($conn, $match, $winner);
}

/* ---------- UPDATE STANDINGS IF GROUP ---------- */
if ($match['round'] === 'group') {
  require_once "update-standings.php";
  updateGroupStandings($conn, $match['tournament_id']);
  // Fill quarterfinal placeholders after standings updated
  populateQuarterFinals($conn, $match['tournament_id']);
}

echo json_encode(['success' => true]);
exit;
