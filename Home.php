<?php
// send_reminder.php
header('Content-Type: application/json');

// Database configuration – update these with your actual details
$servername = "localhost";
$username = "your_db_username";
$password = "your_db_password";
$dbname = "your_db_name";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
  exit;
}

// Query to get user emails from the users table
$sql = "SELECT email FROM users";
$result = $conn->query($sql);
if (!$result) {
  echo json_encode(["status" => "error", "message" => "Database query failed."]);
  exit;
}

$successCount = 0;
$failureCount = 0;

while ($row = $result->fetch_assoc()) {
  $to = $row['email'];
  $subject = "Time to Drink Water!";
  $message = "Hi,\n\nIt's time to drink water. Please log your water intake on FitSync.\n\nStay hydrated!";
  $headers = "From: no-reply@fitsync.com\r\n" .
             "Reply-To: no-reply@fitsync.com\r\n" .
             "X-Mailer: PHP/" . phpversion();
  
  if (mail($to, $subject, $message, $headers)) {
    $successCount++;
  } else {
    $failureCount++;
  }
}

$conn->close();

echo json_encode([
  "status" => "success",
  "message" => "Email reminders sent: $successCount successful, $failureCount failed."
]);
?>