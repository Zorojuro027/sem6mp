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
    $type = $input['type'] ?? null; // 'quote' or 'personal' or 'none'
    $content = $input['content'] ?? null;

    // Validate type
    $allowed_types = ['quote', 'personal', 'none'];
     if (!$type || !in_array($type, $allowed_types, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid inspiration type.']);
        exit;
    }
     // Content is required unless type is 'none'
    if ($type !== 'none' && ($content === null || trim($content) === '')) {
         http_response_code(400);
         echo json_encode(['success' => false, 'message' => 'Inspiration content cannot be empty.']);
         exit;
     }
     // If type is 'none', force content to null
     if ($type === 'none') {
         $content = null;
     }

    try {
        $sql = "UPDATE users SET inspirational_message_type = :type, inspirational_message_content = :content WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':content' => $content, // Store full quote or personal message
            ':id' => $user_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Inspiration preference saved.']);

    } catch (PDOException $e) {
        error_log("Inspiration save error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error saving inspiration.']);
    }
    exit;
}
// GET request (usually handled by profile.php itself)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
     try {
        $sql = "SELECT inspirational_message_type, inspirational_message_content FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'type' => $result['inspirational_message_type'] ?? 'none',
            'content' => $result['inspirational_message_content'] ?? null
        ]);

    } catch (PDOException $e) {
        error_log("Inspiration fetch error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error fetching inspiration.']);
    }
    exit;
}
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
?>