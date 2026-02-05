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

if (!isset($_GET['tournament_id'])) {
  die("Invalid request");
}

$tournament_id = (int)$_GET['tournament_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT tournament_id
    FROM tournaments
    WHERE tournament_id = ?
      AND organizer_id = ?
      AND admin_status = 'pending'
");
$stmt->bind_param("ii", $tournament_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
  die("Unauthorized access");
}

$stmt = $conn->prepare("
    SELECT tournament_create_value 
    FROM creation_fee 
    WHERE tournament_create_fee_id = 'tournament_creation_fee'
");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$CREATION_FEE = (float)$result['tournament_create_value'];
if ($CREATION_FEE <= 0) {
  die("No payment required");
} 

// $CREATION_FEE = 100; // USD

$session = \Stripe\Checkout\Session::create([
  'mode' => 'payment',
  'payment_method_types' => ['card'],
  'line_items' => [[
    'price_data' => [
      'currency' => 'usd',
      'product_data' => [
        'name' => 'Tournament Creation Fee'
      ],
      'unit_amount' => $CREATION_FEE * 100
    ],
    'quantity' => 1
  ]],
  'success_url' => "http://localhost/tournament-project/organizer/stripe-success.php?tournament_id=$tournament_id&session_id={CHECKOUT_SESSION_ID}",
  'cancel_url'  => "http://localhost/tournament-project/organizer/stripe-cancel.php?tournament_id=$tournament_id"
]);

header("Location: " . $session->url);
exit;
