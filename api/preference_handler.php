<?php
session_start();
require '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $darkModeEnabled = isset($input['darkMode']) ? (bool)$input['darkMode'] : null;
    // Add meal preference logic here if needed

    if ($darkModeEnabled === null) {
         http_response_code(400);
         echo json_encode(['success' => false, 'message' => 'Dark mode preference not provided.']);
         exit;
    }

    try {
        $sql = "UPDATE users SET dark_mode_enabled = :darkMode WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':darkMode' => $darkModeEnabled,
            ':id' => $user_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Dark mode preference saved.']);

    } catch (PDOException $e) {
        error_log("Dark Mode save error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error saving preference.']);
    }
    exit;
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
?>