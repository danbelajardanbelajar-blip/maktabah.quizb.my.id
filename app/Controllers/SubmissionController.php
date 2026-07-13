<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\SearchHelper;
use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use PDO;
use Exception;

class SubmissionController {
    public function handleSubmitFile(): void {
        $user = AuthHelper::getSessionUser();
        $pdo  = Database::getConnection();
    
        $fileName    = trim($_POST['file_name']     ?? '');
        $fileType    = trim($_POST['file_type']     ?? '');
        $categoryId  = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description']   ?? '');
        $submitterEmail = trim($_POST['submitter_email'] ?? '');
    
        if ($fileName === '') {
            http_response_code(400); echo json_encode(['error' => 'Nama file wajib diisi.']); return;
        }
        if (!in_array($fileType, ['bahsul_masail', 'kitab'], true)) {
            http_response_code(400); echo json_encode(['error' => 'Tipe file tidak valid.']); return;
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['file']['error'] ?? -1;
            http_response_code(400); echo json_encode(['error' => "Upload gagal (kode: $errCode)."]); return;
        }
    
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);
    
        if (!in_array($mimeType, $allowedMime, true)) {
            http_response_code(400); echo json_encode(['error' => 'Format tidak didukung. Gunakan PDF atau Word.']); return;
        }
        if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
            http_response_code(400); echo json_encode(['error' => 'Ukuran file maksimal 20 MB.']); return;
        }
    
        // If user not logged in, require a valid email
        if (!$user) {
            if ($submitterEmail === '' || filter_var($submitterEmail, FILTER_VALIDATE_EMAIL) === false) {
                http_response_code(400); echo json_encode(['error' => 'Email pengirim wajib diisi dan harus valid.']); return;
            }
        }
    
        $categoryName = '';
        if ($categoryId > 0) {
            $cat = $pdo->prepare("SELECT name FROM categories WHERE id = :id");
            $cat->execute([':id' => $categoryId]);
            $row = $cat->fetch();
            $categoryName = $row['name'] ?? '';
        }
    
        $uploadDir = dirname(__DIR__, 2) . '/uploads/submissions/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    
        $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-z0-9_\-]/i', '_', $fileName);
        $saveAs   = uniqid('sub_', true) . '_' . $safeName . '.' . $ext;
        $destPath = $uploadDir . $saveAs;
    
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
            http_response_code(500); echo json_encode(['error' => 'Gagal menyimpan file di server.']); return;
        }
    
        $stmt = $pdo->prepare(
            "INSERT INTO file_submissions
             (user_id, user_name, user_email, file_name, file_type, category_id, category_name,
              file_url, file_size, mime_type, description)
             VALUES
             (:user_id, :user_name, :user_email, :file_name, :file_type, :category_id, :category_name,
              :file_url, :file_size, :mime_type, :description)"
        );
        $stmt->execute([
            ':user_id'       => $user['id'] ?? 0,
            ':user_name'     => $user['name'] ?? '',
            ':user_email'    => $user['email'] ?? $submitterEmail,
            ':file_name'     => $fileName,
            ':file_type'     => $fileType,
            ':category_id'   => $categoryId ?: null,
            ':category_name' => $categoryName,
            ':file_url'      => '/uploads/submissions/' . $saveAs,
            ':file_size'     => filesize($destPath),
            ':mime_type'     => $mimeType,
            ':description'   => $description,
        ]);
            $actualUserEmail = $user['email'] ?? $submitterEmail;
        $actualUserName  = $user['name'] ?? 'Tamu';

        // 1. Email Tanda Terima untuk User
        $userSubject = "Terima Kasih atas Kiriman File Anda";
        $userBody = "<h3>Halo $actualUserName,</h3>
            <p>Kami telah menerima file kiriman Anda dengan nama: <strong>$fileName</strong>.</p>
            <p>Admin kami akan segera meninjau file tersebut sebelum dipublikasikan ke perpustakaan. Anda akan menerima pemberitahuan lebih lanjut jika file telah disetujui, ditolak, atau jika ada pesan balasan dari Admin.</p>
            <p>Jazakumullah khairan,<br>Tim Admin Maktabah As-Sunniyyah</p>";
        \App\Helpers\MailHelper::sendNotification($actualUserEmail, $userSubject, $userBody);

        // 2. Email Notifikasi untuk Admin
        $adminSubject = "Notifikasi Kiriman File Baru";
        $adminBody = "<h3>Halo Admin,</h3>
            <p>Terdapat kiriman file baru yang menunggu untuk ditinjau:</p>
            <ul>
                <li><strong>Pengirim:</strong> $actualUserName ($actualUserEmail)</li>
                <li><strong>Nama File:</strong> $fileName</li>
                <li><strong>Deskripsi:</strong> $description</li>
            </ul>
            <p>Silakan login ke dashboard untuk melakukan peninjauan (Approve/Reject).</p>";
        \App\Helpers\MailHelper::sendNotification(['admin@maktabah.quizb.my.id', 'zenhkm@gmail.com'], $adminSubject, $adminBody);

        echo json_encode(['success' => true, 'message' => 'Kiriman berhasil dikirim dan sedang menunggu review admin.']);
    }

    public function handleSubmitRequest(): void {
        $user = AuthHelper::getSessionUser();
        $pdo = Database::getConnection();

        $email       = trim($_POST['user_email']         ?? '');
        $requestType = trim($_POST['request_type']       ?? '');
        $title       = trim($_POST['title']              ?? '');
        $authorOrCat = trim($_POST['author_or_category'] ?? '');
        $description = trim($_POST['description']        ?? '');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            http_response_code(400); echo json_encode(['error' => 'Email wajib diisi dan valid.']); return;
        }
        if ($title === '') {
            http_response_code(400); echo json_encode(['error' => 'Judul kitab / bahsul masail wajib diisi.']); return;
        }
        if (!in_array($requestType, ['bahsul_masail', 'kitab'], true)) {
            http_response_code(400); echo json_encode(['error' => 'Jenis request tidak valid.']); return;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO kitab_requests (user_id, user_email, request_type, title, author_or_category, description, status)
                 VALUES (:user_id, :email, :type, :title, :author, :desc, 'pending')"
            );
            $stmt->execute([
                ':user_id' => $user ? $user['id'] : null,
                ':email'   => $email,
                ':type'    => $requestType,
                ':title'   => $title,
                ':author'  => $authorOrCat,
                ':desc'    => $description,
            ]);

            $actualUserEmail = $email;
            $actualUserName  = $user['name'] ?? 'Tamu';

            // 1. Email Tanda Terima untuk User
            $userSubject = "Permintaan Kitab Telah Diterima";
            $userBody = "<h3>Halo $actualUserName,</h3>
                <p>Kami telah menerima permintaan kitab Anda: <strong>$title</strong>.</p>
                <p>Admin kami akan berusaha mencari kitab yang Anda minta. Jika telah ditemukan atau ada balasan, Anda akan segera dihubungi melalui email ini.</p>
                <p>Jazakumullah khairan,<br>Tim Admin Maktabah As-Sunniyyah</p>";
            \App\Helpers\MailHelper::sendNotification($actualUserEmail, $userSubject, $userBody);

            // 2. Email Notifikasi untuk Admin
            $adminSubject = "Notifikasi Request Kitab Baru";
            $adminBody = "<h3>Halo Admin,</h3>
                <p>Terdapat permintaan kitab baru dari pengguna:</p>
                <ul>
                    <li><strong>Pengirim:</strong> $actualUserName ($actualUserEmail)</li>
                    <li><strong>Judul/Pengarang:</strong> $title</li>
                    <li><strong>Deskripsi Tambahan:</strong> $description</li>
                </ul>
                <p>Silakan login ke dashboard untuk membalas request tersebut.</p>";
            \App\Helpers\MailHelper::sendNotification(['admin@maktabah.quizb.my.id', 'zenhkm@gmail.com'], $adminSubject, $adminBody);

            echo json_encode(['success' => true, 'message' => 'Request berhasil dikirim. Kami akan memprosesnya dan memberi tahu Anda via email jika sudah tersedia.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Terjadi kesalahan sistem saat menyimpan request.']);
        }
    }

    public function setupKitabRequests(): void {
        try {
            $pdo = Database::getConnection();
            $sql = file_get_contents(dirname(__DIR__, 2) . '/database/setup_kitab_requests.sql');
            $pdo->exec($sql);
            echo json_encode(['success' => true, 'message' => 'Table kitab_requests created successfully!']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function setupFeedback(): void {
        try {
            $pdo = Database::getConnection();
            $sql = file_get_contents(dirname(__DIR__, 2) . '/setup_feedback.sql');
            $pdo->exec($sql);
            echo json_encode(['success' => true, 'message' => 'Table feedbacks created successfully!']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function handleSubmitFeedback(): void {
        $user = AuthHelper::getSessionUser();
        $pdo = Database::getConnection();
        
        $email   = trim($_POST['email'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (!$email || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'Email dan isi feedback wajib diisi.']);
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Format email tidak valid.']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO feedbacks (user_id, email, content) VALUES (?, ?, ?)");
            $stmt->execute([$user ? $user['id'] : null, $email, $content]);

            $actualUserEmail = $email;
            $actualUserName  = $user['name'] ?? 'Tamu';

            // 1. Email Tanda Terima untuk User
            $userSubject = "Terima Kasih atas Feedback Anda";
            $userBody = "<h3>Halo $actualUserName,</h3>
                <p>Kami telah menerima pesan/feedback Anda untuk pengembangan layanan Maktabah As-Sunniyyah.</p>
                <p>Berikut salinan pesan Anda:</p>
                <blockquote><em>$content</em></blockquote>
                <p>Masukan Anda sangat berarti bagi kami. Jika memerlukan balasan, kami akan segera membalas email Anda.</p>
                <p>Jazakumullah khairan,<br>Tim Admin Maktabah As-Sunniyyah</p>";
            \App\Helpers\MailHelper::sendNotification($actualUserEmail, $userSubject, $userBody);

            // 2. Email Notifikasi untuk Admin
            $adminSubject = "Notifikasi Feedback Baru";
            $adminBody = "<h3>Halo Admin,</h3>
                <p>Terdapat pesan/feedback baru masuk:</p>
                <ul>
                    <li><strong>Pengirim:</strong> $actualUserName ($actualUserEmail)</li>
                </ul>
                <p><strong>Isi Pesan:</strong><br>$content</p>
                <p>Silakan login ke dashboard untuk membalas feedback tersebut.</p>";
            \App\Helpers\MailHelper::sendNotification(['admin@maktabah.quizb.my.id', 'zenhkm@gmail.com'], $adminSubject, $adminBody);

            echo json_encode(['success' => true, 'message' => 'Terima kasih! Feedback Anda telah berhasil dikirim.']);
        } catch (Exception $e) {
            error_log('Feedback Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Terjadi kesalahan saat menyimpan feedback.']);
        }
    }

    public function getMyActivities(): void {
        $user = AuthHelper::getSessionUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $pdo = Database::getConnection();
        $userId = $user['id'];

        // Get Submissions
        $stmtSub = $pdo->prepare("SELECT id, file_name as title, file_type as type, status, review_note as admin_reply, created_at FROM file_submissions WHERE user_id = ? ORDER BY created_at DESC");
        $stmtSub->execute([$userId]);
        $submissions = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

        // Get Requests
        $stmtReq = $pdo->prepare("SELECT id, title, request_type as type, status, admin_reply, created_at FROM kitab_requests WHERE user_id = ? ORDER BY created_at DESC");
        $stmtReq->execute([$userId]);
        $requests = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

        // Get Feedbacks
        $stmtFb = $pdo->prepare("SELECT id, content as title, 'feedback' as type, status, admin_reply, created_at FROM feedbacks WHERE user_id = ? ORDER BY created_at DESC");
        $stmtFb->execute([$userId]);
        $feedbacks = $stmtFb->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'submissions' => $submissions,
                'requests' => $requests,
                'feedbacks' => $feedbacks
            ]
        ]);
    }

    public function getMyNotifications(): void {
        $user = AuthHelper::getSessionUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $pdo = Database::getConnection();
        $userId = $user['id'];

        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $notifications]);
    }

    public function markNotificationRead(): void {
        $user = AuthHelper::getSessionUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID required']);
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);

        echo json_encode(['success' => true]);
    }

    public function handleUserReplyActivity(): void {
        $user = AuthHelper::getSessionUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        $id = $input['id'] ?? 0;
        $reply = trim($input['reply'] ?? '');

        if (!$type || !$id || !$reply) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data.']);
            return;
        }

        $pdo = Database::getConnection();
        $table = '';
        $column = '';

        if ($type === 'submissions') {
            $table = 'file_submissions';
            $column = 'review_note';
        } elseif ($type === 'requests') {
            $table = 'kitab_requests';
            $column = 'admin_reply';
        } elseif ($type === 'feedbacks') {
            $table = 'feedbacks';
            $column = 'admin_reply';
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type.']);
            return;
        }

        // Fetch existing reply to append to it
        $stmt = $pdo->prepare("SELECT $column FROM $table WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found or unauthorized.']);
            return;
        }

        $existing = $row[$column] ?? '';
        $userName = $user['name'];
        // Format append: if existing already has content, add a separator
        $newReply = $existing . "\n\n[$userName]:\n" . $reply;

        $updateStmt = $pdo->prepare("UPDATE $table SET $column = ? WHERE id = ?");
        $updateStmt->execute([$newReply, $id]);

        // Send email to admin
        $adminSubject = "Notifikasi Balasan User (" . ucfirst($type) . ")";
        $adminBody = "<h3>Halo Admin,</h3>
            <p>User <strong>$userName</strong> membalas pesan Anda terkait <strong>" . ucfirst($type) . " (ID: $id)</strong>.</p>
            <p><strong>Isi Balasan:</strong><br>$reply</p>
            <p>Silakan login ke dashboard untuk membalas kembali jika diperlukan.</p>";
        \App\Helpers\MailHelper::sendNotification(['admin@maktabah.quizb.my.id', 'zenhkm@gmail.com'], $adminSubject, $adminBody);

        echo json_encode(['success' => true]);
    }
}
