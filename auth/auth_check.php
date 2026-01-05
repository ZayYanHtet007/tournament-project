<?php
session_start();

/* ===== MUST BE LOGGED IN ===== */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
