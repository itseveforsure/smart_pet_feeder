<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'smart_pet_feeder';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!<br>";
    echo "<a href='register.php'>Go to Register Page</a>";
} catch(PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>