<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require '../includes/pdo.php';

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  echo json_encode(["status" => "error", "message" => "DB 連線失敗: " . $e->getMessage()]);
  exit;
}

// 前端：只列出啟用檔案
if (($_GET['action'] ?? '') === 'listActive') {
  $sql = "SELECT file_ID,file_name,file_url 
          FROM file 
          WHERE file_status=1 
          ORDER BY is_top DESC,file_updated_d DESC";
  echo json_encode($pdo->query($sql)->fetchAll());
  exit;
}

// 後台：GET 全部檔案
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $sql = "SELECT file_ID,file_name,file_url,is_top,file_status,file_updated_d
          FROM file ORDER BY is_top DESC,file_updated_d DESC";
  echo json_encode($pdo->query($sql)->fetchAll());
  exit;
}

// 後台POST 更新/上傳
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 🔹 更新狀態 (JSON)
  if (stripos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['action'] ?? '') === 'update') {
      $field = $data['field'];
      $value = (int) $data['value'];
      $id = (int) $data['file_ID'];

      if (!in_array($field, ['is_top', 'file_status'])) {
        echo json_encode(["status" => "error", "message" => "欄位不合法"]);
        exit;
      }

      $pdo->prepare("UPDATE file SET {$field}=?,file_updated_d=NOW() WHERE file_ID=?")
        ->execute([$value, $id]);
      $row = $pdo->query("SELECT * FROM file WHERE file_ID={$id}")->fetch();
      echo json_encode(["status" => "success", "data" => $row]);
      exit;
    }
  }

  // 上傳新檔案
  if (!empty($_FILES['file']) && isset($_POST['file_name'])) {
    $fileName = trim($_POST['file_name']);

    // 檢查檔名是否重複
    $check = $pdo->prepare("SELECT COUNT(*) FROM file WHERE file_name = ?");
    $check->execute([$fileName]);
    if ($check->fetchColumn() > 0) {
      echo json_encode(["status" => "error", "message" => "已有同樣檔名，請更換"]);
      exit;
    }

    $dir = __DIR__ . "../uploads/templates/";
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }

    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $new = "A" . time() . "." . $ext;
    $path = $dir . $new;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
      $url = "../uploads/templates/" . $new;
      $pdo->prepare("INSERT INTO file(file_name,file_url,file_updated_d,file_status,is_top) VALUES(?,?,NOW(),1,0)")
        ->execute([$fileName, $url]);
      echo json_encode(["status" => "success", "file_ID" => $pdo->lastInsertId()]);
    } else {
      echo json_encode(["status" => "error", "message" => "檔案上傳失敗"]);
    }
    exit;
  }

  echo json_encode(["status" => "error", "message" => "無效的動作"]);
  exit;
}

echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
