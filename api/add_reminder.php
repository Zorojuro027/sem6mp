<?php
// File: sem6mp/api/add_reminder.php

session_start();
// Ensure db.php is one level up from the api directory
require '../db.php';

// Set header to return JSON
header('Content-Type: application/json');

// --- Check Login ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

// --- Read and Validate Input ---
// Get the raw POST data
$inputJSON = file_get_contents('php://input');
// Decode the JSON data into a PHP array
$input = json_decode($inputJSON, true);

// Check if JSON decoding was successful and data exists
if (json_last_error() !== JSON_ERROR_NONE || $input === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received.']);
    exit;
}

$title = trim($input['title'] ?? '');
$reminder_time_str = trim($input['reminder_time'] ?? '');

// Basic validation
if (empty($title) || empty($reminder_time_str)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing title or reminder_time.']);
    exit;
}

// More robust date/time validation
$reminder_time_obj = DateTime::createFromFormat('Y-m-d H:i:s', $reminder_time_str);
if (!$reminder_time_obj || $reminder_time_obj->format('Y-m-d H:i:s') !== $reminder_time_str) {
     http_response_code(400); // Bad Request
     error_log("Invalid reminder_time format received in add_reminder.php: " . $reminder_time_str);
     echo json_encode(['success' => false, 'message' => 'Invalid reminder_time format. Expected YYYY-MM-DD HH:MM:SS.']);
     exit;
}

// --- Insert into Database ---
try {
    // Use the $pdo object established in db.php
    // If db.php doesn't assign $pdo globally, you might need to adjust how it's accessed
    // Assuming $pdo is available globally or returned from db.php
    if (!isset($pdo)) {
         // Attempt to re-establish connection if $pdo isn't global (adjust db details if needed)
         // This is less ideal than db.php providing $pdo directly
         $host = 'localhost'; $db = 'fitsync'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
         $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
         $options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ];
         $pdo = new PDO($dsn, $user, $pass, $options);
    }


    $sql = "INSERT INTO reminders (user_id, title, reminder_time, is_sent) VALUES (:user_id, :title, :reminder_time, FALSE)";
    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->bindParam(':reminder_time', $reminder_time_str, PDO::PARAM_STR);

    // Execute the statement
    if ($stmt->execute()) {
        // Success
        echo json_encode(['success' => true, 'message' => 'Reminder added successfully.']);
    } else {
        // Execution failed
        http_response_code(500); // Internal Server Error
        error_log("Failed to execute reminder insert for user: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Failed to save reminder to database.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("Database error adding reminder for user $user_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred while adding the reminder.']);
}

?>