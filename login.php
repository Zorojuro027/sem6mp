<?php
require 'db.php'; // Ensure this path is correct
session_start(); // Start the session at the very beginning

// Check if the user is already logged in, if so, redirect to Home.html
if (isset($_SESSION['user_id'])) {
    header('Location: Home.html');
    exit;
}

$error_message = ''; // Variable to hold error messages

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error_message = "Please fill in both email and password.";
    } else {
        try {
            // Fetch the user by email
            $stmt = $pdo->prepare("SELECT id, email, password, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // If user found and password is correct
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID upon successful login for security
                session_regenerate_id(true);

                // Store user information in the session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['username']; // Optionally store username

                // Redirect to the main application page
                header("Location: Home.html"); // Corrected redirect
                exit;
            } else {
                // Set error message for invalid credentials
                $error_message = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            // Log the error and show a generic message to the user
            error_log("Database error during login: " . $e->getMessage());
            $error_message = "An error occurred. Please try again later.";
        }
    }
}

// If there was an error or it's not a POST request, show the login form again
// It's generally better to redirect back to Login.html with an error query parameter,
// but for simplicity here, we'll just output the error if one occurred during POST.
if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
     // You could redirect back to Login.html with an error message
     // header('Location: Login.html?error=' . urlencode($error_message));
     // exit;
     // Or just display the error here (less ideal user experience)
     echo "<p style='color:red; text-align:center; margin-top: 20px;'>" . htmlspecialchars($error_message) . " <a href='Login.html'>Try again</a></p>";
}

// If the script is accessed via GET, or if login fails and we haven't redirected,
// ideally, it should redirect to Login.html or display the login form.
// Since this file primarily handles the POST logic, it might be better
// to keep the HTML form solely in Login.html.
// If you want this file to ALSO display the form on GET, you'd include the HTML here.

// Inside login.php
if ($user && password_verify($password, $user['password'])) {
    // Login successful - THIS PART WAS SKIPPED
} else {
    // Login failed - THIS PART RAN
    $error_message = "Invalid email or password.";
    // ... (code to display the error)
}

?>