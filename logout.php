<?php
// Caminho: faz_bem_v2/logout_v2.php
session_start();


$_SESSION = array();


session_destroy();


header("Location: login.html");
exit;
?>