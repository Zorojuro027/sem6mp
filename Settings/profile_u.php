<?php

/*
// FILE: sem6mp/Settings/profile_update_handler.php
session_start();
require '../db.php'; // Go up one directory

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. Please log in."]);
    exit;
}
$user_id = intval($_SESSION['user_id']);

// Check request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

// --- Input Validation ---
$name = trim($_POST["name"] ?? '');
$email = trim($_POST["email"] ?? '');
// Add other fields like $dob if needed
*/
/*
if (empty($name) || empty($email) ) {   // Add checks for other fields
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Name and Email are required."]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid email format."]);
    exit;
}
*/

/*
// --- File Upload Handling ---
$update_picture = false;
$profile_pic_web_path = null; // Path relative to web root or display script
$profile_pic_db_value = null; // Path stored in DB

if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {
    // --- [PASTE THE ENTIRE FILE UPLOAD HANDLING LOGIC HERE] ---
    // This includes size check, MIME type check, defining $upload_dir_filesystem,
    // checking/creating the directory, checking writability, generating filename,
    // moving the file with move_uploaded_file(), setting $update_picture = true,
    // setting $profile_pic_web_path and $profile_pic_db_value.
    // Make sure $upload_dir_filesystem points to the correct place (e.g., __DIR__ . '/uploads')
    // and $profile_pic_db_value is the path you want to store (e.g., 'uploads/filename.jpg')

    // Example placeholder for file handling logic:
    $file_tmp_name = $_FILES["profile_picture"]["tmp_name"];
    $upload_dir_filesystem = __DIR__ . '/uploads'; // e.g., /path/to/htdocs/sem6mp/Settings/uploads
    $upload_dir_web = 'uploads'; // Relative path for src attribute
    $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION); // Basic extension getting
    $new_filename = "user_" . $user_id . "_" . time() . "." . strtolower($ext);
    $destination_filesystem = $upload_dir_filesystem . "/" . $new_filename;

     // **ADD PROPER VALIDATION (SIZE, TYPE as in previous example) HERE**

    if (!is_dir($upload_dir_filesystem)) { mkdir($upload_dir_filesystem, 0775, true); } // Create if needed

    if (move_uploaded_file($file_tmp_name, $destination_filesystem)) {
        $update_picture = true;
        $profile_pic_web_path = $upload_dir_web . "/" . $new_filename; // e.g., uploads/user_1_12345.jpg
        $profile_pic_db_value = $profile_pic_web_path; // Store this relative path
    } else {
         http_response_code(500);
         error_log("Failed to move uploaded file: " . $_FILES['profile_picture']['error']);
         echo json_encode(["success" => false, "message" => "Could not save uploaded file."]);
         exit;
    }
} elseif (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "File upload error code: " . $_FILES["profile_picture"]["error"]]);
    exit;
}


// --- Database Update ---
try {
    // Check if email is being changed and if the new email exists for another user
    $currentEmail = $_SESSION['email'] ?? ''; // Get current email from session
    if ($email !== $currentEmail) {
        $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $emailCheckStmt->execute([$email, $user_id]);
        if ($emailCheckStmt->fetch()) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email address already in use."]);
            exit;
        }
    }

    // Build SQL query
    $sql_parts = [];
    $params = [];
    $sql_parts[] = "name = :name"; $params[':name'] = $name;
    $sql_parts[] = "email = :email"; $params[':email'] = $email;
    if ($update_picture && $profile_pic_db_value !== null) {
        $sql_parts[] = "profile_pic = :profile_pic";
        $params[':profile_pic'] = $profile_pic_db_value;
    }
    // Add DOB etc. if needed: $sql_parts[] = "dob = :dob"; $params[':dob'] = $dob;

    if (!empty($sql_parts)) {
        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = :id";
        $params[':id'] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Update session
        $_SESSION['username'] = $name;
        $_SESSION['email'] = $email;
        // Optionally update session profile pic path if you use it elsewhere

        $response = ["success" => true, "message" => "Profile updated successfully!"];
        if ($profile_pic_web_path !== null) {
            $response["profile_pic"] = $profile_pic_web_path; // Send new path back to JS
        }
        echo json_encode($response);

    } else {
        echo json_encode(["success" => true, "message" => "No changes detected."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Failed to update profile for user ID $user_id: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: Failed to update profile."]);
}
    */
?>