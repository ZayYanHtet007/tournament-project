<?php
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$tournament_id = (int)$_GET['tournament_id'];
$session_id = $_GET['session_id'];

$session = \Stripe\Checkout\Session::retrieve($session_id);

if ($session->payment_status !== 'paid') {
  die("Payment not completed");
}

$amount = $session->amount_total / 100;
$transaction_id = $session->payment_intent;
$method_id = 1; // Stripe

mysqli_begin_transaction($conn);

try {

  mysqli_query($conn, "
        INSERT INTO tournament_payments
        (tournament_id, user_id, amount, method_id, status, transaction_id, payment_date, last_update)
        VALUES
        ($tournament_id, {$_SESSION['user_id']}, $amount, $method_id,
         'completed', '$transaction_id', NOW(), NOW())
    ");

  mysqli_query($conn, "
        UPDATE tournaments
        SET status = 'upcoming'
        WHERE tournament_id = $tournament_id
    ");

  mysqli_commit($conn);

  echo "✅ Payment successful. Tournament is now live.";
} catch (Exception $e) {
  mysqli_rollback($conn);
  echo "❌ Payment completed but database failed.";
}
