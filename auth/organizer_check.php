<?php
require_once "auth_check.php";

/* ===== ORGANIZER ONLY ===== */
if ($_SESSION['is_organizer'] != 1) {
  header("Location: login.php");
  exit;
}
