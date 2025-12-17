

<?php
    $server = $_SERVER['SERVER_NAME'] ?? "localhost";
    // if($server == "localhost"){
    //     $host = "localhost";
    //     $port = "3306";
    //     $username = "root";
    //     $password = "";
    // }else{
        require_once __DIR__ . '/../loadEnv.php';

        loadEnv(__DIR__ . '/../.env');
        $host = $_ENV["DB_HOST"];
        $port = $_ENV["DB_PORT"];
        $username = $_ENV["DB_USER"];
        $password = $_ENV["DB_PASSWORD"];
    //}

    $databaseName = "tournax";

    $conn = new mysqli($host, $username, $password, $databaseName, $port);
    if($conn->connect_error){
        die("Connection failed: " . $conn->connect_error);
    }else{
        echo "Connection successfully to $server";
    }

