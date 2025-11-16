<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('shop_admin');

redirect('/quickmed/shop-admin/dashboard.php');
?>