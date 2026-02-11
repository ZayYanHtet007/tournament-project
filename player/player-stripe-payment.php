<?php
session_start();
require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_SESSION['user_id'])) {
  die("Access Denied");
}

$tournament_id = (int)$_GET['tournament_id'];

$stmt = $conn->prepare("SELECT title, fee FROM tournaments WHERE tournament_id = ?");
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
  die("Tournament not found");
}

$amount = (float)$res['fee'];
$title = $res['title'];

$session = \Stripe\Checkout\Session::create([
  'payment_method_types' => ['card'],
  'line_items' => [[
    'price_data' => [
      'currency' => 'usd',
      'product_data' => [
        'name' => "Registration: $title",
        'description' => "Tournament Entry Fee (ID: #$tournament_id)"
      ],
      'unit_amount' => $amount * 100,
    ],
    'quantity' => 1,
  ]],
  'mode' => 'payment',
  // ADD THIS: It helps track the payment back to the user if the session ID fails
  'payment_intent_data' => [
    'metadata' => [
      'tournament_id' => $tournament_id,
      'user_id' => $_SESSION['user_id']
    ]
  ],
  'success_url' => "http://localhost/tournament-project/player/player-stripe-success.php?tournament_id=$tournament_id&session_id={CHECKOUT_SESSION_ID}",
  'cancel_url'  => "http://localhost/tournament-project/announceDetail.php?tournament_id=$tournament_id",
]);

header("Location: " . $session->url);
exit;
