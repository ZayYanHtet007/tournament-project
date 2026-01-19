<?php

/**
 * Bracket helpers:
 * - advanceBracket($conn, $match, $winner)  -> propagate winner from quarterfinal->semifinal and semifinal->final, and place semifinal losers into third_place.
 * - populateQuarterFinals($conn, $tournament_id) -> fill quarterfinal team slots based on group_standings (top 2 per group).
 *
 * This implementation uses the ordering (match_order) of placeholder matches per round,
 * so it does not rely on hard-coded numeric offsets.
 */

/* Safe setter/getter for team slots by match_order and round */
function setMatchSlot($conn, $tournament_id, $match_order, $round, $slot, $team_id)
{
  if (!in_array($slot, ['team1_id', 'team2_id'])) return false;

  if ($team_id === null) {
    $sql = "UPDATE matches SET {$slot} = NULL WHERE tournament_id = ? AND match_order = ? AND round = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("iis", $tournament_id, $match_order, $round);
  } else {
    $sql = "UPDATE matches SET {$slot} = ? WHERE tournament_id = ? AND match_order = ? AND round = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("iiis", $team_id, $tournament_id, $match_order, $round);
  }
  $res = $stmt->execute();
  $stmt->close();
  return $res;
}

function fetchTargetSlotValue($conn, $tournament_id, $match_order, $round, $slot)
{
  if (!in_array($slot, ['team1_id', 'team2_id'])) return null;
  $sql = "SELECT {$slot} FROM matches WHERE tournament_id = ? AND match_order = ? AND round = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return null;
  $stmt->bind_param("iis", $tournament_id, $match_order, $round);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $r[$slot] ?? null;
}

/* Return ordered array of match_order values for a given round (ascending) */
function getOrderedMatchOrders($conn, $tournament_id, $round)
{
  $sql = "SELECT match_order FROM matches WHERE tournament_id = ? AND round = ? ORDER BY match_order ASC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param("is", $tournament_id, $round);
  $stmt->execute();
  $res = $stmt->get_result();
  $orders = [];
  while ($r = $res->fetch_assoc()) $orders[] = (int)$r['match_order'];
  $stmt->close();
  return $orders;
}

/* Advance winner from quarterfinal -> semifinal and semifinal -> final.
   Also place semifinal losers into third_place slots.
   Uses positional mapping (indexes) rather than hard-coded numbers.
*/
function advanceBracket($conn, $match, $winner)
{
  if (!$match || empty($match['round'])) return;

  $round = $match['round'];
  $matchOrder = (int)$match['match_order'];
  $tournament = (int)$match['tournament_id'];
  $prev_winner = $match['winner_team_id'] ?? null;

  if ($round === 'quarterfinal') {
    $srcOrders = getOrderedMatchOrders($conn, $tournament, 'quarterfinal');
    $dstOrders = getOrderedMatchOrders($conn, $tournament, 'semifinal');
    if (empty($srcOrders) || empty($dstOrders)) return;

    $idx = array_search($matchOrder, $srcOrders, true);
    if ($idx === false) return;
    $targetIdx = intdiv($idx, 2);
    if (!isset($dstOrders[$targetIdx])) return;
    $targetOrder = $dstOrders[$targetIdx];
    $slot = ($idx % 2 === 0) ? 'team1_id' : 'team2_id';
    $next_round = 'semifinal';
  } elseif ($round === 'semifinal') {
    $srcOrders = getOrderedMatchOrders($conn, $tournament, 'semifinal');
    $dstOrders = getOrderedMatchOrders($conn, $tournament, 'final');
    $thirdOrders = getOrderedMatchOrders($conn, $tournament, 'third_place');
    if (empty($srcOrders) || empty($dstOrders) || empty($thirdOrders)) return;

    $idx = array_search($matchOrder, $srcOrders, true);
    if ($idx === false) return;
    $targetIdx = 0; // finals typically single match; we place winners according to semifinal index
    $targetOrder = $dstOrders[$targetIdx];
    $slot = ($idx === 0) ? 'team1_id' : 'team2_id';
    $next_round = 'final';
    $third_order = $thirdOrders[0];
    $third_slot = ($idx === 0) ? 'team1_id' : 'team2_id';
  } else {
    return;
  }

  // Clear previously propagated assignment if prev_winner changed
  if ($prev_winner && $prev_winner != $winner) {
    $existing = fetchTargetSlotValue($conn, $tournament, $targetOrder, $next_round, $slot);
    if ($existing !== null && (int)$existing === (int)$prev_winner) {
      setMatchSlot($conn, $tournament, $targetOrder, $next_round, $slot, null);
    }

    if ($round === 'semifinal') {
      // clear previous loser from third place if it was set from previous result
      if (!empty($match['team1_id']) && !empty($match['team2_id'])) {
        $prev_loser = ((int)$prev_winner === (int)$match['team1_id']) ? (int)$match['team2_id'] : (int)$match['team1_id'];
        $existing3 = fetchTargetSlotValue($conn, $tournament, $third_order, 'third_place', $third_slot);
        if ($existing3 !== null && (int)$existing3 === (int)$prev_loser) {
          setMatchSlot($conn, $tournament, $third_order, 'third_place', $third_slot, null);
        }
      }
    }
  }

  // If no winner (tie/cleared), do not advance
  if (!$winner) return;

  // Set into next round
  setMatchSlot($conn, $tournament, $targetOrder, $next_round, $slot, $winner);

  // For semifinals, set loser to third_place
  if ($round === 'semifinal') {
    if (!empty($match['team1_id']) && !empty($match['team2_id'])) {
      $loser = ((int)$winner === (int)$match['team1_id']) ? (int)$match['team2_id'] : (int)$match['team1_id'];
      setMatchSlot($conn, $tournament, $third_order, 'third_place', $third_slot, $loser);
    }
  }
}

