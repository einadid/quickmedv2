<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('salesman');

redirect('/quickmed/salesman/dashboard.php');
?>