<?php
/**
 * Database Configuration Template
 * 
 * Instructions:
 * 1. Copy this file to 'includes/db.php'
 * 2. Update the credentials below with your MySQL database details.
 * 3. Never commit 'includes/db.php' with real credentials to GitHub.
 */

// Set the default timezone (e.g., 'Asia/Kolkata' or your local timezone)
date_default_timezone_set('Asia/Kolkata');

// Database configuration
define('DB_SERVER', 'localhost');      // Database host (e.g., 'localhost' or '127.0.0.1')
define('DB_USERNAME', 'root');           // Database username
define('DB_PASSWORD', '');               // Database password
define('DB_NAME', 'canteen_db');         // Database name

// Attempt to connect to MySQL database via PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USERNAME, 
        DB_PASSWORD,
        [
            // Set PDO error mode to exception for better debugging & safety
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Persistent connections (set to false for standard local XAMPP/WAMP setups)
            PDO::ATTR_PERSISTENT => false,
            // Prevent emulation of prepared statements for SQL injection security
            PDO::ATTR_EMULATE_PREPARES => false,
            // Set default fetch mode to associative array
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Synchronize MySQL timezone with PHP timezone
    $pdo->exec("SET time_zone = '+05:30'");
    
} catch(PDOException $e) {
    // Log error to server logs without exposing credentials to end users
    error_log("Database connection failed: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Please check your configuration in includes/db.php.");
}
?>
