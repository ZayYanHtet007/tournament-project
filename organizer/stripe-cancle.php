<?php
require_once "../database/dbConfig.php";

$tournament_id = (int)$_GET['tournament_id'];

mysqli_query($conn, "
    DELETE FROM tournaments
    WHERE tournament_id = $tournament_id AND status = 'pending_payment'
");

echo "❌ Payment cancelled. Tournament was removed.";
