<?php
include("../config/database.php");
session_start();

$user_id = $_SESSION['user_id'];

$result = $conn->query(
  "SELECT * FROM notifications 
   WHERE user_id=$user_id 
   ORDER BY created_at DESC"
);

echo json_encode($result->fetch_all(MYSQLI_ASSOC));
