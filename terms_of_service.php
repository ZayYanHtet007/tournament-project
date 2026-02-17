<?php
// terms_of_service.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Player Terms of Service | TournaX</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f8f9fa;
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
      line-height: 1.7;
    }

    ul {
      margin-left: 20px;
    }

    .footer {
      margin-top: 30px;
      font-size: 14px;
      color: #777;
      text-align: center;
    }
  </style>
</head>

<body>

  <div class="container">
    <h1>Player Terms of Service</h1>
    <p><strong>Last Updated:</strong> <?php echo date("F d, Y"); ?></p>

    <p>
      Welcome to <strong>TournaX</strong>. By registering, accessing, or using the platform as a player,
      you agree to comply with and be bound by the following Terms of Service.
    </p>

    <h2>1. Eligibility</h2>
    <p>
      Any user may register and use TournaX as a player. By creating an account,
      you confirm that all information you provide is accurate, current, and complete.
    </p>
    <p>
      You agree to use the platform only for lawful purposes and in accordance
      with these Terms of Service.
    </p>
    <h2>2. Player Account</h2>
    <ul>
      <li>You are responsible for maintaining the confidentiality of your login credentials.</li>
      <li>You must not share your account with others.</li>
      <li>You are responsible for all activities performed through your account.</li>
    </ul>

    <h2>3. Tournament Participation</h2>
    <ul>
      <li>Players must follow all tournament rules set by the organizer.</li>
      <li>Any form of cheating, exploiting bugs, or unfair play is strictly prohibited.</li>
      <li>Violation of rules may result in disqualification or account suspension.</li>
    </ul>

    <h2>4. Payments & Fees</h2>
    <p>
      Some tournaments may require entry fees. All payments made through the platform
      are non-refundable unless stated otherwise by the organizer.
    </p>

    <h2>5. Code of Conduct</h2>
    <ul>
      <li>Respect other players, organizers, and administrators.</li>
      <li>Harassment, abusive language, or offensive behavior is not allowed.</li>
      <li>Impersonation or false identity is prohibited.</li>
    </ul>

    <h2>6. Content & Data</h2>
    <p>
      Any content you submit (such as team names or profiles) must be appropriate and lawful.
      TournaX reserves the right to remove any content that violates these terms.
    </p>

    <h2>7. Account Suspension or Termination</h2>
    <p>
      TournaX may suspend or terminate your account if you violate these Terms of Service
      or engage in activities that harm the platform or other users.
    </p>

    <h2>8. Limitation of Liability</h2>
    <p>
      TournaX is not responsible for any losses, damages, or disputes arising from
      tournament participation or interactions between users.
    </p>

    <h2>9. Changes to Terms</h2>
    <p>
      TournaX reserves the right to update or modify these terms at any time.
      Continued use of the platform means you accept the updated terms.
    </p>

    <h2>10. Contact</h2>
    <p>
      If you have any questions regarding these Terms of Service, please contact the system administrator.
    </p>

    <div class="footer">
      &copy; <?php echo date("Y"); ?> TournaX. All rights reserved.
    </div>
  </div>

</body>

</html>