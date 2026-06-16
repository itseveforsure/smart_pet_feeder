<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Test</h1>";

$host = 'localhost';
$dbname = 'smart_pet_feeder';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    echo "✅ Connected to MySQL server<br>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Database '$dbname' exists<br>";
        
        $pdo->exec("USE $dbname");
        
        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        
        if (count($tables) > 0) {
            echo "✅ Tables found: " . count($tables) . "<br>";
            echo "<ul>";
            foreach($tables as $table) {
                echo "<li>" . $table[0] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "❌ No tables found! Please run the SQL to create tables.<br>";
        }
    } else {
        echo "❌ Database '$dbname' does NOT exist!<br>";
        echo "Please create it in phpMyAdmin.<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p>Make sure MySQL is running in XAMPP.</p>";
}
?>