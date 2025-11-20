<?php
// pages/admin_updateuser.php
session_start();
require_once '../includes/pdo.php';

// 檢查權限（主任 role_ID = 1 和 科辦 role_ID = 2）
$role_ID = $_SESSION['role_ID'] ?? null;
if (!isset($role_ID) || !in_array($role_ID, [1, 2])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => '無權限訪問']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

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
  $cohort_ID= isset($_POST['cohort_id'])  ? intval($_POST['cohort_id'])  : null;
  $grade    = isset($_POST['grade'])      ? intval($_POST['grade'])     : null;
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
    // 確保檔名沒有空格和特殊字元
    $safeUid = preg_replace('/[^A-Za-z0-9_\-]/', '', $u_ID);
    $new_img = 'u_img_' . $safeUid . '_' . time() . '.' . $ext;
    $destDir = dirname(__DIR__) . '/headshot';
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
  // 注意：userdata 表中沒有 class_ID 欄位，班級通過 enrollmentdata 表管理（見下方處理）
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
    // 先檢查該用戶是否已經有這個 role_ID 的記錄
    $stmt = $conn->prepare("SELECT COUNT(*) FROM userrolesdata WHERE ur_u_ID = ? AND role_ID = ?");
    $stmt->execute([$u_ID, $role_ID]);
    $roleExists = $stmt->fetchColumn() > 0;

    if ($roleExists) {
      // 如果該角色記錄已存在，只更新狀態為啟用
      $stmt = $conn->prepare("UPDATE userrolesdata SET user_role_status = 1 WHERE ur_u_ID = ? AND role_ID = ?");
      $stmt->execute([$u_ID, $role_ID]);
      
      // 將該用戶的其他角色設為停用（確保只有一個啟用角色）
      $stmt = $conn->prepare("UPDATE userrolesdata SET user_role_status = 0 WHERE ur_u_ID = ? AND role_ID != ?");
      $stmt->execute([$u_ID, $role_ID]);
    } else {
      // 如果該角色記錄不存在，先將該用戶所有角色設為停用
      $stmt = $conn->prepare("UPDATE userrolesdata SET user_role_status = 0 WHERE ur_u_ID = ?");
      $stmt->execute([$u_ID]);
      
      // 然後插入新角色關聯
      $stmt = $conn->prepare("INSERT INTO userrolesdata (ur_u_ID, role_ID, user_role_status) VALUES (?,?,1)");
      $stmt->execute([$u_ID, $role_ID]);
    }
  }
  
  // 學籍關聯處理（班級、學級、年級）
  // 先查找使用者是否有有效的 enrollment 記錄
  $stmt = $conn->prepare("SELECT enroll_ID, cohort_ID, class_ID, enroll_grade FROM enrollmentdata WHERE enroll_u_ID = ? AND enroll_status = 1 ORDER BY enroll_created_d DESC LIMIT 1");
  $stmt->execute([$u_ID]);
  $enroll = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // 確定要使用的 cohort_ID（優先使用表單提交的值，否則使用現有值，最後使用預設值）
  if ($cohort_ID !== null && $cohort_ID > 0) {
    $final_cohort_ID = $cohort_ID;
  } elseif ($enroll && $enroll['cohort_ID']) {
    $final_cohort_ID = $enroll['cohort_ID'];
  } else {
    // 獲取當前啟用的 cohort（或最新的 cohort）
    $cohortStmt = $conn->query("SELECT cohort_ID FROM cohortdata WHERE cohort_status = 1 ORDER BY cohort_ID DESC LIMIT 1");
    $cohort = $cohortStmt->fetch(PDO::FETCH_ASSOC);
    $final_cohort_ID = $cohort ? $cohort['cohort_ID'] : 1; // 預設使用第一個 cohort
  }
  
  if ($enroll) {
    // 更新現有學籍記錄
    $updateFields = [];
    $updateParams = [];
    
    if ($c_ID !== null) {
      $updateFields[] = "class_ID = ?";
      $updateParams[] = $c_ID > 0 ? $c_ID : null;
    }
    
    if ($cohort_ID !== null && $cohort_ID > 0) {
      $updateFields[] = "cohort_ID = ?";
      $updateParams[] = $cohort_ID;
    }
    
    if ($grade !== null) {
      $updateFields[] = "enroll_grade = ?";
      $updateParams[] = $grade > 0 ? $grade : null;
    }
    
    if (!empty($updateFields)) {
      $updateParams[] = $enroll['enroll_ID'];
      $stmt = $conn->prepare("UPDATE enrollmentdata SET " . implode(', ', $updateFields) . " WHERE enroll_ID = ?");
      $stmt->execute($updateParams);
    }
  } else {
    // 如果沒有 enrollment 記錄，建立新記錄
    // 至少需要 cohort_ID 才能建立記錄
    if ($final_cohort_ID) {
      $stmt = $conn->prepare("INSERT INTO enrollmentdata (enroll_u_ID, cohort_ID, class_ID, enroll_grade, enroll_status, enroll_created_d) VALUES (?,?,?,?,1,NOW())");
      $stmt->execute([
        $u_ID, 
        $final_cohort_ID, 
        ($c_ID !== null && $c_ID > 0) ? $c_ID : null,
        ($grade !== null && $grade > 0) ? $grade : null
      ]);
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
