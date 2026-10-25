<?php
// Set the default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Database configuration for your single InfinityFree Database
define('DB_SERVER', 'use yours'); // Replace with your InfinityFree DB Host if different
define('DB_USERNAME', 'use yours');
define('DB_PASSWORD', 'use yours');
define('DB_NAME', 'use yours');

// Attempt to connect to MySQL database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, 
        DB_USERNAME, 
        DB_PASSWORD,
        [
            // Set the PDO error mode to exception for better error handling
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Use persistent connections to reduce the load on the InfinityFree server
            PDO::ATTR_PERSISTENT => true,
            // Prevent emulation of prepared statements for better security
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Synchronize MySQL timezone with PHP timezone (IST)
    // This ensures MySQL's NOW() matches PHP's time() in production
    $pdo->exec("SET time_zone = '+05:30'");
    
} catch(PDOException $e) {
    // If the connection fails, stop the script and show a generic error
    // Do not output $e->getMessage() to users in production for security
    error_log("Database connection failed: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Please try again later.");
}
?>
