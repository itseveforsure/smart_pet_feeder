<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'smart_pet_feeder';
$user = 'root';
$pass = '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo "<h2 style='color:green'>✅ Login successful! Redirecting...</h2>";
            header("refresh:2; url=dashboard_simple.php");
        } else {
            $error = "Invalid username or password";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Login Test</title>
</head>
<body>
    <h1>Smart Pet Feeder - Login Test</h1>
    
    <?php if ($error): ?>
        <p style="color:red">❌ <?php echo $error; ?></p>
    <?php endif; ?>
    
    <form method="POST">
        <p>Username: <input type="text" name="username" value="petlover"></p>
        <p>Password: <input type="password" name="password" value="password123"></p>
        <button type="submit">Login</button>
    </form>
    
    <hr>
    <p>Test credentials:</p>
    <ul>
        <li>Username: petlover | Password: password123</li>
        <li>Username: admin | Password: admin123</li>
    </ul>
</body>
</html>