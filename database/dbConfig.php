<?php
$host = "database-tournax.j.aivencloud.com";
$username = "avnadmin";
$password = "AVNS_sIFFuS4VDRsaBQTQk--";
$database = "tournax";
$port = 13376;
$conn = mysqli_connect($host, $username, $password, $database, $port);
if (!$conn) {
  echo "Connection Failed :" . mysqli_connect_error();
} else {
  echo "Connected Successfully";
}
