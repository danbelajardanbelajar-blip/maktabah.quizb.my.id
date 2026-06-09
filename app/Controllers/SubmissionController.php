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
    
        $uploadDir = __DIR__ . '/uploads/submissions/';
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
    
        echo json_encode(['success' => true, 'message' => 'Kiriman berhasil dikirim dan sedang menunggu review admin.']);
    }

}
