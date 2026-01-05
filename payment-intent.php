<?php
error_reporting(0);
ini_set('display_errors', 0);

require __DIR__ . '/stripe-php/init.php';

\Stripe\Stripe::setApiKey('sk_test_51SjY15B56m619Hect7rrzJdnX8UE1ZmbOrvkyIxyw1bO94peRlxQSJEcgiGdMTlI6Ur13iH6PCBHjS1EFwXX5YLh008SZlFLoH');

header('Content-Type: application/json');

$totalAmount = 120000; // MMK
$exchangeRate = 3500;
$amountUsd = $totalAmount / $exchangeRate;
$amountToCharge = (int) round($amountUsd * 100);

try {
  $intent = \Stripe\PaymentIntent::create([
    'amount' => $amountToCharge,
    'currency' => 'usd',
    'payment_method_types' => ['card'],
    'description' => 'Tournament payment'
  ]);

  echo json_encode([
    'clientSecret' => $intent->client_secret
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'error' => $e->getMessage()
  ]);
}
