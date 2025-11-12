
<?php
// pages/admin_updateuser.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../includes/pdo.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Invalid method');
  }

  $u_ID     = $_POST['u_ID'] ?? '';
  if ($u_ID === '') throw new Exception('缺少使用者ID');

  // 先查舊頭貼
  $stmt = $conn->prepare("SELECT u_img FROM userdata WHERE u_ID = ?");
  $stmt->execute([$u_ID]);
  $current = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$current) throw new Exception('找不到使用者');

  // 取表單值（與前端名稱對齊）
  $u_name   = trim($_POST['name'] ?? '');
  $u_gmail  = trim($_POST['gmail'] ?? '');
  $u_profile= trim($_POST['profile'] ?? '');
  $c_ID     = isset($_POST['class_id'])  ? intval($_POST['class_id'])  : null;
  $role_ID  = isset($_POST['role_id'])   ? intval($_POST['role_id'])   : null;
  $u_status = isset($_POST['status_id']) ? intval($_POST['status_id']) : null;
  $password = trim($_POST['password'] ?? '');
  $clear    = ($_POST['clear_avatar'] ?? '0') === '1';

  // 頭貼處理
  $new_img = null;
  if ($clear) {
    // 清除
    $new_img = null;
  } elseif (!empty($_FILES['avatar']['name'])) {
    // 上傳新檔
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
      throw new Exception('頭貼只接受 jpg / png / webp');
    }
    $new_img  = 'u_img_' . $u_ID . '_' . time() . '.' . $ext;
    $destDir  = dirname(__DIR__) . '/headshot';
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
    $destPath = $destDir . '/' . $new_img;
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
      throw new Exception('頭貼上傳失敗');
    }
  }

  // 組更新 SQL
  $sets = [];
  $params = [];

  if ($u_name !== '')         { $sets[]='u_name = ?';     $params[]=$u_name; }
  $sets[] = 'u_gmail = ?';      $params[] = $u_gmail;
  $sets[] = 'u_profile = ?';    $params[] = $u_profile;
  if ($c_ID !== null)         { $sets[]='c_ID = ?';       $params[]=$c_ID; }
  if ($u_status !== null)     { $sets[]='u_status = ?';   $params[]=$u_status; }
  if ($password !== '')       { $sets[]='u_password = ?'; $params[]=$password; } // 你原本就是明碼，維持不動
  if ($clear)                 { $sets[]='u_img = NULL'; }
  elseif ($new_img)           { $sets[]='u_img = ?';      $params[]=$new_img; }

  $conn->beginTransaction();

  if ($sets) {
    $sql = "UPDATE userdata SET ".implode(',', $sets)." WHERE u_ID = ?";
    $params[] = $u_ID;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
  }

  // 角色關聯（單一角色）
  if ($role_ID !== null) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM userrolesdata WHERE u_ID = ?");
    $stmt->execute([$u_ID]);
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
      $stmt = $conn->prepare("UPDATE userrolesdata SET role_ID = ?, user_role_status = 1 WHERE u_ID = ?");
      $stmt->execute([$role_ID, $u_ID]);
    } else {
      $stmt = $conn->prepare("INSERT INTO userrolesdata (u_ID, role_ID, user_role_status) VALUES (?,?,1)");
      $stmt->execute([$u_ID, $role_ID]);
    }
  }

  $conn->commit();

  // 刪除舊圖（若清除或替換）
  if (($clear || $new_img) && !empty($current['u_img'])) {
    @unlink(dirname(__DIR__).'/headshot/'.$current['u_img']);
  }

  echo json_encode(['ok'=>true, 'msg'=>'更新完成']);
} catch (Throwable $e) {
  if ($conn && $conn->inTransaction()) $conn->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
}
