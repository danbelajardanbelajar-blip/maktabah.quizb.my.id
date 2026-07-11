<?php
// Script untuk testing pengiriman email
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Pengiriman Email Maktabah</h1>";

// Load Helper
require_once __DIR__ . '/app/Helpers/MailHelper.php';

// Jika PHPMailer berhasil diload
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color:red;'>Error: Class PHPMailer tidak ditemukan. Path salah.</p>";
} else {
    echo "<p style='color:green;'>Sukses: Class PHPMailer berhasil dimuat.</p>";
}

// Coba kirim email
$toEmail = 'admin@maktabah.quizb.my.id';
$subject = 'Test Notifikasi Maktabah';
$messageHTML = 'Ini adalah email ujicoba dari server Maktabah As-Sunniyyah.';

echo "<p>Mencoba mengirim email ke: $toEmail</p>";

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    // Aktifkan debug output
    $mail->SMTPDebug = 3; 
    $mail->Debugoutput = 'html';

    // Konfigurasi persis seperti di MailHelper
    $mail->isSMTP();
    $mail->Host       = 'maktabah.quizb.my.id'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'admin@maktabah.quizb.my.id'; 
    $mail->Password   = 'i3SPCi7r5998@kH'; 
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465; 

    // PENTING: Kadang sertifikat SSL server belum tersertifikasi sehingga gagal.
    // Opsi ini bisa dicoba untuk mem-bypass verifikasi SSL (jika diperlukan)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom('admin@maktabah.quizb.my.id', 'Maktabah Admin');
    $mail->addAddress($toEmail);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $messageHTML;

    $mail->send();
    echo "<p style='color:green;font-weight:bold;'>Email berhasil dikirim!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'><b>Pesan gagal dikirim. Error:</b> {$mail->ErrorInfo}</p>";
} catch (\Exception $e) {
    echo "<p style='color:red;'><b>Pesan gagal dikirim. Error:</b> {$e->getMessage()}</p>";
}
