<?php
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_GET['tournament_id'])) {
  die("Invalid request");
}

$tournament_id = (int)$_GET['tournament_id'];
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id === 0) {
  die("Please login first");
}

// Check tournament ownership & status
$stmt = $conn->prepare("
    SELECT tournament_id 
    FROM tournaments 
    WHERE tournament_id = ? AND organizer_id = ? AND status = 'upcoming'
");
$stmt->bind_param("ii", $tournament_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Unauthorized access");
}

// Tournament creation fee
$CREATION_FEE = 100; // USD

// Create Stripe checkout session
$checkout = \Stripe\Checkout\Session::create([
  'mode' => 'payment',
  'payment_method_types' => ['card'],
  'line_items' => [[
    'price_data' => [
      'currency' => 'usd',
      'product_data' => ['name' => 'Tournament Creation Fee'],
      'unit_amount' => $CREATION_FEE * 100,
    ],
    'quantity' => 1
  ]],
  'success_url' => "http://localhost/tournament-project/organizer/stripe-success.php?tournament_id=$tournament_id&session_id={CHECKOUT_SESSION_ID}",
  'cancel_url'  => "http://localhost/tournament-project/organizer/stripe-cancel.php?tournament_id=$tournament_id"
]);

header("Location: " . $checkout->url);
exit;
