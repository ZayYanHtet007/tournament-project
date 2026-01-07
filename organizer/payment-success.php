<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

try {
  require_once "../loadEnv.php";
  require_once "../database/dbConfig.php";
  require_once "../stripe-php/init.php"; // ✅ REQUIRED

  \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

  $tournament_id = (int)($_GET['tournament_id'] ?? 0);
  $user_id = (int)($_SESSION['user_id'] ?? 0);

  if (!$tournament_id || !$user_id) {
    throw new Exception("Invalid request");
  }

  $amount = 5.00; // USD

  $intent = \Stripe\PaymentIntent::create([
    'amount' => (int) round($amount * 100),
    'currency' => 'usd',
    'payment_method_types' => ['card'],
    'metadata' => [
      'tournament_id' => $tournament_id,
      'user_id' => $user_id
    ]
  ]);

  $stmt = $conn->prepare("
        INSERT INTO tournament_payments
        (tournament_id, user_id, amount, method_id, status, transaction_id, payment_date, last_update)
        VALUES (?, ?, ?, 1, 'pending', ?, NOW(), NOW())
    ");

  if (!$stmt) {
    throw new Exception($conn->error);
  }

  $stmt->bind_param(
    "iids",
    $tournament_id,
    $user_id,
    $amount,
    $intent->id
  );

  $stmt->execute();

  echo json_encode([
    'clientSecret' => $intent->client_secret
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error' => $e->getMessage()
  ]);
}

exit;
