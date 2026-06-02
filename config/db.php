<?php
// Database configuration for PDO
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "water_system";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // If database doesn't exist, try to create it
    try {
        $pdo_temp = new PDO("mysql:host=$servername;charset=utf8mb4", $username, $password);
        $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
        $pdo_temp = null;
        
        // Now connect to the database
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e2) {
        die("Database connection failed: " . $e2->getMessage());
    }
}
?>
