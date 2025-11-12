<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once "../../includes/pdo.php";// 這裡會給你 $conn (PDO)

try {
    //讀取表單資料
    $file_ID     = $_POST['file_ID'] ?? '';
    $apply_user  = $_POST['apply_user'] ?? ($_SESSION['u_ID'] ?? '');
    $apply_other = $_POST['apply_other'] ?? '';
    $file        = $_FILES['apply_image'] ?? null;

    if (empty($file_ID) || empty($apply_user) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "請完整填寫欄位並上傳圖檔"]);
        exit;
    }

    //檢查檔名
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowedExt)) {
        echo json_encode(["status" => "error", "message" => "僅允許上傳 PNG、JPG 圖檔"]);
        exit;
    }


    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png'];
    if (!in_array($mime, $allowedMime)) {
        echo json_encode(["status" => "error", "message" => "檔案格式不正確"]);
        exit;
    }


    // 建立上傳資料夾
    $uploadDir = dirname( __DIR__, 2) . '/uploads/images/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        echo json_encode(["status" => "error", "message" => "無法建立上傳資料夾"]);
        exit;
    }


    // 儲存檔案
    $newName = uniqid('img_') . '.' . $ext;
    $savePath = $uploadDir . $newName;
    $dbPath   = 'uploads/images/' . $newName; 

    if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        echo json_encode(["status" => "error", "message" => "檔案儲存失敗"]);
        exit;
    }


 $sql = "
        INSERT INTO applydata
          ( file_ID,  apply_a_u_ID, apply_other, apply_url, apply_status, apply_created_d)
        VALUES
          (?, ?, ?, ?, 1, NOW())
    ";
    $stmt = $conn->prepare($sql); // ★ 用 $conn，不是 $pdo
    $stmt->execute([$file_ID, $apply_user, $apply_other, $dbPath]);
    echo json_encode([
        "status"   => "success",
        "message"  => "申請已成功送出！",
        "apply_ID" => $conn->lastInsertId() // 方便前端使用
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => '伺服器錯誤：' . $e->getMessage()]);
}