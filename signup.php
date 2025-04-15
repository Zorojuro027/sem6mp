<?php
// Force error reporting ON for debugging (REMOVE LATER)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Signup Debug</title></head><body>"; // Start HTML for readable debug output
echo "DEBUG: signup.php script started.<br>";

require 'db.php'; // db.php should still have its debug echos temporarily

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "DEBUG: POST request received.<br>";

    // Retrieve data, use trim() and null coalescing operator ??
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    echo "DEBUG: Received Data -> Username: '" . htmlspecialchars($username) . "', Email: '" . htmlspecialchars($email) . "'<br>";
    echo "DEBUG: Received Data -> Password Length: " . strlen($password) . ", Confirm Password Length: " . strlen($password_confirm) . "<br>";

    // --- Basic Validation ---
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error_message = "Please fill in all fields.";
        echo "DEBUG: Validation Stop -> Empty fields detected.<br>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
        echo "DEBUG: Validation Stop -> Invalid email format.<br>";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
        echo "DEBUG: Validation Stop -> Password too short.<br>";
    } elseif ($password !== $password_confirm) {
        $error_message = "Passwords do not match.";
        echo "DEBUG: Validation Stop -> Passwords do not match.<br>";
    } else {
        echo "DEBUG: Basic validation passed.<br>";
        // --- Database Operations ---
        try {
            echo "DEBUG: Checking if email '$email' already exists...<br>";
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch(); // Use a different variable name

            if ($existingUser) {
                $error_message = "Email address is already registered.";
                echo "DEBUG: Email Check Stop -> Email already exists (ID: " . $existingUser['id'] . ").<br>";
            } else {
                echo "DEBUG: Email check passed - Email is new.<br>";
                // --- Hash Password and Insert User ---
                echo "DEBUG: Hashing password...<br>";
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                echo "DEBUG: Preparing INSERT statement...<br>";
                $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");

                echo "DEBUG: Executing INSERT for user '$username' with email '$email'...<br>";
                if ($insertStmt->execute([$username, $email, $hashedPassword])) {
                    echo "DEBUG: INSERT successful! User should be in DB now.<br>";
                    // --- SUCCESS ---
                    echo "DEBUG: Redirecting to Login.html?signup=success...<br>";
                    echo "</body></html>"; // End HTML before redirect header
                    header("Location: Login.html?signup=success");
                    exit; // Crucial: stop script execution after redirect
                } else {
                    $error_message = "Registration failed during database insert execution.";
                    echo "DEBUG: INSERT execute() returned false. Check database permissions/constraints.<br>";
                }
            }
        } catch (PDOException $e) {
            error_log("Database error during signup: " . $e->getMessage());
            $error_message = "A database error occurred during registration.";
            echo "DEBUG: Database PDOException Caught: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "DEBUG: Not a POST request. Redirecting to signup.html...<br>";
    echo "</body></html>"; // End HTML before redirect header
    header('Location: signup.html');
    exit; // Crucial: stop script execution after redirect
}

// --- Display Error if Occurred During POST ---
if (!empty($error_message)) {
    echo "DEBUG: Final Error Message: " . htmlspecialchars($error_message) . "<br>";
    // You might want to display this more nicely or redirect back with an error parameter
    echo "<p style='color:red; font-weight: bold;'>Error: " . htmlspecialchars($error_message) . "</p>";
    echo "<p><a href='signup.html'>Try Signing Up Again</a></p>";
}

echo "DEBUG: signup.php script finished (if no redirect happened).<br>";
echo "</body></html>"; // End HTML
?>