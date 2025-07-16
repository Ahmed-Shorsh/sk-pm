<?php
// File: backend/db.php
// Purpose: Establish a secure PDO connection to the MySQL database



// hosted
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'dbvwgo85dtqpmm');
// define('DB_USER', 'ukjjbxzlryc5n');
// define('DB_PASS', '$sk$-101');

// local
define('DB_HOST', 'localhost');
define('DB_NAME', 'sk-pm');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Use PDO for secure connections and prepared statements
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    // Throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Emulate prepares OFF for native prepared statements
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // In production, log this message instead of echoing
    die('Database connection failed: ' . $e->getMessage());
}
