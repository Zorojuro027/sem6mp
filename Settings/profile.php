<?php
session_start(); // Start the session to access logged-in user info
require '../db.php'; // Include your database connection

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login.html'); // Redirect to login page
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// --- Start: POST handling logic ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json'); // Set header for JSON response

    // Re-check login within POST context (good practice)
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $user_id_post = intval($_SESSION['user_id']);

    // Get name, email from $_POST
    $name_post = trim($_POST['name'] ?? '');
    $email_post = trim($_POST['email'] ?? '');

    // --- Basic Input Validation ---
    $errors_post = [];
    if (empty($name_post)) {
        $errors_post[] = 'Name is required.';
    }
    if (empty($email_post)) {
        $errors_post[] = 'Email is required.';
    } elseif (!filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
        $errors_post[] = 'Invalid email format.';
    }

    // --- ** START: Detailed File Upload Handling ** ---
    $update_picture_post = false;
    $profile_pic_db_value = null; // Path to store in DB (relative to web root)
    $profile_pic_web_path_post = null; // Full path for response (can be same as db value)

    // Check if a file was uploaded without errors
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {

        $file_tmp_name = $_FILES["profile_picture"]["tmp_name"];
        $file_name = $_FILES["profile_picture"]["name"];
        $file_size = $_FILES["profile_picture"]["size"];
        $file_type = $_FILES["profile_picture"]["type"];
        $file_error = $_FILES["profile_picture"]["error"]; // Redundant check, but good practice

        // Define upload directory (relative to this script's location)
        // Creates an 'uploads' folder inside the 'Settings' directory
        $upload_dir_filesystem = __DIR__ . '/uploads/';
        // Define the web-accessible path (relative to the document root or a base URL)
        // Adjust 'Settings/uploads/' if your web server root is different
        $upload_dir_web = 'uploads'; // Path used in <img> src attribute

        // --- File Validation ---
        // 1. Check Size (e.g., max 5MB)
        $max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
        if ($file_size > $max_file_size) {
            $errors_post[] = "File is too large. Maximum size is 5MB.";
        }

        // 2. Check Type (allow common image types)
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_mime_type = mime_content_type($file_tmp_name); // More reliable than $_FILES['type']

        if (!in_array($file_mime_type, $allowed_mime_types)) {
            $errors_post[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }

        // --- Process Upload if Validation Passed ---
        if (empty($errors_post)) {
            // Create a unique filename to prevent overwrites and conflicts
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_filename = "user_" . $user_id_post . "_" . time() . "." . $file_extension;
            $destination_filesystem = $upload_dir_filesystem . $new_filename;

            // Create the uploads directory if it doesn't exist
            if (!is_dir($upload_dir_filesystem)) {
                if (!mkdir($upload_dir_filesystem, 0775, true)) { // Create recursively with permissions
                    $errors_post[] = "Failed to create upload directory.";
                    error_log("Failed to create directory: " . $upload_dir_filesystem);
                }
            }

            // Check if directory is writable (important!)
            if (is_dir($upload_dir_filesystem) && !is_writable($upload_dir_filesystem)) {
                 $errors_post[] = "Upload directory is not writable.";
                 error_log("Upload directory not writable: " . $upload_dir_filesystem);
            }

            // Move the uploaded file if no directory errors occurred
            if (empty($errors_post)) {
                if (move_uploaded_file($file_tmp_name, $destination_filesystem)) {
                    // File moved successfully!
                    $update_picture_post = true;
                    // Store the web-accessible path in the DB
                    $profile_pic_db_value = $upload_dir_web . "/" . $new_filename; // e.g., "uploads/user_123_1678886400.jpg"
                    $profile_pic_web_path_post = $profile_pic_db_value; // Send this back to JS
                } else {
                    $errors_post[] = "Failed to move uploaded file. Check permissions.";
                    error_log("move_uploaded_file failed for temp file: " . $file_tmp_name . " to " . $destination_filesystem);
                }
            }
        }

    } elseif (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors (e.g., file too large based on php.ini)
        $errors_post[] = "File upload error code: " . $_FILES["profile_picture"]["error"];
    }
    // --- ** END: Detailed File Upload Handling ** ---


    // --- Proceed only if no errors ---
    if (!empty($errors_post)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(' ', $errors_post)]);
        exit;
    }

    // --- Database Update ---
    try {
        // Check if email is being changed and if the new email exists for another user
        $currentEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $currentEmailStmt->execute([$user_id_post]);
        $currentEmailRow = $currentEmailStmt->fetch();
        $currentEmail = $currentEmailRow ? $currentEmailRow['email'] : null;

        if ($email_post !== $currentEmail) {
            $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailCheckStmt->execute([$email_post, $user_id_post]);
            if ($emailCheckStmt->fetch()) {
                // Email already exists for another user
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Email address already in use by another account."]);
                exit;
            }
        }

        // Build SQL query dynamically
        $sql_parts = [];
        $params = [];

        // Always update name and email if they passed validation
        $sql_parts[] = "username = :username"; $params[':username'] = $name_post;
        $sql_parts[] = "email = :email"; $params[':email'] = $email_post;

        // Add profile picture update only if a new file was successfully uploaded
        if ($update_picture_post && $profile_pic_db_value !== null) {
            $sql_parts[] = "profile_pic = :profile_pic";
            $params[':profile_pic'] = $profile_pic_db_value;
        }
        // Add other fields like DOB if needed:
        // $sql_parts[] = "dob = :dob"; $params[':dob'] = $dob_post;

        if (!empty($sql_parts)) {
            $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = :id";
            $params[':id'] = $user_id_post;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Update session variables
            $_SESSION['username'] = $name_post;
            $_SESSION['email'] = $email_post;
            if ($update_picture_post && $profile_pic_db_value !== null) {
                 // Optionally update session profile pic path if you use it elsewhere dynamically
                 // $_SESSION['profile_pic'] = $profile_pic_db_value;
            }

            $response = ["success" => true, "message" => "Profile updated successfully!"];
            // Send back the *new* web path if the picture was updated
            if ($profile_pic_web_path_post !== null) {
                // Prepend '../Settings/' because the JS is likely in Home.html (one level up)
                // Adjust this relative path if your JS file location is different
                $response["profile_pic"] = '../Settings/' . $profile_pic_web_path_post;
            }
            echo json_encode($response);

        } else {
            // Should not happen if name/email are always updated, but good fallback
            echo json_encode(["success" => true, "message" => "No changes detected."]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Failed to update profile for user ID $user_id_post: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Database error: Failed to update profile."]);
    }
    exit; // Crucial exit after POST handling
    // --- End: POST handling logic ---
}

// --- Start: Original profile.php logic for displaying the page (GET request) ---
$userData = null;
$memberSince = 'N/A';
$currentMood = null;
$currentJournal = '';
$currentInspirationType = 'none';
$currentInspirationContent = null;
$darkModePref = false; // Default dark mode preference

// --- Fetch User Data, Mood, Journal, Inspiration, Preferences ---
try {
    // Fetch basic user data + preferences
    $stmtUser = $pdo->prepare("SELECT username, email, created_at, profile_pic, dark_mode_enabled, inspirational_message_type, inspirational_message_content FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        // Format registration date
        if (!empty($userData['created_at'])) {
            try {
                 $regDate = new DateTime($userData['created_at']);
                 $memberSince = $regDate->format('F Y'); // Format as "Month Year"
            } catch (Exception $e) {
                 $memberSince = 'Invalid Date'; // Handle potential date parsing errors
                 error_log("Error parsing created_at date for user $user_id: " . $userData['created_at']);
            }
        }
        // Set preferences
        $darkModePref = !empty($userData['dark_mode_enabled']); // Treat 0/NULL as false, 1 as true
        $currentInspirationType = $userData['inspirational_message_type'] ?? 'none';
        $currentInspirationContent = $userData['inspirational_message_content'] ?? null;
    } else {
        // Handle case where user data couldn't be fetched (shouldn't happen if logged in)
        error_log("Could not fetch user data for logged-in user ID: " . $user_id);
        // Maybe redirect to logout or show an error page
    }

    // Fetch today's mood log
    $stmtMood = $pdo->prepare("SELECT mood FROM mood_log WHERE user_id = ? AND log_date = ?");
    $stmtMood->execute([$user_id, $today]);
    $moodData = $stmtMood->fetch(PDO::FETCH_ASSOC);
    $currentMood = $moodData['mood'] ?? null;

    // Fetch today's journal entry
    $stmtJournal = $pdo->prepare("SELECT content FROM journal_entries WHERE user_id = ? AND entry_date = ?");
    $stmtJournal->execute([$user_id, $today]);
    $journalData = $stmtJournal->fetch(PDO::FETCH_ASSOC);
    $currentJournal = $journalData['content'] ?? '';

} catch (PDOException $e) {
    error_log("Error fetching profile data for user $user_id: " . $e->getMessage());
    // Handle error gracefully, maybe show a message to the user on the page
    // For now, variables will keep their default values
}

// Set display variables (with defaults/fallbacks)
$profileNameDisplay = htmlspecialchars($userData['username'] ?? 'User Name');
$profileEmailDisplay = htmlspecialchars($userData['email'] ?? 'user@example.com');
$defaultPic = 'https://via.placeholder.com/120/39d75b/FFFFFF?text=User'; // Default placeholder
$profilePicPath = $userData['profile_pic'] ?? null; // Path stored in DB (e.g., "uploads/user_123_time.jpg")

// Construct the correct web path relative to the web root/calling script
// Assuming profile.php is in Settings/, and uploads are in Settings/uploads/
// The path needed for the <img> src attribute relative to profile.php is just the path stored in the DB
$profilePicDisplay = $defaultPic; // Start with default
if ($profilePicPath) {
    // Check if the file actually exists relative to *this* script's directory
    $profilePicFilesystemPath = __DIR__ . '/' . $profilePicPath; // e.g., /path/to/htdocs/sem6mp/Settings/uploads/user_123_time.jpg
    if (file_exists($profilePicFilesystemPath)) {
        // Use the path stored in the DB directly, as it's relative to this Settings folder
        $profilePicDisplay = htmlspecialchars($profilePicPath) . '?t=' . time(); // Add timestamp to prevent caching
    } else {
         error_log("Profile picture file not found at: " . $profilePicFilesystemPath . " (DB path: " . $profilePicPath . ")");
    }
}

// Determine initial class for body based on dark mode preference
$bodyClass = $darkModePref ? 'dark-mode' : '';

// Determine initial state for mood buttons and journal
$moodButtonStates = [];
$allowed_moods = ['Happy', 'Neutral', 'Sad', 'Energetic', 'Tired'];
foreach ($allowed_moods as $mood) {
    $moodButtonStates[$mood] = ($currentMood === $mood) ? 'selected' : '';
}

// Determine initial quote display
$initialQuoteContent = '';
$initialQuoteAuthor = '';
if ($currentInspirationType === 'quote' && $currentInspirationContent) {
    // Attempt to split stored quote "Content // Author"
    $parts = explode(' // ', $currentInspirationContent, 2);
    if (count($parts) === 2) {
        $initialQuoteContent = htmlspecialchars(trim($parts[0]));
        $initialQuoteAuthor = '— ' . htmlspecialchars(trim($parts[1]));
    } else {
        // If format is unexpected, display the whole thing as content
        $initialQuoteContent = htmlspecialchars($currentInspirationContent);
        $initialQuoteAuthor = '— Unknown';
    }
} elseif ($currentInspirationType === 'personal' && $currentInspirationContent) {
    $initialQuoteContent = htmlspecialchars($currentInspirationContent);
    $initialQuoteAuthor = '— You';
} else {
    $initialQuoteContent = 'Click below for an inspiring quote!';
    $initialQuoteAuthor = '';
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <title>FitSync Dashboard - Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Base Layout & Font */
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background-color: #f5f7fa; /* Light background */
      color: #333; /* Dark text */
      transition: background-color 0.3s ease, color 0.3s ease;
    }
    .layout-table {
      width: 100%;
      min-height: 100vh;
      border-collapse: collapse;
    }
    .main-cell {
      width: 80%;
      vertical-align: top;
      padding: 0; /* Remove padding, content-area will handle it */
      transition: background-color 0.3s, color 0.3s;
    }

    /* Sidebar Styling */
    .sidebar-cell {
      width: 20%; /* Adjust width as needed */
      min-width: 180px; /* Minimum width for sidebar */
      vertical-align: top;
      background-color: #39d75b;
      padding: 10px;
      border-right: 1px solid #ccc;
      transition: background-color 0.3s, color 0.3s;
    }
    .sidebar-cell h2 {
      margin: 0 0 20px;
      font-size: 28px;
      color: #000;
      transition: color 0.3s;
      text-align: center; /* Center title */
    }
    body.dark-mode .sidebar-cell {
      background-color: #1AA447;
      color: #ccc;
      border-right: 1px solid #333;
    }
    body.dark-mode .sidebar-cell h2 {
      color: #000000;
    }
    .sidebar-cell ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .sidebar-cell ul li {
      margin: 15px 0;
      position: relative;
    }
    .pill-button {
      background-color: #39d75b;
      border: 2px solid #000;
      border-radius: 30px;
      color: #000;
      padding: 10px 15px; /* Adjusted padding */
      width: 100%;
      text-align: left;
      cursor: pointer;
      transition: background-color 0.3s, border-color 0.3s, color 0.3s;
      font-size: 15px; /* Adjusted font size */
      display: flex;
      align-items: center;
      gap: 8px; /* Slightly increased gap */
      box-sizing: border-box;
    }
    .pill-button:hover {
      background-color: rgba(0, 0, 0, 0.15); /* Slightly darker hover */
    }
    .sidebar-cell ul li a {
      color: #000;
      text-decoration: none;
      display: block;
      width: 100%;
      transition: color 0.3s;
      font-weight: 500; /* Slightly bolder */
    }
    body.dark-mode .pill-button {
      background-color: #1DB954;
      color: #000000;
      border: 2px solid #004c0a;
    }
    body.dark-mode .pill-button a {
      color: #000000;
    }
    body.dark-mode a {
      color: #1DB954; /* Use theme green for links */
    }
    body.dark-mode a:hover {
      color: #ffffff;
    }

    /* Accordion Dropdown for Sidebar */
    .dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease-in-out; /* Smoother transition */
      margin-left: 20px;
      padding-left: 0;
      list-style: none;
    }
    .dropdown:hover .dropdown-menu {
      max-height: 300px;
    }
    .dropdown-menu li {
      margin: 8px 0; /* Adjusted spacing */
    }
    .dropdown-menu .pill-button {
      font-size: 14px;
      padding: 8px 15px;
    }

    /* Dark Mode Colors */
    body.dark-mode {
      background-color: #121212;
      color: #ccc;
    }
    body.dark-mode .sidebar-cell {
      background-color: #1AA447;
      color: #ccc;
      border-right: 1px solid #333;
    }
    body.dark-mode .main-cell {
      background-color: #191414;
      color: #ccc;
    }
    body.dark-mode .pill-button {
      background-color: #1DB954;
      color: #000000;
      border: 2px solid #004c0a;
    }
    body.dark-mode .pill-button a {
      color: #000000;
    }
    body.dark-mode a {
      color: #1DB954; /* Use theme green for links */
    }
    body.dark-mode a:hover {
      color: #ffffff;
    }

    /* Updated Dark Mode Button and Sidebar Styling */
    input#dark-mode-toggle {
      display: none;
    }
    .dark-mode-btn {
      cursor: pointer;
      position: fixed;
      top: 10px;
      right: 10px;
      background-color: #333;
      color: #fff;
      padding: 8px 12px;
      border-radius: 5px;
      z-index: 1000;
      transition: background-color 0.3s;
    }
    .dark-mode-btn:hover {
      background-color: #444;
    }

    /* Accordion Dropdown for Sidebar */
    .dropdown-menu {
      list-style: none; /* Remove list bullets */
      padding: 0; /* Remove default padding */
      margin: 10px 0 0 20px; /* Add top margin and indent */
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
      /* background-color: rgba(255, 255, 255, 0.1); Optional subtle background */
      border-radius: 5px;
    }
    .dropdown.open > .dropdown-menu { /* Target only when parent has 'open' */
        max-height: 300px; /* Adjust as needed */
    }
    .dropdown-menu li {
        margin: 0; /* Reset margin for list items */
    }
    .dropdown-menu .pill-button {
      font-size: 15px; /* Slightly smaller font */
      padding: 8px 15px; /* Adjust padding */
      margin-bottom: 5px; /* Space between dropdown items */
      background-color: #ffffff; /* Match sidebar bg */
      border: 1px solid #000; /* Lighter border */
    }
    .dropdown-menu .pill-button:hover {
        background-color: rgba(0, 0, 0, 0.2); /* Slightly darker hover */
    }


    /* Dark Mode Colors */
    body.dark-mode {
      background-color: #121212; /* Very dark grey */
      color: #eee; /* Light text */
    }
    body.dark-mode .main-cell {
      background-color: #191414; /* Slightly different dark shade */
      color: #eee;
    }
    body.dark-mode .header-bar {
      background-color: #1f1f1f; /* Darker header */
      box-shadow: 0 2px 10px rgba(0,0,0,0.3); /* Stronger shadow */
    }
    body.dark-mode .profile-header,
    body.dark-mode .mood-tracker,
    body.dark-mode .daily-journal,
    body.dark-mode .quote-section {
      background-color: #272727; /* Consistent dark background for sections */
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
     body.dark-mode .profile-picture {
        border-color: #444; /* Darker border in dark mode */
     }
    body.dark-mode .profile-email {
      color: #aaa;
    }
     body.dark-mode .profile-date {
        color: #aaa;
     }
    body.dark-mode .edit-picture {
        background-color: #1DB954; /* Spotify green */
    }
    body.dark-mode .action-button.primary-button,
    body.dark-mode .save-journal,
    body.dark-mode .get-quote {
      background-color: #1DB954; /* Spotify green */
      color: #fff;
      border-color: #1DB954;
    }
    body.dark-mode .action-button.primary-button:hover,
    body.dark-mode .save-journal:hover,
    body.dark-mode .get-quote:hover {
        background-color: #18A94A; /* Slightly darker hover */
        box-shadow: 0 5px 15px rgba(29, 185, 84, 0.2);
    }
    body.dark-mode .quote-display {
        color: #bbb;
    }
    body.dark-mode .quote-author {
        color: #999;
    }
     body.dark-mode .journal-area, body.dark-mode .form-control {
      background-color: #333;
      color: #eee;
      border-color: #555;
    }
    body.dark-mode .journal-area::placeholder, body.dark-mode .form-control::placeholder {
        color: #888;
    }
     body.dark-mode .journal-area:focus, body.dark-mode .form-control:focus {
      border-color: #1DB954;
      box-shadow: 0 0 0 3px rgba(29, 185, 84, 0.3);
      outline: none;
    }
    body.dark-mode .modal-content {
      background-color: #2a2a2a;
      color: #eee;
    }
     body.dark-mode .close-modal {
         color: #888;
     }
    body.dark-mode .close-modal:hover {
        color: #1DB954;
    }
    body.dark-mode #toast {
        background-color: #e0e0e0;
        color: #121212;
    }
     body.dark-mode .section-title {
        color: #1DB954;
        border-bottom-color: #333;
    }

    /* --- Header Bar --- */
    .header-bar {
      background-color: #fff;
      padding: 15px 40px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 900;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
    }
    .page-title {
      font-size: 24px;
      font-weight: 600;
      margin: 0;
    }

    /* --- Content Area --- */
    .content-area {
      padding: 30px 40px;
      /* Adjust height calculation if header height changes */
      /* Consider removing fixed height for flexibility */
      /* height: calc(100vh - 70px); */
      /* overflow-y: auto; */
    }
    .profile-container {
      max-width: 1000px;
      margin: 0 auto;
      animation: fadeIn 0.5s ease-in;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* --- Profile Specific Sections --- */
    /* Profile Header & Details */
    .profile-header {
      background-color: #fff;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      padding: 30px;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      margin-bottom: 30px;
      transition: all 0.3s ease;
    }
    .profile-details {
      display: flex;
      align-items: center;
      gap: 30px;
      flex-grow: 1;
    }
    .profile-picture-container {
      position: relative;
      flex-shrink: 0;
    }
    .profile-picture {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #fff;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    .edit-picture {
      position: absolute;
      bottom: 5px;
      right: 5px;
      background-color: #39d75b;
      color: white;
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      cursor: pointer;
      transition: all 0.3s ease;
      opacity: 0;
      visibility: hidden;
    }
    .profile-picture-container:hover .edit-picture {
      opacity: 1;
      visibility: visible;
      transform: scale(1.1);
    }
    .profile-picture-container:hover .profile-picture {
      transform: scale(1.03);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    .profile-info {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .profile-name {
      font-size: 28px;
      font-weight: 700;
      margin: 0;
      line-height: 1.2;
    }
    .profile-email {
      color: #666;
      margin: 0;
      font-size: 15px;
    }
    .profile-date {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #888;
      margin-top: 8px;
      font-size: 14px;
    }
    .profile-actions {
      display: flex;
      gap: 15px;
      flex-shrink: 0;
    }
    .action-button {
      padding: 12px 24px;
      border-radius: 30px;
      font-weight: 600;
      transition: all 0.3s ease;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      border: none;
      font-size: 15px;
    }
    .primary-button {
      background-color: #39d75b;
      color: white;
    }
    .primary-button:hover {
      background-color: #2ebf51;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(57, 215, 91, 0.3);
    }

    /* Mood Tracker, Journal, Quote Sections */
    .mood-tracker, .daily-journal, .quote-section {
      background-color: #fff;
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.07);
      transition: all 0.3s ease;
    }
    .section-title {
      font-size: 20px;
      font-weight: 600;
      margin-top: 0;
      margin-bottom: 20px;
      color: #39d75b;
      text-align: center;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
    }
    /* Mood Tracker */
    .mood-buttons {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-bottom: 20px;
    }
    .mood-button {
      font-size: 32px;
      cursor: pointer;
      transition: transform 0.2s ease, opacity 0.2s ease;
      border: none;
      background: none;
      padding: 5px;
      opacity: 0.7;
    }
    .mood-button:hover {
      transform: scale(1.2);
      opacity: 1;
    }
    .mood-button.selected {
        transform: scale(1.1);
        opacity: 1;
    }
    .current-mood {
      text-align: center;
      font-size: 16px;
      font-weight: 500;
      color: #555;
      margin-top: 10px;
    }

    /* Daily Journal */
    .journal-area {
      width: 100%;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px;
      font-size: 16px;
      resize: vertical;
      min-height: 120px;
      box-sizing: border-box;
      margin-bottom: 15px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
     .journal-area:focus {
         border-color: #39d75b;
         box-shadow: 0 0 0 3px rgba(57, 215, 91, 0.2);
         outline: none;
     }
    .save-journal {
      padding: 10px 25px;
      background-color: #39d75b;
      border: none;
      border-radius: 30px;
      color: #fff;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.2s;
      display: block;
      margin-left: auto;
      margin-right: auto;
      font-weight: 500;
    }
    .save-journal:hover {
      background-color: #2ebf51;
      transform: translateY(-2px);
    }

    /* Quote Section */
    .quote-display {
      text-align: center;
      font-size: 17px;
      font-style: italic;
      margin-bottom: 10px;
      min-height: 40px;
      line-height: 1.5;
      color: #555;
    }
    .quote-author {
      text-align: center;
      font-size: 14px;
      margin-top: 5px;
      color: #888;
    }
    .get-quote {
      padding: 10px 25px;
      background-color: #39d75b;
      border: none;
      border-radius: 30px;
      color: #fff;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.2s;
      display: block;
      margin-left: auto;
      margin-right: auto;
      font-weight: 500;
    }
    .get-quote:hover {
      background-color: #2ebf51;
       transform: translateY(-2px);
    }
    #personalMessage {
        margin-top: 20px;
    }

    /* --- Modal Styles --- */
    .modal {
      display: none;
      position: fixed;
      z-index: 1500;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.6);
      animation: fadeInModal 0.3s ease;
    }
    @keyframes fadeInModal {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    .modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 30px 35px;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      position: relative;
      animation: slideDown 0.4s ease-out;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .close-modal {
      position: absolute;
      right: 15px;
      top: 15px;
      font-size: 28px;
      font-weight: 300;
      color: #aaa;
      cursor: pointer;
      transition: all 0.2s ease;
      line-height: 1;
    }
    .close-modal:hover {
      transform: rotate(90deg) scale(1.1);
      color: #39d75b;
    }
    .modal-title {
      margin-top: 0;
      margin-bottom: 25px;
      font-size: 24px;
      font-weight: 600;
      text-align: center;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      font-size: 15px;
    }
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }
    .form-control:focus {
      border-color: #39d75b;
      box-shadow: 0 0 0 3px rgba(57, 215, 91, 0.2);
      outline: none;
    }
    .form-control[type="file"] {
        padding: 8px 15px;
    }
    .modal-content .primary-button { /* Modal submit button */
        display: block;
        width: 100%;
        margin-top: 10px;
        padding: 14px 20px;
        font-size: 16px;
    }

    /* --- Toast Notification --- */
    #toast {
      visibility: hidden;
      min-width: 250px;
      background-color: #333;
      color: #fff;
      text-align: center;
      border-radius: 8px;
      padding: 16px 25px;
      position: fixed;
      z-index: 2000;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%) translateY(20px);
      font-size: 16px;
      opacity: 0;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      transition: all 0.4s cubic-bezier(0.215, 0.610, 0.355, 1.000);
    }
    #toast.show {
      visibility: visible;
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    /* --- Responsive Adjustments --- */
    @media (max-width: 992px) {
        .sidebar-cell {
            width: 30%;
            min-width: 200px;
        }
        .main-cell {
            width: 70%;
        }
        .profile-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .profile-actions {
            width: 100%;
            justify-content: flex-start;
            margin-top: 15px;
        }
    }
     @media (max-width: 768px) {
         .layout-table, .layout-table tr {
             display: block;
         }
        .sidebar-cell, .main-cell {
            display: block;
            width: 100%;
            border-right: none;
            border-bottom: 1px solid #e0e0e0; /* Add separator */
        }
        .sidebar-cell {
            min-height: auto;
            padding-bottom: 20px;
        }
         body.dark-mode .sidebar-cell {
             border-bottom-color: #333;
         }
        .content-area {
            height: auto;
            padding: 20px;
        }
        .header-bar {
            padding: 15px 20px;
            position: static; /* Unstick header */
        }
         .dark-mode-btn {
            top: 10px;
            right: 10px;
            padding: 8px 12px;
         }
        .profile-details {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .profile-picture {
            width: 100px;
            height: 100px;
        }
        .profile-name {
            font-size: 24px;
        }
        .modal-content {
            margin: 20% auto;
            padding: 25px;
        }
    }

  </style>
</head>
<body>
    <input type="checkbox" id="dark-mode-toggle">
    <label for="dark-mode-toggle" class="dark-mode-btn">
      <span id="dark-mode-icon">🌙</span>
    </label>

    <div id="toast"></div>

    <table class="layout-table">
      <tr>
        <td class="sidebar-cell">
          <h2>FitSync</h2>
          <ul>
            <li><button class="pill-button"><a href="../Home.html">📊 Dashboard</a></button></li>
            <li class="dropdown">
              <button class="pill-button">📅 Schedule ▼</button>
              <ul class="dropdown-menu">
                 <li><button class="pill-button"><a href="../workout.html">Workout Plan</a></button></li>
                <li><button class="pill-button"><a href="../Diet/diet.html">Meal Plan</a></button></li>
                <li><button class="pill-button"><a href="../schedule.html">Calendar</a></button></li>
              </ul>
            </li>
            <li><button class="pill-button"><a href="../Community/comm.html">💬 Community</a></button></li>
            <li class="dropdown"> <button class="pill-button active">⚙️ Settings ▼</button> <ul class="dropdown-menu">
                   <li><button class="pill-button active"><a href="profile.php">Profile</a></button></li>
                <li><button class="pill-button"><a href="privacy.html">Privacy</a></button></li>
              </ul>
            </li>
            <li><button class="pill-button"><a href="../logout.php">🔒 Logout</a></button></li>
          </ul>
        </td>

        <td class="main-cell">
          <div class="header-bar">
            <h1 class="page-title">Profile Settings</h1>
          </div>
          <div class="content-area">
            <div class="profile-container">
              <div class="profile-header">
                <div class="profile-details">
                  <div class="profile-picture-container">
                    <img src="<?php echo $profilePicDisplay; ?>" alt="Profile Picture" class="profile-picture" id="profileImage">
                    <div class="edit-picture" id="changePictureBtnTrigger">
                      <span>📷</span>
                    </div>
                  </div>
                  <div class="profile-info">
                     <h2 class="profile-name" id="profileName"><?php echo $profileNameDisplay; ?></h2>
                    <p class="profile-email" id="profileEmail"><?php echo $profileEmailDisplay; ?></p>
                    <div class="profile-date">
                      <span>📅</span> Member since <span id="memberSince"><?php echo htmlspecialchars($memberSince); ?></span>
                    </div>
                  </div>
                </div>
                <div class="profile-actions">
                  <button class="action-button primary-button" id="editProfileBtn">
                    <span>✏️</span> Edit Profile
                  </button>
                </div>
              </div>

              <div class="mood-tracker">
                <h2 class="section-title">How are you feeling today?</h2>
                <div class="mood-buttons">
                  <button class="mood-button" data-mood="Happy" title="Happy">😊</button>
                  <button class="mood-button" data-mood="Neutral" title="Neutral">😐</button>
                  <button class="mood-button" data-mood="Sad" title="Sad">😢</button>
                  <button class="mood-button" data-mood="Energetic" title="Energetic">⚡</button>
                  <button class="mood-button" data-mood="Tired" title="Tired">😴</button>
                </div>
                <div class="current-mood" id="currentMood">Select your current mood</div>
              </div>

              <div class="daily-journal">
                <h2 class="section-title">Daily Journal</h2>
                <textarea class="journal-area" id="journalText" placeholder="Write your thoughts, reflections, or notes for today..."></textarea>
                <button class="save-journal" id="saveJournalBtn">Save Journal</button>
              </div>

              <div class="quote-section">
                <h2 class="section-title">Inspiration Corner</h2>
                <div class="quote-display" id="quoteDisplay">Click below for an inspiring quote!</div>
                <div class="quote-author" id="quoteAuthor"></div>
                <button class="get-quote" id="getRandomQuoteBtn">Get Random Quote</button>
                <br><br>
                <input type="text" id="personalMessage" class="form-control" placeholder="Or enter your own personal mantra...">
                <button class="get-quote" style="margin-top:10px;" id="setPersonalMessageBtn">Set Personal Message</button>
              </div>
            </div>
          </div>
        </td>
      </tr>
    </table>

    <div id="profileModal" class="modal">
      <div class="modal-content">
        <span class="close-modal" id="closeModal">×</span>
        <h2 class="modal-title">Edit Profile</h2>
        <form id="editProfileForm" enctype="multipart/form-data">
          <div class="form-group">
            <label for="editName">Name:</label>
             <input type="text" id="editName" name="name" class="form-control" required value="<?php echo $profileNameDisplay; ?>">
          </div>
          <div class="form-group">
            <label for="editEmail">Email:</label>
             <input type="email" id="editEmail" name="email" class="form-control" required value="<?php echo $profileEmailDisplay; ?>">
          </div>
          <div class="form-group">
            <label for="editPicture">Change Profile Picture:</label>
            <input type="file" id="editPicture" name="profile_picture" class="form-control" accept="image/jpeg, image/png, image/gif">
            <small style="display: block; margin-top: 5px; color: #888;">Max 5MB. JPG, PNG, GIF accepted.</small>
             <img id="editPicturePreview" src="#" alt="New profile picture preview" style="max-width: 100px; max-height: 100px; margin-top: 10px; display: none; border-radius: 50%;"/>
          </div>
          <button type="submit" class="action-button primary-button">Save Changes</button>
        </form>
      </div>
    </div>

    <script>
    // Wrap most JS in DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {

        // --- Elements ---
        const body = document.body;
        const toast = document.getElementById('toast');
        const editProfileBtn = document.getElementById('editProfileBtn');
        const profileModal = document.getElementById('profileModal');
        const closeModal = document.getElementById('closeModal');
        const editProfileForm = document.getElementById('editProfileForm');
        const profileImage = document.getElementById('profileImage');
        const profileName = document.getElementById('profileName');
        const profileEmail = document.getElementById('profileEmail');
        const changePictureBtnTrigger = document.getElementById('changePictureBtnTrigger');
        const editPictureInput = document.getElementById('editPicture');
        const editPicturePreview = document.getElementById('editPicturePreview');
        const currentMoodDisplay = document.getElementById('currentMood');
        const moodButtons = document.querySelectorAll('.mood-button');
        const journalText = document.getElementById('journalText');
        const saveJournalBtn = document.getElementById('saveJournalBtn');
        const quoteDisplay = document.getElementById('quoteDisplay');
        const quoteAuthor = document.getElementById('quoteAuthor');
        const getRandomQuoteBtn = document.getElementById('getRandomQuoteBtn');
        const personalMessageInput = document.getElementById('personalMessage');
        const setPersonalMessageBtn = document.getElementById('setPersonalMessageBtn');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const darkModeIcon = document.getElementById('dark-mode-icon'); // Assumes span in label
        const dropdownToggles = document.querySelectorAll('.sidebar-cell .dropdown > .pill-button');

        // --- Utilities (Toast, Cookies) ---
        let toastTimeoutId = null;
        function showToast(message, duration = 3000) {
            if (!toast) return; toast.innerText = message; toast.classList.add('show');
            if (toastTimeoutId) clearTimeout(toastTimeoutId);
            toastTimeoutId = setTimeout(() => { toast.classList.remove('show'); toastTimeoutId = null; }, duration);
        }
        function setCookie(name, value, days) { let expires = ""; if (days) { let date = new Date(); date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000)); expires = "; expires=" + date.toUTCString(); } document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax"; }
        function getCookie(name) { let nameEQ = name + "="; let ca = document.cookie.split(';'); for(let i=0;i < ca.length;i++) { let c = ca[i]; while (c.charAt(0)==' ') c = c.substring(1,c.length); if (c.startsWith(nameEQ)) return c.substring(nameEQ.length,c.length); } return null; }

        // --- Dark Mode ---
        function applyDarkModePreference() {
            // Read preference from DB (already applied server-side via PHP class on body)
            // Update toggle state and icon based on body class
             const isDarkMode = body.classList.contains("dark-mode");
             if (darkModeToggle) darkModeToggle.checked = isDarkMode;
             if(darkModeIcon) darkModeIcon.textContent = isDarkMode ? : '🌙';
        }
        if (darkModeToggle) {
          darkModeToggle.addEventListener("change", function() {
             const isEnabled = this.checked;
             body.classList.toggle("dark-mode", isEnabled); // Update UI immediately
             if(darkModeIcon) darkModeIcon.textContent = isEnabled ? : '🌙';
             setCookie("darkMode", isEnabled ? "true" : "false", 30); // Update cookie (optional fallback)

             // Save preference to backend
             fetch('../api/preference_handler.php', { // Path relative to Settings folder
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({ darkMode: isEnabled })
             })
             .then(response => response.json())
             .then(data => {
                 if (!data.success) {
                     showToast(data.message || 'Failed to save dark mode preference.');
                     // Optional: Revert UI change if save fails
                     // body.classList.toggle("dark-mode", !isEnabled);
                     // if(darkModeIcon) darkModeIcon.textContent = !isEnabled ? '☀️' : '🌙';
                     // darkModeToggle.checked = !isEnabled;
                 } else {
                    // Optionally show success toast, but might be too noisy
                    // showToast(data.message);
                 }
             })
             .catch(error => {
                 console.error('Error saving dark mode preference:', error);
                 showToast('Error saving preference.');
                  // Optional: Revert UI change on network error
             });
          });
        }
        applyDarkModePreference(); // Sync toggle/icon on load

        // --- Sidebar Dropdowns ---
         if (dropdownToggles) {
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    if (e.target === toggle || !e.target.closest('a')) {
                         let parentAnchor = e.target.closest('a');
                         if(parentAnchor && parentAnchor.parentElement === toggle){ return; }
                         e.preventDefault();
                        const parentLi = toggle.closest('li.dropdown');
                        if (parentLi) parentLi.classList.toggle('open');
                    }
                });
            });
        }
         // Ensure active dropdown stays open (handled by PHP adding 'open' class)


        // --- Profile Edit Modal & Form Submission ---
        // (Keep the existing JS logic for opening/closing the modal,
        // file input triggering, image preview, and form submission via Fetch
        // as provided in the previous step - it correctly POSTs to profile.php)
        // --- Profile Edit Modal ---
        if (editProfileBtn && profileModal && closeModal) { /* ... keep open/close logic ... */ editProfileBtn.addEventListener('click', () => { document.getElementById('editName').value = profileName ? profileName.textContent : ''; document.getElementById('editEmail').value = profileEmail ? profileEmail.textContent : ''; if(editPicturePreview) editPicturePreview.style.display = 'none'; if(editPictureInput) editPictureInput.value = ''; profileModal.style.display = 'block'; }); closeModal.addEventListener('click', () => { profileModal.style.display = 'none'; }); window.addEventListener('click', (event) => { if (event.target === profileModal) profileModal.style.display = 'none'; }); }
        if (changePictureBtnTrigger && editPictureInput) { changePictureBtnTrigger.addEventListener('click', () => { editPictureInput.click(); }); }
        if (editPictureInput && editPicturePreview) { editPictureInput.addEventListener('change', function() { const file = this.files[0]; if (file) { const validTypes = ['image/jpeg', 'image/png', 'image/gif']; if (!validTypes.includes(file.type) || file.size > 5 * 1024 * 1024) { showToast('Invalid file: JPG/PNG/GIF < 5MB.'); this.value = ''; editPicturePreview.style.display = 'none'; return; } const reader = new FileReader(); reader.onload = (e) => { editPicturePreview.src = e.target.result; editPicturePreview.style.display = 'block'; }; reader.readAsDataURL(file); } else { editPicturePreview.style.display = 'none'; } }); }
        // --- Submit Profile Form via Fetch ---
        if (editProfileForm) { editProfileForm.addEventListener('submit', function(event) { /* ... keep exact fetch POST logic to profile.php ... */ event.preventDefault(); const formData = new FormData(this); const submitButton = this.querySelector('button[type="submit"]'); const originalButtonText = submitButton.innerHTML; submitButton.innerHTML = 'Saving...'; submitButton.disabled = true; fetch('profile.php', { method: 'POST', body: formData }).then(response => response.json().then(data => ({ ok: response.ok, status: response.status, jsonData: data })).catch(err => { console.error("JSON Parsing Error:", err); throw new Error("Server returned non-JSON response."); }) ).then(({ ok, status, jsonData }) => { if (!ok) { throw new Error(jsonData.message || `HTTP error! Status: ${status}`); } if (jsonData.success) { const newName = formData.get('name'); const newEmail = formData.get('email'); if(profileName) profileName.textContent = newName; if(profileEmail) profileEmail.textContent = newEmail; if (jsonData.profile_pic && profileImage) { profileImage.src = jsonData.profile_pic + '?t=' + new Date().getTime(); } showToast(jsonData.message || "Profile updated!"); if (profileModal) profileModal.style.display = 'none'; } else { showToast(jsonData.message || "Failed to update profile."); } }).catch(error => { console.error('Error updating profile:', error); showToast(`Error: ${error.message}`); }).finally(() => { if (submitButton) { submitButton.innerHTML = originalButtonText; submitButton.disabled = false; } }); }); }


        // --- Mood Tracker (Uses API) ---
        if (moodButtons.length > 0 && currentMoodDisplay) {
            moodButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const mood = button.dataset.mood;

                    // Update UI immediately
                    moodButtons.forEach(btn => btn.classList.remove('selected'));
                    button.classList.add('selected');
                    currentMoodDisplay.innerHTML = `Saving mood: <strong>${mood}</strong>...`;

                    // Send to backend
                    fetch('../api/mood_handler.php', { // Path relative to Settings folder
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ mood: mood })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(`Mood saved as ${mood}`);
                            currentMoodDisplay.innerHTML = `Current Mood: <strong>${mood}</strong>`;
                        } else {
                            showToast(data.message || 'Failed to save mood.');
                            // Optional: Revert UI selection on failure
                            button.classList.remove('selected');
                            // Try fetching last saved mood again or display default
                            currentMoodDisplay.textContent = 'Select your current mood';
                        }
                    })
                    .catch(error => {
                         console.error('Error saving mood:', error);
                         showToast('Error saving mood.');
                         button.classList.remove('selected');
                         currentMoodDisplay.textContent = 'Select your current mood';
                    });
                });
            });
            // Initial mood is set by PHP
        }

        // --- Daily Journal (Uses API) ---
        if (saveJournalBtn && journalText) {
            saveJournalBtn.addEventListener('click', () => {
                const content = journalText.value; // Send trimmed or full based on preference
                const originalButtonText = saveJournalBtn.textContent;
                saveJournalBtn.textContent = 'Saving...';
                saveJournalBtn.disabled = true;

                fetch('../api/journal_handler.php', { // Path relative to Settings folder
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content: content })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || "Journal entry saved!");
                    } else {
                        showToast(data.message || "Failed to save journal entry.");
                    }
                })
                .catch(error => {
                     console.error('Error saving journal:', error);
                     showToast("Error saving journal entry.");
                })
                .finally(() => {
                     saveJournalBtn.textContent = originalButtonText;
                     saveJournalBtn.disabled = false;
                 });
            });
             // Initial journal text is set by PHP
        }

        // --- Inspiration Corner (Local Quotes + API for Save) ---
        const localQuotes = [
             { content: "The only bad workout is the one that didn't happen.", author: "Unknown" },
             { content: "Believe you can and you're halfway there.", author: "Theodore Roosevelt" },
             { content: "Strive for progress, not perfection.", author: "Unknown" },
             { content: "The body achieves what the mind believes.", author: "Napoleon Hill" },
             { content: "Push yourself because no one else is going to do it for you.", author: "Unknown" },
             { content: "Success usually comes to those who are too busy to be looking for it.", author: "Henry David Thoreau" },
             { content: "Don’t watch the clock; do what it does. Keep going.", author: "Sam Levenson" },
             { content: "Your health is an investment, not an expense.", author: "Unknown" },
             { content: "The pain you feel today will be the strength you feel tomorrow.", author: "Unknown" },
             { content: "It’s going to be a journey. It’s not a sprint to get in shape.", author: "Kerri Walsh Jennings" }
        ];
        let currentQuoteIndex = -1; // Track displayed quote index

        function displayQuote(quoteData) {
            if(!quoteDisplay || !quoteAuthor) return;
            quoteDisplay.textContent = `"${quoteData.content}"`;
            quoteAuthor.textContent = `— ${quoteData.author}`;
        }

        function saveInspiration(type, content) {
             fetch('../api/inspiration_handler.php', { // Path relative to Settings folder
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: type, content: content })
             })
             .then(r => r.json())
             .then(d => {
                 if (!d.success) { showToast(d.message || 'Failed to save inspiration.'); }
                 // Optionally show success: else { showToast(d.message); }
             })
             .catch(e => { console.error('Save Inspiration Error:', e); showToast('Error saving preference.'); });
        }

        if (getRandomQuoteBtn) {
            getRandomQuoteBtn.addEventListener('click', () => {
                // Simple random selection, avoiding immediate repeat
                let randomIndex;
                do {
                    randomIndex = Math.floor(Math.random() * localQuotes.length);
                } while (localQuotes.length > 1 && randomIndex === currentQuoteIndex);

                currentQuoteIndex = randomIndex;
                const selectedQuote = localQuotes[currentQuoteIndex];
                displayQuote(selectedQuote);
                personalMessageInput.value = ''; // Clear personal message input
                // Save the chosen quote to backend
                saveInspiration('quote', `${selectedQuote.content} // ${selectedQuote.author}`);
            });
        }

        if (setPersonalMessageBtn && personalMessageInput) {
            setPersonalMessageBtn.addEventListener('click', () => {
                const message = personalMessageInput.value.trim();
                if (message === "") { showToast("Please enter a message."); return; }

                quoteDisplay.textContent = `"${message}"`;
                quoteAuthor.textContent = "— You";
                showToast("Personal message set!");
                // Save personal message to backend
                saveInspiration('personal', message);
            });
        }
        // Initial quote/message display is handled by PHP

        // --- Back/Forward Cache Handling ---
        window.onpageshow = function(event) { if (event.persisted) { window.location.reload(); } };

    }); // End DOMContentLoaded
