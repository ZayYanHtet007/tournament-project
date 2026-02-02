<?php
session_start();
require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$tournament_id = (int)$_GET['tournament_id'];

// Get the fee from your creation_fee table
$stmt = $conn->prepare("SELECT tournament_create_value FROM creation_fee WHERE tournament_create_fee_id = 'tournament_creation_fee'");
$stmt->execute();
$fee_row = $stmt->get_result()->fetch_assoc();
$amount = (float)$fee_row['tournament_create_value'];

$session = \Stripe\Checkout\Session::create([
  'payment_method_types' => ['card'],
  'line_items' => [[
    'price_data' => [
      'currency' => 'usd',
      'product_data' => ['name' => 'Tournament Creation Fee', 'description' => "ID: #$tournament_id"],
      'unit_amount' => $amount * 100,
    ],
    'quantity' => 1,
  ]],
  'mode' => 'payment',
  'success_url' => "http://localhost/tournament-project/organizer/stripe-success.php?tournament_id=$tournament_id&session_id={CHECKOUT_SESSION_ID}",
  'cancel_url'  => "http://localhost/tournament-project/organizer/stripe-cancel.php?tournament_id=$tournament_id",
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      background: #f9fafb;
    }

    .card {
      background: white;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      text-align: center;
    }

    .spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #6366f1;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="spinner"></div>
    <h2>Secure Checkout</h2>
    <p>Redirecting to Stripe to complete your tournament creation fee...</p>
  </div>
  <script>
    window.location.href = "<?php echo $session->url; ?>";
  </script>
</body>

</html>