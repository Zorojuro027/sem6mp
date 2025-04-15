
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Prevent caching of protected pages:
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database configuration (update with your actual credentials)
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'logout'; // Using the 'logout' database

    // Create connection using mysqli
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Record the logout event if the user is logged in
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];

        // Prepare an SQL statement to insert logout event
        $stmt = $conn->prepare("INSERT INTO logout_events (user_id, logout_time) VALUES (?, NOW())");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            error_log("Logout event insert error: " . $stmt->error);
        }
        $stmt->close();
    }

    $conn->close();

    // End the session and redirect to login page
    session_destroy();
    header('Location: login.html');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logout</title>
</head>
<body>
  <form method="post" action="">
    <button type="submit">Log Out</button>
  </form>

  <script>
    window.onpageshow = function(event) {
  if (event.persisted) {
    window.location.reload();
  }
};
  </script>
</body>
</html>
