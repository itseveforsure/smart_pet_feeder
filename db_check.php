<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

$host = 'localhost';
$dbname = 'smart_pet_feeder';
$user = 'root';
$pass = '';

try {
    // Test MySQL connection without database
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    echo "✅ Connected to MySQL server successfully!<br><br>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Database '$dbname' exists<br>";
        $pdo->exec("USE $dbname");
        
        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "✅ Tables found: " . implode(', ', $tables) . "<br>";
        } else {
            echo "❌ No tables found. Please run the SQL to create tables.<br>";
        }
    } else {
        echo "❌ Database '$dbname' does NOT exist!<br>";
        echo "<a href='http://localhost/phpmyadmin' target='_blank'>Create it in phpMyAdmin</a><br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure MySQL is running in XAMPP.</p>";
}
?>