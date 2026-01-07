<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header("Content-Type: application/json");

require_once "../loadEnv.php";
require_once "../database/dbConfig.php";
require_once "../stripe-php/init.php";  // Make sure this path is correct

loadEnv("../.env");

try {
  // Check if user is logged in
  if (!isset($_SESSION['user_id'])) {
    throw new Exception("User not logged in");
  }

  $user_id = (int)$_SESSION['user_id'];

  // Get tournament ID from URL
  $tournament_id = (int)($_GET['tournament_id'] ?? 0);
  if (!$tournament_id) {
    throw new Exception("Tournament ID missing");
  }

  // Ensure Stripe secret key is loaded
  if (empty($_ENV['STRIPE_SECRET_KEY'])) {
    throw new Exception("Stripe secret key missing");
  }

  // Initialize Stripe
  \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

  // Fixed creation fee
  $amount = 5.00; // USD
  $amount_cents = (int)round($amount * 100);

  // Create PaymentIntent
  $intent = \Stripe\PaymentIntent::create([
    'amount' => $amount_cents,
    'currency' => 'usd',
    'payment_method_types' => ['card'],
    'metadata' => [
      'tournament_id' => $tournament_id,
      'user_id' => $user_id
    ]
  ]);

  // Insert pending payment into database
  $stmt = $conn->prepare("
       update tournament_payments set status='completed' where tournament_id=?   and status='pending'");

  if (!$stmt) {
    throw new Exception("Database prepare failed: " . $conn->error);
  }

  $stmt->bind_param("i", $tournament_id);
  $stmt->execute();

  // Return client secret to frontend
  ob_clean();
  echo json_encode([
    'clientSecret' => $intent->client_secret
  ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  ob_clean();
  echo json_encode([
    'error' => $e->getMessage()
  ]);
  exit;
}
