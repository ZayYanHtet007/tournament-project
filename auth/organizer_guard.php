<?php
session_start();

if (
  !isset($_SESSION['user_id']) ||
  $_SESSION['is_organizer'] != 1 ||
  $_SESSION['organizer_status'] !== 'approved'
) {
  header("Location: ../login.php");
  exit;
}


