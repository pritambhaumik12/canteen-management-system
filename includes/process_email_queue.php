<?php
// Prevent the script from stopping if the user closes their browser
ignore_user_abort(true);
set_time_limit(0);

// =========================================================================
// BACKGROUND EXECUTION: Close the HTTP connection immediately 
// so the browser doesn't wait or spin while emails send.
// =========================================================================
if (php_sapi_name() !== 'cli') {
    // If running in a web browser, close the connection immediately
    if (ob_get_level()) ob_end_clean();
    header("Connection: close");
    ob_start();
    echo "Processing started in background.";
    header("Content-Length: " . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();
    
    // Unlock the session so the user can continue navigating the site
    if (session_id()) session_write_close(); 
}
// =========================================================================

require_once 'db.php';
require_once 'mailer.php';

// Fetch up to 5 emails at a time.
$stmt = $pdo->prepare("SELECT * FROM email_queue ORDER BY id ASC LIMIT 5");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($emails)) {
    exit; // Silently exit if queue is empty
}

foreach ($emails as $email) {
    // Decode the embedded images array if any exist
    $embedded_images = !empty($email['embedded_images']) ? json_decode($email['embedded_images'], true) : [];
    
    // Attempt to send the email
    sendMail(
        $email['recipient_email'], 
        $email['recipient_name'], 
        $email['subject'], 
        $email['html_body'], 
        $email['sender_name'], 
        $embedded_images
    );

    // Completely remove the email from the database after attempting to send
    $delete = $pdo->prepare("DELETE FROM email_queue WHERE id = ?");
    $delete->execute([$email['id']]);
}
?>