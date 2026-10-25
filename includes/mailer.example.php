<?php
/**
 * Mailer Configuration Template (Brevo / SMTP via PHPMailer)
 * 
 * Instructions:
 * 1. Copy this file to 'includes/mailer.php'
 * 2. Update your Brevo SMTP username, SMTP password / API key, and verified sender email.
 * 3. Never commit 'includes/mailer.php' with real credentials to GitHub.
 */

// Adjust the paths based on where you call this file from.
// __DIR__ ensures it looks for the 'libs' folder relative to the 'includes' folder.
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer and Brevo SMTP (or any standard SMTP server)
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
        // --- CHARACTER ENCODING ---
        // Explicitly set UTF-8 to support Emojis (✅, ❌) and special symbols
        $mail->CharSet = 'UTF-8'; 
        
        // --- SERVER SETTINGS ---
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com'; // Brevo SMTP Host or your SMTP provider
        $mail->SMTPAuth   = true;
        
        // --- SMTP CREDENTIALS (REPLACE WITH YOUR OWN) ---
        $mail->Username   = 'YOUR_BREVO_SMTP_LOGIN';     // e.g. 'a598c8001@smtp-brevo.com'
        $mail->Password   = 'YOUR_BREVO_SMTP_KEY';       // e.g. 'xsmtpsib-xxxxxxxxxxxxxxxx'
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587; // Standard STARTTLS port (or 465 for SMTPS)

        // --- SENDER INFORMATION ---
        $mail->setFrom('your-verified-email@example.com', $fromName); 
        
        // --- RECIPIENT ---
        $mail->addAddress($toEmail, $toName);

        // --- CONTENT ---
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
        // Uncomment below for local debugging if needed:
        // error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
