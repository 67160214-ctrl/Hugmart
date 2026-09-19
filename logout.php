<?php
require_once 'config/db.php';
unset($_SESSION['user']);
session_destroy();
header("Location: index.php");
exit();
?>