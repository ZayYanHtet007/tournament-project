<?php
session_start();
require_once "../database/dbConfig.php";
require_once "bracket-advance.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['user_id']) || !$_SESSION['is_organizer'] || $_SESSION['organizer_status'] !== 'approved') {
  header("Location: ../login.php");
  exit;
}

$tournament_id = (int)($_POST['tournament_id'] ?? 0);
if (!$tournament_id) {
  die("Invalid tournament");
}

/* Verify organizer owns the tournament */
$stmt = $conn->prepare("SELECT organizer_id FROM tournaments WHERE tournament_id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tRow = $stmt->get_result()->fetch_assoc();
if (!$tRow) {
  die("Tournament not found");
}
if ($tRow['organizer_id'] != $_SESSION['user_id']) {
  header("Location: ../login.php");
  exit;
}

$scores = $_POST['score'] ?? [];
if (!is_array($scores)) $scores = [];

require_once "update-standings.php";

$affected_group = false;

try {
  $conn->begin_transaction();

  // Prepare statements for performance
  $getMatchStmt = $conn->prepare("SELECT * FROM matches WHERE match_id = ? AND tournament_id = ?");
  $insertScoreStmt = $conn->prepare("
        INSERT INTO match_scores (match_id, team1_score, team2_score, set_number)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
          team1_score = VALUES(team1_score),
          team2_score = VALUES(team2_score),
          created_at = CURRENT_TIMESTAMP
    ");

  foreach ($scores as $match_id_str => $arr) {
    $match_id = (int)$match_id_str;
    $s1 = isset($arr['team1']) ? (int)$arr['team1'] : 0;
    $s2 = isset($arr['team2']) ? (int)$arr['team2'] : 0;

    // Validate match belongs to tournament
    $getMatchStmt->bind_param("ii", $match_id, $tournament_id);
    $getMatchStmt->execute();
    $match = $getMatchStmt->get_result()->fetch_assoc();
    if (!$match) {
      // skip invalid match id
      continue;
    }

    // Determine winner. For knockout rounds, ties are not advanced.
    $winner = null;
    if ($s1 > $s2) $winner = $match['team1_id'];
    elseif ($s2 > $s1) $winner = $match['team2_id'];
    else $winner = null; // tie

    $prev_winner = $match['winner_team_id'] ?? null;

    // Save score
    $insertScoreStmt->bind_param("iii", $match_id, $s1, $s2);
    $insertScoreStmt->execute();

    // Update match
    if ($match['round'] !== 'group') {
      if ($winner !== null && $winner !== '') {
        $updateMatchStmt = $conn->prepare("
                    UPDATE matches
                    SET winner_team_id = ?, status = 'completed', last_update = CURRENT_TIMESTAMP
                    WHERE match_id = ?
                ");
        $updateMatchStmt->bind_param("ii", $winner, $match_id);
      } else {
        $updateMatchStmt = $conn->prepare("
                    UPDATE matches
                    SET winner_team_id = NULL, status = 'pending', last_update = CURRENT_TIMESTAMP
                    WHERE match_id = ?
                ");
        $updateMatchStmt->bind_param("i", $match_id);
      }
      $updateMatchStmt->execute();
    } else {
      // group match - completed allowed even if tie
      if ($winner === null) {
        $updateMatchStmt = $conn->prepare("
                    UPDATE matches
                    SET winner_team_id = NULL, status = 'completed', last_update = CURRENT_TIMESTAMP
                    WHERE match_id = ?
                ");
        $updateMatchStmt->bind_param("i", $match_id);
      } else {
        $updateMatchStmt = $conn->prepare("
                    UPDATE matches
                    SET winner_team_id = ?, status = 'completed', last_update = CURRENT_TIMESTAMP
                    WHERE match_id = ?
                ");
        $updateMatchStmt->bind_param("ii", $winner, $match_id);
      }
      $updateMatchStmt->execute();
    }

    // Advance bracket if knockout and winner exists (or clear downstream if winner removed)
    if (in_array($match['round'], ['quarterfinal', 'semifinal'])) {
      advanceBracket($conn, $match, $winner);
    }

    if ($match['round'] === 'group') $affected_group = true;
  }

  // Recompute group standings if any group match updated
  if ($affected_group) {
    updateGroupStandings($conn, $tournament_id);
    // populate quarterfinals now that standings are updated
    populateQuarterFinals($conn, $tournament_id);
  }

  $conn->commit();
} catch (Exception $e) {
  $conn->rollback();
  error_log("Error saving results: " . $e->getMessage());
  die("An error occurred while saving scores.");
}

$redirect = $_SERVER['HTTP_REFERER'] ?? "resultManagement.php?tournament_id=" . $tournament_id;
header("Location: " . $redirect);
exit;
