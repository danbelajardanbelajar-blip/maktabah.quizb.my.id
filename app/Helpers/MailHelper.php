<?php

namespace App\Helpers;

use Exception;

// Kita asumsikan struktur ini untuk PHPMailer berdasarkan petunjuk
$vendorPath = dirname(__DIR__, 2) . '/vendor/phpmailer/phpmailer/src/';
if (file_exists($vendorPath . 'PHPMailer.php')) {
    require_once $vendorPath . 'Exception.php';
    require_once $vendorPath . 'PHPMailer.php';
    require_once $vendorPath . 'SMTP.php';
} else {
    // Fallback jika path di server berbeda
    $vendorPathPublic = $_SERVER['DOCUMENT_ROOT'] . '/vendor/phpmailer/phpmailer/src/';
    if (file_exists($vendorPathPublic . 'PHPMailer.php')) {
        require_once $vendorPathPublic . 'Exception.php';
        require_once $vendorPathPublic . 'PHPMailer.php';
        require_once $vendorPathPublic . 'SMTP.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;

class MailHelper {
    public static function sendNotification($toEmail, $subject, $messageHTML) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log('PHPMailer class not found.');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            // Konfigurasi SMTP (sesuaikan dengan server email sebenarnya)
            // Karena tidak ada file env, kita letakkan dasar di sini. 
            // Sebaiknya baca dari config.local.php jika ada.
            $mail->isSMTP();
            $mail->Host       = 'localhost'; // Ganti dengan host SMTP asli jika perlu
            $mail->SMTPAuth   = false;
            // $mail->Username   = 'admin@maktabah.quizb.my.id';
            // $mail->Password   = 'password';
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 25;

            $mail->setFrom('no-reply@maktabah.quizb.my.id', 'Maktabah Admin');
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $messageHTML;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
