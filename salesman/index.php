<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('/quickmed/login.php');
}

if ($_SESSION['role'] !== 'salesman') {
    redirect('/quickmed/login.php');
}

redirect('/quickmed/salesman/dashboard.php');
?>