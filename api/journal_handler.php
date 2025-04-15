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
$today = date('Y-m-d');

// Handle POST request (Save Journal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    // Allow empty content to clear journal
    $content = isset($input['content']) ? trim($input['content']) : '';

    try {
        $sql = "INSERT INTO journal_entries (user_id, entry_date, content) VALUES (:user_id, :entry_date, :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':entry_date' => $today,
            ':content' => $content
        ]);

        echo json_encode(['success' => true, 'message' => 'Journal entry saved.']);

    } catch (PDOException $e) {
        error_log("Journal save error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error saving journal.']);
    }
    exit;
}

// Handle GET request (Fetch Today's Journal)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
     try {
        $sql = "SELECT content FROM journal_entries WHERE user_id = :user_id AND entry_date = :entry_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id, ':entry_date' => $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'content' => $result['content'] ?? '']); // Return empty string if no entry

    } catch (PDOException $e) {
        error_log("Journal fetch error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error fetching journal.']);
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