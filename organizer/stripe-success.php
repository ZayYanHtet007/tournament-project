<?php
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_SESSION['user_id'])) {
  die("Login required");
}

$tournament_id = (int)$_GET['tournament_id'];
$session_id = $_GET['session_id'];

$session = \Stripe\Checkout\Session::retrieve($session_id);

if ($session->payment_status !== 'paid') {
  die("Payment not completed");
}

$amount = $session->amount_total / 100;
$transaction_id = $session->payment_intent;
$method_id = 1; // Stripe

$check = $conn->prepare("
    SELECT payment_id
    FROM tournament_payments
    WHERE transaction_id = ?
");
$check->bind_param("s", $transaction_id);
$check->execute();

if ($check->get_result()->num_rows > 0) {
  die("Payment already recorded");
}

mysqli_begin_transaction($conn);

try {

  $pay = $conn->prepare("
        INSERT INTO tournament_payments
        (tournament_id, user_id, amount, method_id, status,
         transaction_id, payment_date, last_update)
        VALUES (?, ?, ?, ?, 'completed', ?, NOW(), NOW())
    ");
  $pay->bind_param(
    "iidis",
    $tournament_id,
    $_SESSION['user_id'],
    $amount,
    $method_id,
    $transaction_id
  );
  $pay->execute();

  mysqli_commit($conn);

  echo "✅ Payment successful. Waiting for admin approval.";
} catch (Exception $e) {
  mysqli_rollback($conn);
  echo "❌ Payment succeeded but database failed.";
}
