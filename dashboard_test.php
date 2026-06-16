<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Dashboard Test</h1>";
echo "<pre>";
echo "Session data:\n";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green'>✅ You are logged in as: " . htmlspecialchars($_SESSION['username']) . "</p>";
    echo "<p><a href='dashboard.php'>Try original dashboard</a></p>";
    echo "<p><a href='logout.php'>Logout</a></p>";
} else {
    echo "<p style='color:red'>❌ You are NOT logged in. <a href='login.php'>Go to Login</a></p>";
}
?>