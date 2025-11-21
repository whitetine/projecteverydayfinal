<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once "../../includes/pdo.php";// 這裡會給你 $conn (PDO)

try {
    //讀取表單資料
    $file_ID     = $_POST['file_ID'] ?? '';
    $apply_user_input = $_POST['apply_user'] ?? '';
    $apply_other = $_POST['apply_other'] ?? '';
    $file        = $_FILES['apply_image'] ?? null;

    // 🔹 優先使用 session 中的 u_ID，確保是有效的用戶ID
    $apply_user = $_SESSION['u_ID'] ?? '';
    
    // 如果 session 沒有 u_ID，嘗試從 POST 取得
    if (empty($apply_user) && !empty($apply_user_input)) {
        // 檢查是否為有效的 u_ID（查詢 userdata 表）
        $checkStmt = $conn->prepare("SELECT u_ID FROM userdata WHERE u_ID = ? LIMIT 1");
        $checkStmt->execute([$apply_user_input]);
        $userRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $apply_user = $apply_user_input;
        } else {
            // 如果不是有效的ID，嘗試用名稱查詢
            $checkStmt = $conn->prepare("SELECT u_ID FROM userdata WHERE u_name = ? LIMIT 1");
            $checkStmt->execute([$apply_user_input]);
            $userRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $apply_user = $userRow['u_ID'];
            }
        }
    }

    if (empty($file_ID) || empty($apply_user) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "請完整填寫欄位並上傳圖檔，或請先登入"]);
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


 // 插入 docsubdata 表（根據資料表結構）
    $sql = "
        INSERT INTO docsubdata
          (doc_ID, dcsub_team_ID, dcsub_u_ID, dcsub_comment, dcsub_url, dcsub_sub_d, dc_approved_u_ID, dcsub_approved_d, dcsub_remark, dcsub_status)
        VALUES
          (?, NULL, ?, ?, ?, NOW(), NULL, NULL, NULL, 0)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$file_ID, $apply_user, $apply_other, $dbPath]);
    echo json_encode([
        "status"   => "success",
        "message"  => "申請已成功送出！",
        "apply_ID" => $conn->lastInsertId() // 方便前端使用
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => '伺服器錯誤：' . $e->getMessage()]);
}