<?php

require 'db.php'; // Adjust path if needed, assumes db.php is in the same directory

// --- IMPORTANT: XAMPP Mail Configuration ---
// For PHP's mail() function to work in XAMPP, you MUST configure
// php.ini and sendmail.ini correctly to use an SMTP server (like Gmail).
// Search online for "XAMPP configure php mail()" for detailed guides.
// It won't work without proper SMTP setup.
// ---

echo "Attempting to send email reminders...\n";

try {
    $conn = $pdo; // Use the PDO object from db.php

    // Select reminders that are due and haven't been marked as sent yet
    // Includes a small buffer (5 minutes past due) to catch reminders reliably
    $sql = "SELECT r.id, r.title, r.reminder_time, u.email
            FROM reminders r
            JOIN users u ON r.user_id = u.id
            WHERE r.reminder_time <= NOW()
              AND r.reminder_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) -- Optional: Avoid sending very old reminders
              AND r.is_sent = FALSE";

    $stmt = $conn->query($sql);
    $remindersFound = false;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $remindersFound = true;
        $to = $row['email'];
        $subject = "FitSync Reminder: " . htmlspecialchars($row['title']);
        $message = "Hi!\n\nThis is a reminder for: " . htmlspecialchars($row['title'])
                 . "\nScheduled for: " . date("Y-m-d h:i A", strtotime($row['reminder_time']))
                 . "\n\nStay hydrated and keep up the great work!\n\n- FitSync Team";
        // ** Replace with your actual sending email address **
        $headers = "From: no-reply@fitsync.example.com" . "\r\n" .
                   "Reply-To: no-reply@fitsync.example.com" . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        echo "Sending to: " . $to . " - Subject: " . $subject . "\n";

        // Attempt to send email
        if (mail($to, $subject, $message, $headers)) {
            echo "  Email sent successfully.\n";
            // Mark reminder as sent in the database
            $updateSql = "UPDATE reminders SET is_sent = TRUE WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt->execute([$row['id']])) {
                echo "  Reminder ID " . $row['id'] . " marked as sent.\n";
            } else {
                echo "  ERROR: Failed to mark reminder ID " . $row['id'] . " as sent.\n";
                error_log("Failed to update is_sent for reminder ID: " . $row['id']);
            }
        } else {
            echo "  ERROR: mail() function failed for " . $to . "\n";
            error_log("PHP mail() function failed for email: " . $to . " Reminder ID: " . $row['id']);
        }
    }

    if (!$remindersFound) {
        echo "No due reminders found to email.\n";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    error_log("Database error in email_notify.php: " . $e->getMessage());
}

echo "Email reminder script finished.\n";
?>