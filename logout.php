<?php
session_set_cookie_params(['lifetime'=>1800,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();
session_regenerate_id(true);
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();
header("Location: login.php");
exit();
?>