/**
 * Populate quarterfinal slots based on group_standings.
 * Rules:
 *  - For each group (ordered by group_name asc), pick top 2 teams using ORDER:
 *      points DESC, (score_for - score_against) DESC, score_for DESC, wins DESC
 *  - Map top teams into quarterfinal placeholders ordered by match_order:
 *      qf[0] = A1 vs B2
 *      qf[1] = C1 vs D2
 *      qf[2] = B1 vs A2
 *      qf[3] = D1 vs C2
 *
 *  If a group or slot is missing, the slot will be set to NULL.
 */
function populateQuarterFinals($conn, $tournament_id)
{
  // Get distinct groups for this tournament (from group_standings)
  $gstmt = $conn->prepare("SELECT DISTINCT group_name FROM group_standings WHERE tournament_id = ? ORDER BY group_name ASC");
  if (!$gstmt) return false;
  $gstmt->bind_param("i", $tournament_id);
  $gstmt->execute();
  $gres = $gstmt->get_result();
  $groups = [];
  while ($g = $gres->fetch_assoc()) {
    if ($g['group_name'] !== null && $g['group_name'] !== '') $groups[] = $g['group_name'];
  }
  $gstmt->close();

  // We expect at least 4 groups (A,B,C,D). If less, still proceed with whatever exists.
  // Build top2 map: group => [1=>team_id, 2=>team_id]
  $top2 = [];
  $stmtTop = $conn->prepare("
        SELECT team_id FROM group_standings
        WHERE tournament_id = ? AND group_name = ?
        ORDER BY points DESC, (score_for - score_against) DESC, score_for DESC, wins DESC
        LIMIT 2
    ");
  if (!$stmtTop) return false;
  foreach ($groups as $grp) {
    $stmtTop->bind_param("is", $tournament_id, $grp);
    $stmtTop->execute();
    $r = $stmtTop->get_result();
    $arr = [];
    while ($row = $r->fetch_assoc()) $arr[] = (int)$row['team_id'];
    $top2[$grp] = $arr; // may contain 0,1 or 2 elements
  }
  $stmtTop->close();

  // Collect groups in order A,B,C,D if present, else use discovered order
  // For deterministic mapping we want index positions for groups:
  $ordered = $groups;
  // Fill array A-D if they exist
  $want = ['A', 'B', 'C', 'D'];
  $ordered = array_values(array_filter($want, function ($x) use ($groups) {
    return in_array($x, $groups);
  }));
  // If not 4 groups found, append remaining discovered groups
  foreach ($groups as $g) if (!in_array($g, $ordered)) $ordered[] = $g;

  // Get quarterfinal match_orders (ordered)
  $qfOrders = getOrderedMatchOrders($conn, $tournament_id, 'quarterfinal');
  // If no quarterfinal placeholders, nothing to do
  if (count($qfOrders) === 0) return false;

  // Build seed variables (use null if missing)
  $A1 = $top2['A'][0] ?? null;
  $A2 = $top2['A'][1] ?? null;
  $B1 = $top2['B'][0] ?? null;
  $B2 = $top2['B'][1] ?? null;
  $C1 = $top2['C'][0] ?? null;
  $C2 = $top2['C'][1] ?? null;
  $D1 = $top2['D'][0] ?? null;
  $D2 = $top2['D'][1] ?? null;

  // Determine mapping based on number of qf slots available.
  // We'll map first four qf slots to standard pattern if available.
  $mapping = [];
  if (isset($qfOrders[0])) $mapping[$qfOrders[0]] = ['team1' => $A1, 'team2' => $B2];
  if (isset($qfOrders[1])) $mapping[$qfOrders[1]] = ['team1' => $C1, 'team2' => $D2];
  if (isset($qfOrders[2])) $mapping[$qfOrders[2]] = ['team1' => $B1, 'team2' => $A2];
  if (isset($qfOrders[3])) $mapping[$qfOrders[3]] = ['team1' => $D1, 'team2' => $C2];

  // For extra safety, if groups differ from A-D we attempt a fallback mapping using discovered ordered groups:
  if (count($qfOrders) >= 4 && empty($A1) && !empty($ordered)) {
    // fallback: use group positions in $ordered as G0..G3
    $G1 = $top2[$ordered[0]][0] ?? null;
    $G1_2 = $top2[$ordered[0]][1] ?? null;
    $G2 = $top2[$ordered[1]][0] ?? null;
    $G2_2 = $top2[$ordered[1]][1] ?? null;
    $G3 = $top2[$ordered[2]][0] ?? null;
    $G3_2 = $top2[$ordered[2]][1] ?? null;
    $G4 = $top2[$ordered[3]][0] ?? null;
    $G4_2 = $top2[$ordered[3]][1] ?? null;
    $mapping[$qfOrders[0]] = ['team1' => $G1,   'team2' => $G2_2];
    $mapping[$qfOrders[1]] = ['team1' => $G3,   'team2' => $G4_2];
    $mapping[$qfOrders[2]] = ['team1' => $G2,   'team2' => $G1_2];
    $mapping[$qfOrders[3]] = ['team1' => $G4,   'team2' => $G3_2];
  }

  // Apply mapping
  foreach ($mapping as $mOrder => $teams) {
    // team1
    setMatchSlot($conn, $tournament_id, $mOrder, 'quarterfinal', 'team1_id', $teams['team1'] ?? null);
    // team2
    setMatchSlot($conn, $tournament_id, $mOrder, 'quarterfinal', 'team2_id', $teams['team2'] ?? null);
  }

  return true;
}
