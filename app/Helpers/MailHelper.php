<?php

namespace App\Helpers;

use Exception;

// Mengecek beberapa kemungkinan letak folder PHPMailer di cPanel/Server
$possiblePaths = [
    dirname(__DIR__, 2) . '/vendor/phpmailer/phpmailer/src/',
    dirname(__DIR__, 2) . '/vendor/phpmailer/src/',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/phpmailer/phpmailer/src/',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/phpmailer/src/'
];

foreach ($possiblePaths as $path) {
    if (file_exists($path . 'PHPMailer.php')) {
        require_once $path . 'Exception.php';
        require_once $path . 'PHPMailer.php';
        require_once $path . 'SMTP.php';
        break;
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
            // KONFIGURASI SMTP
            // ==========================================
            $mail->isSMTP();
            $mail->Host       = 'mail.maktabah.quizb.my.id'; // GANTI dengan Host SMTP Anda (contoh: smtp.hostinger.com)
            $mail->SMTPAuth   = true;
            $mail->Username   = 'admin@maktabah.quizb.my.id'; // GANTI dengan alamat email Anda
            $mail->Password   = 'PASSWORD_EMAIL_ANDA'; // GANTI dengan password email Anda
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Gunakan ENCRYPTION_SMTPS untuk port 465, atau ENCRYPTION_STARTTLS untuk port 587
            $mail->Port       = 465; // GANTI dengan port SMTP Anda (465 atau 587)
            // ==========================================

            $mail->setFrom('admin@maktabah.quizb.my.id', 'Maktabah Admin');
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
