<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
session_start();

require_once "../stripe-php/init.php";
require_once "../loadenv.php";
require_once "../database/dbConfig.php";

function calculateStatus($reg_start, $start)
{
  $today = date('Y-m-d');
  if ($today < $reg_start) return 'upcoming';
  if ($today >= $reg_start && $today <= $start) return 'ongoing';
  return 'completed';
}

// Default Functions
function generateDefaultSystem($genre, $maxTeams)
{
  if ($genre === 'BATTLE_ROYALE') {
    return "
•Points Based League
•All Teams Will Play 3 Matches

Rank Points:
1 → 20
2 → 16
3 → 14
4 → 12
5 → 10
6 → 8
7 → 6
8 → 4
9+ → 1

•Each Kill = 1 Point
•Highest Total Points Wins
";
  }

  if ($maxTeams == 12) {
    return "Group Stage (3 teams per group)\nBO3 Matches\nTop 8 → Quarter Final\nTop 4 → Semi Final";
  }

  return "Single Elimination Format";
}

function generateDefaultRules($genre, $description)
{
  $base = $description . "\n• Fair play is mandatory\n• Any cheating leads to disqualification\n";
  switch ($genre) {
    case 'MOBA':
      return $base . "• All matches are BO3 (Grand Final BO5)\n• MOBA Mobile can't play with emulator\n• Teams must be ready 15 minutes before match\n• Disconnects under 5 minutes → rematch\n• Organizer decision is final";
    case 'BATTLE_ROYALE':
      return $base . "• No teaming\n• Exploits and glitches are forbidden\n• Custom room only\n• Organizer decision is final";
    default:
      return $base . "• Organizer decision is final";
  }
}

loadEnv("../.env");
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isset($_SESSION['user_id'])) {
  die("Login required");
}

try {
  if (!isset($_GET['session_id'])) {
    die("Session ID missing.");
  }
  $session_id = $_GET['session_id'];
  $session = \Stripe\Checkout\Session::retrieve($session_id);
  if ($session->payment_status !== 'paid') die("Payment not verified.");

  $transaction_id = $session->payment_intent;
  $data = $_SESSION['pending_tournament'];
  $organizer_id = (int)$_SESSION['user_id'];

  mysqli_begin_transaction($conn);

  // 1. Insert Tournament
  $status = calculateStatus($data['registration_start_date'], $data['start_date']);
  $stmt = $conn->prepare("INSERT INTO tournaments (organizer_id, game_id, title, description, max_participants, team_size, fee, registration_start_date, registration_deadline, start_date, status, admin_status, prize_pool) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
  $stmt->bind_param("iissiidssssd", $organizer_id, $data['game_id'], $data['title'], $data['description'], $data['max_participants'], $data['team_size'], $data['fee'], $data['registration_start_date'], $data['registration_deadline'], $data['start_date'], $status, $data['prize_pool']);
  $stmt->execute();
  $tournament_id = $stmt->insert_id;

  // 2. Insert Default Rules/System
  $gStmt = $conn->prepare("SELECT genre FROM games WHERE game_id = ?");
  $gStmt->bind_param("i", $data['game_id']);
  $gStmt->execute();
  $genre = $gStmt->get_result()->fetch_assoc()['genre'] ?? 'Default';

  $rules = generateDefaultRules($genre, $data['description']);
  $system = generateDefaultSystem($genre, $data['max_participants']);

  $stmtAnn = $conn->prepare("INSERT INTO tournament_announcements (tournament_id, title, rules, system_info, created_at) VALUES (?, ?, ?, ?, NOW())");
  $stmtAnn->bind_param("isss", $tournament_id, $data['title'], $rules, $system);
  $stmtAnn->execute();

  // 3. Record Payment
  $amount = $session->amount_total / 100;
  $pay = $conn->prepare("INSERT INTO tournament_payments (tournament_id, user_id, amount, method_id, status, transaction_id, payment_date) VALUES (?, ?, ?, 1, 'completed', ?, NOW())");
  $pay->bind_param("iids", $tournament_id, $organizer_id, $amount, $transaction_id);
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
  unset($_SESSION['pending_tournament']); // Clear data

  header("Location: payment-success.php?tournament_id=" . $tournament_id);
  exit;
} catch (\Exception $e) {
  mysqli_rollback($conn);
  die("Critical Error: " . $e->getMessage());
}



