<?php
// --- Start of db.php ---

// Error reporting should generally be off or logged to a file in production
// ini_set('display_errors', 0); // Example for production
// error_reporting(0);          // Example for production

// Keep error reporting on for development if needed, but remove echos
ini_set('display_errors', 1);
error_reporting(E_ALL);

// REMOVED: echo "DEBUG: db.php script started.<br>";

$host = 'localhost';
$db   = 'fitsync'; // Make sure this is correct
$user = 'root';    // Make sure this is correct for your MySQL
$pass = '';        // Make sure this is correct (often blank for default XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // PDO::ATTR_EMULATE_PREPARES   => false, // Good practice
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // REMOVED: echo "DEBUG: Database connection successful!<br>";
} catch (\PDOException $e) {
    // Log the error properly instead of echoing
    error_log("Database connection failed: " . $e->getMessage());
    // Provide a generic error message to the user or script
    // If this script is included, dying might be okay, but consider alternatives
    die("Database connection failed. Please check server logs or contact support.");
}

// REMOVED: echo "DEBUG: db.php script finished.<br>";

// --- End of db.php ---
?>