</script>

<script>
        // Basic dark mode toggle functionality
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        darkModeToggle.addEventListener('change', function() {
            if(this.checked) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        });

        
    </script>

    <script>
       // ======================================================
        // === GENERAL UTILITIES (Toast, Cookies, Dark Mode) ===
        // ======================================================
        // (Keep the Toast, Cookie, Dark Mode functions as previously defined)
         // Toast Notification Function
         const toast = document.getElementById('toast');
        let toastTimeoutId = null;
        function showToast(message) {
          if (!toast) return;
          toast.innerText = message;
          toast.classList.add('show');
          if (toastTimeoutId) clearTimeout(toastTimeoutId);
          toastTimeoutId = setTimeout(() => {
            toast.classList.remove('show');
            toastTimeoutId = null;
          }, 3000);
        }

        // Cookie and Dark Mode Management (IIFE)
        (function() {
          function setCookie(name, value, days) { /* ... same as before ... */
            let expires = ""; if (days) { let date = new Date(); date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000)); expires = "; expires=" + date.toUTCString(); } document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax"; }
          function getCookie(name) { /* ... same as before ... */
            let nameEQ = name + "="; let cookiesArray = document.cookie.split(';'); for (let i = 0; i < cookiesArray.length; i++) { let c = cookiesArray[i].trim(); if (c.indexOf(nameEQ) === 0) { return c.substring(nameEQ.length, c.length); } } return null; }
          function applyDarkMode() { /* ... same as before ... */
             let darkModeEnabled = getCookie("darkMode"); let bodyClassList = document.body.classList; let toggle = document.getElementById("dark-mode-toggle"); if (darkModeEnabled === "true") { bodyClassList.add("dark-mode"); if (toggle) toggle.checked = true; } else { bodyClassList.remove("dark-mode"); if (toggle) toggle.checked = false; } }
          var darkModeToggle = document.getElementById("dark-mode-toggle"); if (darkModeToggle) { darkModeToggle.addEventListener("change", function() { if (this.checked) { document.body.classList.add("dark-mode"); setCookie("darkMode", "true", 30); } else { document.body.classList.remove("dark-mode"); setCookie("darkMode", "false", 30); } }); }
          applyDarkMode(); // Apply on initial script execution after DOM load
          window.addEventListener("pageshow", function(event) { if (event.persisted) { applyDarkMode(); } });
        })();

        // --- Initial Load Actions ---
        applyDarkModePreference();

        // Add fallback text if elements might not load data immediately
        if(profileName && !profileName.textContent) profileName.textContent = "User Name";
        if(profileEmail && !profileEmail.textContent) profileEmail.textContent = "user@example.com";
        const memberSinceEl = document.getElementById('memberSince');
        if(memberSinceEl && !memberSinceEl.textContent) memberSinceEl.textContent = "N/A";

        // Logout link functionality (if needed, though href usually handles it)
        // const logoutLink = document.querySelector('a[href="../logout.php"]');
        // if(logoutLink) { ... }

        // Reload page if navigated back to via history (useful if state changes aren't dynamic)
        window.onpageshow = function(event) {
          if (event.persisted) {
            window.location.reload();
          }
        };

      // End DOMContentLoaded
    </script>
  </body>
</html>