<?php
session_start();
// *** Include the database connection ***
require '../db.php'; // Make sure the path is correct relative to the api folder

header('Content-Type: application/json');

// --- Basic Security/Login Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = intval($_SESSION['user_id']); // Get user ID for DB saving

// --- Function to save message to DB ---
function saveChatMessage(PDO $pdo, int $userId, string $sender, string $message): bool {
    try {
        $sql = "INSERT INTO chat_messages (user_id, sender, message, timestamp) VALUES (:user_id, :sender, :message, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':sender'  => $sender, // 'user' or 'bot'
            ':message' => $message
        ]);
        return true;
    } catch (PDOException $e) {
        // Log the error but don't necessarily stop the bot response
        error_log("DB Error saving chat message for user $userId: " . $e->getMessage());
        return false;
    }
}

// --- Handle Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_message = trim($input['message'] ?? '');

    if (empty($user_message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Empty message received.']);
        exit;
    }

    // *** Step 1: Save User Message to DB ***
    if (!saveChatMessage($pdo, $user_id, 'user', $user_message)) {
         // Log failure but continue to get bot reply
         error_log("Failed to save user message for user $user_id, but continuing to get bot reply.");
    }


    // --- Prepare AI Request (Google Gemini) ---

    // ** IMPORTANT: Replace this with a secure way to get your key! **
    // Example: Using an environment variable (Recommended)
    $apiKey = getenv('GEMINI_API_KEY');
    // ** Fallback (REMOVE IN PRODUCTION - REPLACE WITH YOUR KEY ONLY FOR TESTING): **
    if (!$apiKey) {
         $apiKey = 'YOUR_GEMINI_API_KEY'; // <<<--- Replace or remove this line
    }

    // Check if the key is configured
    if (!$apiKey || $apiKey === 'YOUR_GEMINI_API_KEY') {
         http_response_code(500);
         error_log("Gemini API Key is not configured in the backend."); // Log the actual error
         echo json_encode(['success' => false, 'message' => 'AI Service configuration error. Please contact support.']); // User-friendly message
         exit;
    }

    // Choose the Gemini model (e.g., 'gemini-1.5-flash' or 'gemini-pro')
    $model = 'gemini-1.5-flash';
    $geminiApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    // Structure the payload for Gemini API
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $user_message]
                ]
            ]
        ]
        // Optional: Add safety settings or generation config if needed
        // 'safetySettings' => [...],
        // 'generationConfig' => [...]
    ];
    $postData = json_encode($payload);

    // --- Configure and Execute cURL ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $geminiApiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout in seconds
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment ONLY for local testing if SSL issues occur

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // --- Process Response ---
    if ($curl_error) {
        error_log("Chatbot cURL Error (Gemini): " . $curl_error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error contacting AI service (cURL).']);
    } elseif ($httpcode >= 400) {
         error_log("Chatbot API Error (Gemini): HTTP Status $httpcode, Response: $response");
         http_response_code(500);
         $errorData = json_decode($response, true);
         $apiErrorMsg = $errorData['error']['message'] ?? $errorData['error']['status'] ?? 'Unknown API Error';
         echo json_encode(['success' => false, 'message' => "AI service returned error ($httpcode): $apiErrorMsg"]);
    } else {
        $responseData = json_decode($response, true);
        $reply = null;

        // Extract the text response from the Gemini API structure
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $reply = $responseData['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($responseData['promptFeedback']['blockReason'])) {
             // Handle blocked responses
            $reason = $responseData['promptFeedback']['blockReason'];
            $reply = "My response was blocked due to: " . $reason . ". Let's try a different topic.";
             error_log("Gemini response blocked for user $user_id: " . $reason);
        }

        if ($reply !== null) { // Check if reply was successfully extracted or set to an error message
            $bot_reply = trim($reply);
            // *** Step 2: Save Bot Reply to DB ***
            saveChatMessage($pdo, $user_id, 'bot', $bot_reply); // Save bot reply

            echo json_encode(['success' => true, 'reply' => $bot_reply]);
        } else {
             error_log("Chatbot (Gemini) - Could not parse reply for user $user_id from API response: $response");
             http_response_code(500);
             // Provide a generic error to the user
             echo json_encode(['success' => false, 'message' => 'The AI service returned an unexpected response.']);
        }
    }
    exit; // Crucial exit after POST handling

} else {
    // Deny non-POST requests
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
?>