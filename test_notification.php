<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Test adding a notification
addNotification($user_id, '🧪 Test Notification', 'This is a test notification from the system.', 'system');

echo "<h1>Notification Test</h1>";
echo "<p>Test notification added!</p>";
echo "<p><a href='dashboard.php'>Go to Dashboard and click the 🔔 bell icon</a></p>";
?>