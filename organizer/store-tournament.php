<?php
session_start();
require_once "../database/dbConfig.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$organizer_id = $_SESSION['user_id'];
$payment_intent_id = $_POST['payment_intent_id'];

$title = $_POST['title'];
$description = $_POST['description'];
$game_name = $_POST['game_name'];
$max_participants = (int)$_POST['max_participants'];
$fee = (float)$_POST['fee'];
$registration_deadline = $_POST['registration_deadline'];
$start_date = $_POST['start_date'];

$CREATION_FEE = 10;
$STRIPE_METHOD_ID = 1; // payment_methods.id for Stripe

mysqli_begin_transaction($conn);

try {

  $sql = "
        INSERT INTO tournaments
        (organizer_id, title, description, game_name, max_participants, fee, registration_deadline, start_date, status, created_at, last_update)
        VALUES
        ($organizer_id, '$title', '$description', '$game_name', $max_participants, $fee, '$registration_deadline', '$start_date', 'upcoming', NOW(), NOW())
    ";

  mysqli_query($conn, $sql);
  $tournament_id = mysqli_insert_id($conn);

  $paySql = "
        INSERT INTO tournament_payments
        (tournament_id, user_id, amount, method_id, status, transaction_id, payment_date, last_update)
        VALUES
        ($tournament_id, $organizer_id, $CREATION_FEE, $STRIPE_METHOD_ID, 'completed', '$payment_intent_id', NOW(), NOW())
    ";

  mysqli_query($conn, $paySql);

  mysqli_commit($conn);

  header("Location: success.php");
  exit;
} catch (Exception $e) {
  mysqli_rollback($conn);
  echo "Error creating tournament.";
}
