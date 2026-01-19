<?php
session_start();
require_once "../database/dbConfig.php";

if (
  !isset($_SESSION['user_id']) ||
  !$_SESSION['is_organizer'] ||
  $_SESSION['organizer_status'] !== 'approved'
) {
  header("Location: ../login.php");
  exit;
}
$tournament_id=(int)($_GET['tournament_id'] ?? 0);
if(!isset($_POST['schedule'] )){
  header("location: manage-schedule.php");
  exit;

}

foreach ($_POST['schedule'] as $match_id => $datetime){
   if(!$datetime) continue;

   $stmt = $conn->prepare("update matches set scheduled_time = ? where match_id =?");
$stmt->bind_param("si", $datetime,$match_id);
$stmt->execute();

}
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;

?>