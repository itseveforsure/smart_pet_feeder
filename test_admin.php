<?php
session_start();
require_once 'config.php';

echo "<h1>Admin Login Test</h1>";

// Test direct login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    echo "<h2>Debug Info:</h2>";
    echo "Username entered: " . $username . "<br>";
    echo "Password entered: " . $password . "<br>";
    
    if ($user) {
        echo "User found in database!<br>";
        echo "Stored hash: " . $user['password_hash'] . "<br>";
        
        if (password_verify($password, $user['password_hash'])) {
            echo "<span style='color:green'>✅ Password VERIFIED! Login successful!</span><br>";
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            echo "<meta http-equiv='refresh' content='2;url=admin_dashboard.php'>";
        } else {
            echo "<span style='color:red'>❌ Password verification FAILED!</span><br>";
            
            // Generate correct hash for your password
            $correct_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "Correct hash for '$password' would be: " . $correct_hash . "<br>";
            echo "Run this SQL to fix:<br>";
            echo "<code>UPDATE feeder_users SET password_hash = '$correct_hash' WHERE username = 'admin';</code>";
        }
    } else {
        echo "❌ User 'admin' not found!";
    }
}
?>

<form method="POST">
    <input type="text" name="username" placeholder="Username" value="admin"><br><br>
    <input type="password" name="password" placeholder="Password" value="admin123"><br><br>
    <button type="submit">Test Login</button>
</form>