<?php
// Adjust the paths based on where you call this file from. 
// __DIR__ ensures it looks for the 'libs' folder relative to the 'includes' folder.
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer and Brevo SMTP
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody The HTML content of the email
 * @param string $fromName The sender's name (defaults to 'Canteen System')
 * @param array $embeddedImages Associative array of ['CID' => 'Absolute File Path']
 * @return bool True on success, False on failure
 */
function sendMail($toEmail, $toName, $subject, $htmlBody, $fromName = 'Canteen System', $embeddedImages = []) {
    $mail = new PHPMailer(true);

    try {
        // --- CHARACTER ENCODING FIX ---
        // Explicitly set UTF-8 to support Emojis (✅, ❌) and special symbols
        $mail->CharSet = 'UTF-8'; 
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com'; // Brevo SMTP Server
        $mail->SMTPAuth   = true;
        
        // --- BREVO CREDENTIALS ---
        $mail->Username   = 'use yours';  
        $mail->Password   = 'use yours'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587; // TCP port to connect to

        // Sender information
        $mail->setFrom('halloankit18@gmail.com', $fromName); 
        
        // Recipient
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        
        // Embed Inline Images if provided
        if (!empty($embeddedImages)) {
            foreach ($embeddedImages as $cid => $path) {
                if (file_exists($path)) {
                    $mail->addEmbeddedImage($path, $cid);
                }
            }
        }
        
        // Plain text fallback
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
