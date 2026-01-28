<?php

/**
 * Recompute and upsert group_standings for a tournament.
 * Scoring rule: Win = 3 points, Tie = 1 point each, Loss = 0.
 *
 * Improvements made:
 * - Initialize stats for all teams registered in tournament_teams
 * - Ensure group_name is captured when available (even if team hasn't played)
 * - Upsert every registered team (so teams with 0 played still appear)
 */

function updateGroupStandings($conn, $tournament_id)
{
  // 1) Initialize stats for all teams registered in tournament_teams
  $stats = [];

  $sqlTeams = "SELECT team_id FROM tournament_teams WHERE tournament_id = ?";
  $stmtTeams = $conn->prepare($sqlTeams);
  if (!$stmtTeams) return false;
  $stmtTeams->bind_param("i", $tournament_id);
  $stmtTeams->execute();
  $resTeams = $stmtTeams->get_result();
  while ($r = $resTeams->fetch_assoc()) {
    $teamId = (int)$r['team_id'];
    $stats[$teamId] = [
      'group' => '',
      'played' => 0,
      'wins' => 0,
      'losses' => 0,
      'points' => 0,
      'score_for' => 0,
      'score_against' => 0
    ];
  }
  $stmtTeams->close();

  // 2) Fetch all group-round matches with latest scores
  $sql = "
    SELECT m.match_id, m.group_name, m.team1_id, m.team2_id,
           ms.team1_score, ms.team2_score
    FROM matches m
    LEFT JOIN (
        SELECT ms1.match_id, ms1.team1_score, ms1.team2_score
        FROM match_scores ms1
        JOIN (
            SELECT match_id, MAX(set_number) AS max_set
            FROM match_scores
            GROUP BY match_id
        ) ms2 ON ms1.match_id = ms2.match_id AND ms1.set_number = ms2.max_set
    ) ms ON m.match_id = ms.match_id
    WHERE m.tournament_id = ? AND m.round = 'group'
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return false;
  $stmt->bind_param("i", $tournament_id);
  $stmt->execute();
  $res = $stmt->get_result();

  while ($row = $res->fetch_assoc()) {
    $t1 = (int)$row['team1_id'];
    $t2 = (int)$row['team2_id'];
    $g = $row['group_name'] ?? '';

    // Ensure both teams are in stats (in case tournament_teams wasn't populated for some reason)
    if ($t1 && !isset($stats[$t1])) {
      $stats[$t1] = ['group' => $g, 'played' => 0, 'wins' => 0, 'losses' => 0, 'points' => 0, 'score_for' => 0, 'score_against' => 0];
    }
    if ($t2 && !isset($stats[$t2])) {
      $stats[$t2] = ['group' => $g, 'played' => 0, 'wins' => 0, 'losses' => 0, 'points' => 0, 'score_for' => 0, 'score_against' => 0];
    }

    // Update group name if available and not set yet
    if ($g !== '') {
      if ($t1 && (empty($stats[$t1]['group']) || $stats[$t1]['group'] !== $g)) {
        $stats[$t1]['group'] = $g;
      }
      if ($t2 && (empty($stats[$t2]['group']) || $stats[$t2]['group'] !== $g)) {
        $stats[$t2]['group'] = $g;
      }
    }

    // Only count if scores exist (non-null)
    if ($row['team1_score'] === null || $row['team2_score'] === null) {
      continue;
    }

    $s1 = (int)$row['team1_score'];
    $s2 = (int)$row['team2_score'];

    // Guard: skip if team ids are invalid
    if (!$t1 || !$t2) continue;

    $stats[$t1]['played'] += 1;
    $stats[$t2]['played'] += 1;

    $stats[$t1]['score_for'] += $s1;
    $stats[$t1]['score_against'] += $s2;
    $stats[$t2]['score_for'] += $s2;
    $stats[$t2]['score_against'] += $s1;

    if ($s1 > $s2) {
      $stats[$t1]['wins'] += 1;
      $stats[$t1]['points'] += 3;
      $stats[$t2]['losses'] += 1;
    } elseif ($s2 > $s1) {
      $stats[$t2]['wins'] += 1;
      $stats[$t2]['points'] += 3;
      $stats[$t1]['losses'] += 1;
    } else {
      // tie
      $stats[$t1]['points'] += 1;
      $stats[$t2]['points'] += 1;
    }
  }
  $stmt->close();

  // 3) Upsert every registered team (so teams with 0 played are inserted/updated)
  $upsertSql = "
        INSERT INTO group_standings
          (tournament_id, team_id, group_name, played, wins, losses, points, score_for, score_against, last_update)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
          group_name = VALUES(group_name),
          played = VALUES(played),
          wins = VALUES(wins),
          losses = VALUES(losses),
          points = VALUES(points),
          score_for = VALUES(score_for),
          score_against = VALUES(score_against),
          last_update = CURRENT_TIMESTAMP
    ";
  $upsert = $conn->prepare($upsertSql);
  if (!$upsert) return false;

  foreach ($stats as $team_id => $s) {
    $group_name = $s['group'] ?? '';
    $played = (int)$s['played'];
    $wins = (int)$s['wins'];
    $losses = (int)$s['losses'];
    $points = (int)$s['points'];
    $score_for = (int)$s['score_for'];
    $score_against = (int)$s['score_against'];

    // types: tournament_id (i), team_id (i), group_name (s), played (i), wins (i), losses (i), points (i), score_for (i), score_against (i)
    $upsert->bind_param(
      "iisiiiiii",
      $tournament_id,
      $team_id,
      $group_name,
      $played,
      $wins,
      $losses,
      $points,
      $score_for,
      $score_against
    );
    $upsert->execute();
  }

  $upsert->close();
  return true;
}
