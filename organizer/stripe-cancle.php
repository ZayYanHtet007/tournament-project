<?php
session_start();
require_once "../database/dbConfig.php";

/* ---------- AUTH ---------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$organizer_id = (int)$_SESSION['user_id'];
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

if (!$tournament_id) {
    die("Invalid tournament");
}

/* ---------- VERIFY PENDING PAYMENT ---------- */
$stmt = $conn->prepare("
    SELECT tp.payment_id
    FROM tournament_payments tp
    JOIN tournaments t ON t.tournament_id = tp.tournament_id
    WHERE tp.tournament_id = ?
      AND t.organizer_id = ?
      AND tp.status = 'pending'
    LIMIT 1
");
$stmt->bind_param("ii", $tournament_id, $organizer_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    header("Location: create-tournament.php?error=cancel_invalid");
    exit;
}

$stmt->close();

/* ---------- DELETE TOURNAMENT & PAYMENT ---------- */
$delPayment = $conn->prepare("
    DELETE FROM tournament_payments
    WHERE tournament_id = ? AND status = 'pending'
");
$delPayment->bind_param("i", $tournament_id);
$delPayment->execute();
$delPayment->close();

$delTournament = $conn->prepare("
    DELETE FROM tournaments
    WHERE tournament_id = ? AND organizer_id = ?
");
$delTournament->bind_param("ii", $tournament_id, $organizer_id);
$delTournament->execute();
$delTournament->close();

/* ---------- REDIRECT ---------- */
header("Location: create-tournament.php?cancelled=1");

echo "❌ Payment cancelled. Tournament was removed.";

exit;
