<?php
session_start();
require_once "../stripe-php/init.php";
require_once "../loadenv.php";

loadEnv("../.env");

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  exit;
}

$CREATION_FEE = 100; // USD

$intent = \Stripe\PaymentIntent::create([
  'amount' => $CREATION_FEE * 100,
  'currency' => 'usd',
  'metadata' => [
    'type' => 'tournament_creation',
    'user_id' => $_SESSION['user_id']
  ]
]);

echo json_encode([
  'clientSecret' => $intent->client_secret
]);
