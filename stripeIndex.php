<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Stripe Payment</title>
  <script src="https://js.stripe.com/v3/"></script>

  <style>
    body {
      font-family: Arial;
      padding: 30px;
    }

    #card-element {
      border: 1px solid #ccc;
      padding: 12px;
      border-radius: 5px;
      max-width: 420px;
      margin-bottom: 12px;
    }

    button {
      padding: 10px 20px;
      background: #28a745;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
  </style>
</head>

<body>

  <h2>Stripe Payment</h2>

  <form id="payment-form">
    <div id="card-element"></div>
    <div id="card-errors" style="color:red;"></div>
    <button id="submit">Pay</button>
  </form>

  <script>
    const stripe = Stripe('pk_test_51SjY15B56m619Hec0bOD4PF3oCMOr54bg9TtRZmCCk4HmmbkgBklKtwJuddlr3Gvwa5lF3bpIszzFuH4drKXMGaw00Xt9J1YwC'); // ✅ Public key
    const elements = stripe.elements();

    const card = elements.create('card');
    card.mount('#card-element');

    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // 1️⃣ Get client secret from server
      const response = await fetch('payment-intent.php');
      const data = await response.json();

      if (data.error) {
        document.getElementById('card-errors').textContent = data.error;
        return;
      }

      // 2️⃣ Confirm payment
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
        alert('✅ Payment Successful!');
      }
    });
  </script>

</body>

</html>