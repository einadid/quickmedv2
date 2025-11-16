<?php
session_start();
echo "<h1>Customer Test Page</h1>";
echo "<p>Session User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Session Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
echo "<p>Current Directory: " . __DIR__ . "</p>";
echo "<p>File exists check:</p>";
echo "<ul>";
echo "<li>header.php: " . (file_exists(__DIR__ . '/includes/header.php') ? '✅ YES' : '❌ NO') . "</li>";
echo "<li>footer.php: " . (file_exists(__DIR__ . '/includes/footer.php') ? '✅ YES' : '❌ NO') . "</li>";
echo "<li>database.php: " . (file_exists(__DIR__ . '/../config/database.php') ? '✅ YES' : '❌ NO') . "</li>";
echo "</ul>";
?>