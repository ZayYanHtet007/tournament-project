<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if (!$team_id) {
    die("Invalid Team ID.");
}

// 1. Verify membership and role
$stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND user_id = ?");
$stmt->bind_param("ii", $team_id, $user_id);
$stmt->execute();
$member_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member_data) {
    die("You are not a member of this team.");
}

$conn->begin_transaction();

try {
    // 2. If the user leaving is the leader, transfer leadership to the next oldest member
    if ($member_data['role'] === 'leader') {
        
        // Find the second member to join (oldest created_at excluding current leader)
        $next = $conn->prepare("
            SELECT user_id FROM team_members 
            WHERE team_id = ? AND user_id != ? 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $next->bind_param("ii", $team_id, $user_id);
        $next->execute();
        $new_leader = $next->get_result()->fetch_assoc();
        $next->close();

        if ($new_leader) {
            $new_leader_id = $new_leader['user_id'];

            // Update team_members role to 'leader'
            $updMem = $conn->prepare("UPDATE team_members SET role = 'leader' WHERE team_id = ? AND user_id = ?");
            $updMem->bind_param("ii", $team_id, $new_leader_id);
            $updMem->execute();
            $updMem->close();

            // Sync the teams table leader_id
            $updTeam = $conn->prepare("UPDATE teams SET leader_id = ? WHERE team_id = ?");
            $updTeam->bind_param("ii", $new_leader_id, $team_id);
            $updTeam->execute();
            $updTeam->close();
        }
    }

    // 3. Delete the leaving user from team_members
    $del = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
    $del->bind_param("ii", $team_id, $user_id);
    $del->execute();
    $del->close();

    $conn->commit();
    header("Location: ../index.php?msg=left_team");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Error leaving team: " . $e->getMessage());
}