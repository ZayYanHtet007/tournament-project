<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_SESSION['user_id'])) {
  die("Login required");
}

$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$session_id = $_GET['session_id'] ?? '';

if (!$tournament_id || !$session_id) {
  die("Invalid request");
}

try {
  $session = \Stripe\Checkout\Session::retrieve($session_id);
} catch (\Exception $e) {
  die("Stripe session not found");
}

if ($session->payment_status !== 'paid') {
  die("Payment not completed");
}

$amount = $session->amount_total / 100;
$transaction_id = $session->payment_intent;
$method_id = 1; // Stripe

// Check for duplicate payment
$check = $conn->prepare("SELECT payment_id FROM tournament_payments WHERE transaction_id = ?");
$check->bind_param("s", $transaction_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
  die("Payment already recorded");
}

mysqli_begin_transaction($conn);

try {
  // 1️⃣ Record payment
  $pay = $conn->prepare("
        INSERT INTO tournament_payments
        (tournament_id, user_id, amount, method_id, status, transaction_id, payment_date, last_update)
        VALUES (?, ?, ?, ?, 'completed', ?, NOW(), NOW())
    ");
  $pay->bind_param("iidis", $tournament_id, $_SESSION['user_id'], $amount, $method_id, $transaction_id);
  $pay->execute();

  // 2️⃣ Create notifications for all admins
  $title = "Tournament Payment Successful";
  $message = "Payment completed for tournament ID #{$tournament_id}.";

  $stmt = $conn->prepare("
        INSERT INTO admin_notifications (admin_id, title, message, type, created_at)
        SELECT admin_id, ?, ?, 'payment_success', NOW() FROM admins
    ");
  $stmt->bind_param("ss", $title, $message);
  $stmt->execute();

  // Fetch all newly inserted notifications for WebSocket
  $res = $conn->query("SELECT notification_id, created_at FROM admin_notifications ORDER BY notification_id DESC LIMIT " . $conn->affected_rows);
  $notifications = $res->fetch_all(MYSQLI_ASSOC);

  // 3️⃣ Push each notification to WebSocket server
  foreach ($notifications as $n) {
    $payload = json_encode([
      "id" => $n['notification_id'],
      "title" => $title,
      "message" => $message,
      "type" => "payment_success",
      "created_at" => $n['created_at']
    ]);

    $ch = curl_init("http://localhost:5000/notify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
      error_log("WebSocket notify failed: " . curl_error($ch));
    }
    curl_close($ch);
  }

  mysqli_commit($conn);

  // Redirect to success page
  header("Location: payment-success.php?tournament_id=$tournament_id");
  exit;
} catch (Exception $e) {
  mysqli_rollback($conn);
  error_log("Payment DB error: " . $e->getMessage());
  echo "❌ Payment succeeded but database failed.";
}
