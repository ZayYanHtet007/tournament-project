<?php
require __DIR__ . '/stripe-php/init.php';



\Stripe\Stripe::setApiKey('sk_test_51SjY15B56m619Hect7rrzJdnX8UE1ZmbOrvkyIxyw1bO94peRlxQSJEcgiGdMTlI6Ur13iH6PCBHjS1EFwXX5YLh008SZlFLoH'); // Replace with your secret key

header('Content-Type: application/json');

try {
  $intent = \Stripe\PaymentIntent::create([
    'amount' => 1000, // $10 in cents
    'currency' => 'usd',
    'payment_method_types' => ['card'],
  ]);

  echo json_encode(['clientSecret' => $intent->client_secret]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Stripe Payment</title>
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    #card-element {
      border: 1px solid #ccc;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 10px;
      max-width: 400px;
    }

    button {
      padding: 8px 16px;
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
  </style>
</head>

<body>

  <h1>Stripe Payment</h1>

  <form id="payment-form">
    <div id="card-element"></div>
    <div id="card-errors" style="color:red;"></div>
    <button type="submit">Pay</button>
  </form>

  <script>
    const stripe = Stripe('pk_test_51SjY15B56m619Hec0bOD4PF3oCMOr54bg9TtRZmCCk4HmmbkgBklKtwJuddlr3Gvwa5lF3bpIszzFuH4drKXMGaw00Xt9J1YwC'); // ← Replace with your real publishable key
    const elements = stripe.elements();
    const card = elements.create('card', {
      style: {
        base: {
          fontSize: '16px',
          color: '#32325d',
        },
      },
    });
    card.mount('#card-element');

    const form = document.getElementById('payment-form');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // 1. Get clientSecret from server
      const res = await fetch('payment.php');
      const data = await res.json();

      if (data.error) {
        document.getElementById('card-errors').textContent = data.error;
        return;
      }

      // 2. Confirm card payment
      const result = await stripe.confirmCardPayment(data.clientSecret, {
        payment_method: {
          card: card,
          billing_details: {
            name: 'Test User'
          }
        }
      });

      if (result.error) {
        document.getElementById('card-errors').textContent = result.error.message;
      } else if (result.paymentIntent.status === 'succeeded') {
        alert('Payment successful!');
      }
    });
  </script>

</body>

</html>