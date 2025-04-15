<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php'; // Go up one level to find db.php

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = intval($_SESSION['user_id']);
$today = date('Y-m-d');

// Handle POST request (Save Mood)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true); // Read JSON data
    $mood = $input['mood'] ?? null;

    // Validate mood (must be one of the allowed values)
    $allowed_moods = ['Happy', 'Neutral', 'Sad', 'Energetic', 'Tired'];
    if (!$mood || !in_array($mood, $allowed_moods, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid mood value.']);
        exit;
    }

    try {
        // Insert or update mood for today
        $sql = "INSERT INTO mood_log (user_id, log_date, mood) VALUES (:user_id, :log_date, :mood)
                ON DUPLICATE KEY UPDATE mood = VALUES(mood), logged_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':log_date' => $today,
            ':mood' => $mood
        ]);

        echo json_encode(['success' => true, 'message' => 'Mood saved.']);

    } catch (PDOException $e) {
        error_log("Mood save error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error saving mood.']);
    }
    exit;
}

// Handle GET request (Fetch Today's Mood - Although often done in the main page PHP)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
     try {
        $sql = "SELECT mood FROM mood_log WHERE user_id = :user_id AND log_date = :log_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id, ':log_date' => $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'mood' => $result['mood'] ?? null]);

    } catch (PDOException $e) {
        error_log("Mood fetch error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error fetching mood.']);
    }
    exit;
}

// Invalid method
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
?>