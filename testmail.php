<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Include PHPMailer (if using Composer)

$mail = new PHPMailer(true);

try {
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mounikayamarthi7@gmail.com'; // Your Gmail address
    $mail->Password   = 'ftnf kesk juae fsaz'; // Use an App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender & Recipient
    $mail->setFrom('mounikayamarthi7@gmail.com', 'SpeechTherapy');
    $mail->addAddress('yamarthimounika@gmail.com', 'mounii'); // Change recipient email

    // Email Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from PHPMailer';
    $mail->Body    = '<h2>Hello!</h2><p>This is a test email sent using PHPMailer.</p>';
    $mail->AltBody = 'Hello! This is a test email sent using PHPMailer.'; // Plain text version

    // Send Email
    if ($mail->send()) {
        echo 'Test email sent successfully!';
    } else {
        echo 'Failed to send email.';
    }
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
?>
