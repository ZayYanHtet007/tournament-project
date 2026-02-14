<?php
// privacy_policy.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Privacy Policy | TournaX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f8f9fa;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 900px;
      margin: 40px auto;
      background: #ffffff;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2 {
      color: #333;
    }

    p,
    li {
      color: #555;
      line-height: 1.6;
    }

    ul {
      margin-left: 20px;
    }

    .date {
      font-size: 14px;
      color: #777;
    }
  </style>
</head>

<body>

  <div class="container">
    <h1>Privacy Policy</h1>
    <p class="date">Last updated: <?php echo date("F d, Y"); ?></p>

    <p>
      Welcome to <strong>TournaX</strong>. Your privacy is important to us.
      This Privacy Policy explains how we collect, use, and protect your information.
    </p>

    <h2>1. Information We Collect</h2>
    <p>We may collect the following information:</p>
    <ul>
      <li>Name</li>
      <li>Email address</li>
      <li>Username and password (encrypted)</li>
      <li>Team and tournament details</li>
      <li>Login activity and system usage data</li>
    </ul>

    <h2>2. How We Use Your Information</h2>
    <p>Your information is used to:</p>
    <ul>
      <li>Create and manage your account</li>
      <li>Allow team and tournament participation</li>
      <li>Send important notifications (email or system)</li>
      <li>Improve system performance and security</li>
    </ul>

    <h2>3. Password Security</h2>
    <p>
      All passwords are stored using secure encryption methods.
      We never store or display your password in plain text.
    </p>

    <h2>4. Email Communication</h2>
    <p>
      We may send emails for account verification, password reset,
      and important tournament updates.
      We do not send spam emails.
    </p>

    <h2>5. Data Sharing</h2>
    <p>
      TournaX does not sell, trade, or share your personal information
      with third parties, except when required by law.
    </p>

    <h2>6. Cookies & Sessions</h2>
    <p>
      We use sessions and cookies to keep you logged in and improve
      user experience. You can disable cookies in your browser if you wish.
    </p>

    <h2>7. User Responsibility</h2>
    <p>
      You are responsible for keeping your login credentials secure.
      Do not share your password with others.
    </p>

    <h2>8. Changes to This Policy</h2>
    <p>
      We may update this Privacy Policy from time to time.
      Any changes will be posted on this page.
    </p>

    <h2>9. Contact Us</h2>
    <p>
      If you have any questions about this Privacy Policy,
      please contact the TournaX support team.
    </p>

    <p><strong>Thank you for using TournaX.</strong></p>
  </div>

</body>

</html>