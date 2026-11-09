<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /Watchlist/public/login.php');
exit;