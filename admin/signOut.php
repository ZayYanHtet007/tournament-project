<?php
session_start();


$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
<<<<<<< HEAD
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
=======
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
>>>>>>> ZayYanHtet
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
<<<<<<< HEAD
?>
=======
>>>>>>> ZayYanHtet
