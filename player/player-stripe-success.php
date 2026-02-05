<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$session_id = $_GET['session_id'] ?? '';

try {
  // 1. Verify Payment with Stripe
  $session = \Stripe\Checkout\Session::retrieve([
    'id' => $session_id,
    'expand' => ['payment_intent']
  ]);

  if ($session->payment_status !== 'paid') {
    throw new Exception("Payment not confirmed.");
  }

  // 2. Extract Data
  $transaction_id = is_object($session->payment_intent) ? $session->payment_intent->id : ($session->payment_intent ?? $session_id);
  $amount = $session->amount_total / 100;

  // 3. Database Logic
  $stmt = $conn->prepare("SELECT team_id FROM teams WHERE leader_id = ? LIMIT 1");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $team = $stmt->get_result()->fetch_assoc();
  $team_id = $team['team_id'];

  $conn->begin_transaction();
  // Register Team
  $reg = $conn->prepare("INSERT INTO tournament_teams (tournament_id, team_id, registered_at) VALUES (?, ?, NOW())");
  $reg->bind_param("ii", $tournament_id, $team_id);
  $reg->execute();
  // B. Payment Record
  $status = 'completed'; // Define the missing 6th variable

  $pay = $conn->prepare("INSERT INTO registration_payments (tournament_id, team_id, user_id, amount, transaction_id, status) VALUES (?, ?, ?, ?, ?, ?)");

  // Now there are 6 types (iiidss) and 6 variables ($tournament_id, $team_id, $user_id, $amount, $transaction_id, $status)
  $pay->bind_param("iiidss", $tournament_id, $team_id, $_SESSION['user_id'], $amount, $transaction_id, $status);

  if (!$pay->execute()) {
    throw new Exception("Payment record failed: " . $conn->error);
  }
  $conn->commit();
  // If we reached here, everything is successful. Now show the UI.
} catch (Exception $e) {
  if (isset($conn)) $conn->rollback();
  die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Successful</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .checkmark {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: block;
      stroke-width: 2;
      stroke: #4bb543;
      stroke-miterlimit: 10;
      box-shadow: inset 0px 0px 0px #4bb543;
      animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
      margin: 0 auto;
    }

    .checkmark__circle {
      stroke-dasharray: 166;
      stroke-dashoffset: 166;
      stroke-width: 2;
      stroke-miterlimit: 10;
      stroke: #4bb543;
      fill: #fff;
      animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }

    .checkmark__check {
      transform-origin: 50% 50%;
      stroke-dasharray: 48;
      stroke-dashoffset: 48;
      animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }

    @keyframes stroke {
      100% {
        stroke-dashoffset: 0;
      }
    }

    @keyframes fill {
      100% {
        box-shadow: inset 0px 0px 0px 40px #4bb543;
      }
    }
  </style>
</head>

<body class="bg-gray-900 flex items-center justify-center h-screen">
  <div class="bg-white p-8 rounded-2xl shadow-2xl text-center max-w-sm w-full mx-4">
    <svg class="checkmark mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
      <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
    </svg>

    <h1 class="text-2xl font-bold text-gray-800 mb-2">Registration Confirmed!</h1>
    <p class="text-gray-600 mb-6">Your payment of <strong>$<?php echo number_format($amount, 2); ?></strong> was successful. Your team is now registered.</p>

    <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left text-sm text-gray-500">
      <p><strong>Transaction ID:</strong> <span class="font-mono text-xs"><?php echo $transaction_id; ?></span></p>
    </div>

    <p class="text-xs text-gray-400 mb-4">Redirecting you back in <span id="countdown">10</span> seconds...</p>

    <a href="../announceDetail.php?tournament_id=<?php echo $tournament_id; ?>"
      class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition duration-200">
      Go Back Now
    </a>
  </div>

  <script>
    let seconds = 10;
    const countdownEl = document.getElementById('countdown');
    const timer = setInterval(() => {
      seconds--;
      countdownEl.textContent = seconds;
      if (seconds <= 0) {
        clearInterval(timer);
        window.location.href = "../announceDetail.php?tournament_id=<?php echo $tournament_id; ?>";
      }
    }, 1000);
  </script>
</body>

</html>