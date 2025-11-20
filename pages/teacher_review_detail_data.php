<?php
session_start();
require 'pdo.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['u_ID'])) {
  echo json_encode(['success'=>false,'msg'=>'no login']);
  exit;
}

$uid      = $_SESSION['u_ID'];
$teamId   = (int)($_GET['team_ID'] ?? 0);
$periodId = (int)($_GET['period_ID'] ?? 0);

if ($teamId <= 0) {
  echo json_encode(['success'=>false,'msg'=>'team_ID error']);
  exit;
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- 僅限指導老師 ---------- */
$stRole = $conn->prepare("
  SELECT COUNT(*) FROM userrolesdata 
  WHERE u_ID=? AND role_ID=4 AND user_role_status=1
");
$stRole->execute([$uid]);
if (!$stRole->fetchColumn()) {
  echo json_encode(['success'=>false,'msg'=>'no_permission']);
  exit;
}

/* ---------- 是否屬於該組 ---------- */
$chk = $conn->prepare("
  SELECT COUNT(*)
  FROM teammember tm
  JOIN userrolesdata ur ON ur.u_ID=tm.u_ID
  WHERE tm.team_ID=:tid AND tm.u_ID=:uid AND ur.role_ID=4 AND ur.user_role_status=1
");
$chk->execute([':tid'=>$teamId, ':uid'=>$uid]);
if (!$chk->fetchColumn()) {
  echo json_encode(['success'=>false,'msg'=>'no_team']);
  exit;
}

/* ---------- 取得組名 ---------- */
$stName = $conn->prepare("
  SELECT COALESCE(team_project_name, CONCAT('Team ', :tid)) 
  FROM teamdata WHERE team_ID=:tid
");
$stName->execute([':tid'=>$teamId]);
$teamName = $stName->fetchColumn() ?: ("Team ".$teamId);

/* ---------- 取得期間 ---------- */
// 檢查是否有 pe_mode 和 pe_target_ID 欄位
$hasPeMode = false;
$hasPeTargetId = false;
try {
  $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_mode'");
  $hasPeMode = $checkStmt->rowCount() > 0;
  $checkStmt2 = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_target_ID'");
  $hasPeTargetId = $checkStmt2->rowCount() > 0;
} catch (Exception $e) {
  // 忽略錯誤
}

$periodFields = 'period_ID, period_title, period_start_d, period_end_d';
if ($hasPeMode) {
  $periodFields .= ', pe_mode';
}
if ($hasPeTargetId) {
  $periodFields .= ', pe_target_ID';
}

if ($periodId <= 0) {
  // 先嘗試從 perioddata 表查詢
  try {
    $p = $conn->query("
      SELECT $periodFields
      FROM perioddata WHERE pe_status=1
      ORDER BY period_start_d DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $p = null;
  }
  
  // 如果 perioddata 沒有資料，嘗試從 periodsdata 查詢
  if (!$p) {
    $p = $conn->query("
      SELECT period_ID, period_title, period_start_d, period_end_d
      FROM periodsdata WHERE is_active=1
      ORDER BY period_start_d DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
  }

  if (!$p) {
    try {
      $p = $conn->query("
        SELECT $periodFields
        FROM perioddata ORDER BY period_start_d DESC LIMIT 1
      ")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      $p = null;
    }
  }
  
  if (!$p) {
    $p = $conn->query("
      SELECT period_ID, period_title, period_start_d, period_end_d
      FROM periodsdata ORDER BY period_start_d DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
  }
} else {
  // 先嘗試從 perioddata 表查詢
  try {
    $stP = $conn->prepare("
      SELECT $periodFields
      FROM perioddata WHERE period_ID=?
    ");
    $stP->execute([$periodId]);
    $p = $stP->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $p = null;
  }
  
  // 如果 perioddata 沒有資料，嘗試從 periodsdata 查詢
  if (!$p) {
    $stP = $conn->prepare("
      SELECT period_ID, period_title, period_start_d, period_end_d
      FROM periodsdata WHERE period_ID=?
    ");
    $stP->execute([$periodId]);
    $p = $stP->fetch(PDO::FETCH_ASSOC);
  }
}

if (!$p) {
  echo json_encode(['success'=>false,'msg'=>'no_period']);
  exit;
}

$periodId    = (int)$p['period_ID'];
$periodTitle = $p['period_title'];
$periodRange = $p['period_start_d'].' ～ '.$p['period_end_d'];

// 解析互評模式
$peMode = ($hasPeMode && isset($p['pe_mode'])) ? $p['pe_mode'] : 'in';
$peTargetId = ($hasPeTargetId && isset($p['pe_target_ID'])) ? $p['pe_target_ID'] : 'ALL';

// 解析 pe_target_ID
function parseTeamTarget($raw) {
  $result = [
    'assign' => [],
    'receive' => [],
    'is_all' => false
  ];
  if (!$raw || strtoupper(trim($raw)) === 'ALL') {
    $result['is_all'] = true;
    return $result;
  }
  $trimmed = trim((string)$raw);
  if ($trimmed !== '' && $trimmed[0] === '{') {
    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      $result['assign'] = array_values(array_filter(array_map('strval', $decoded['assign'] ?? [])));
      $result['receive'] = array_values(array_filter(array_map('strval', $decoded['receive'] ?? [])));
      return $result;
    }
  }
  $assignList = array_filter(array_map('trim', explode(',', $raw)), function($v){ return $v !== ''; });
  $result['assign'] = array_values(array_map('strval', $assignList));
  return $result;
}

$targetInfo = parseTeamTarget($peTargetId);
$assignTeamIds = array_map('intval', $targetInfo['assign']);
$receiveTeamIds = array_map('intval', $targetInfo['receive']);

/* ---------- 取得學生名單 ---------- */
$stStudents = $conn->prepare("
  SELECT u.u_ID, u.u_name
  FROM teammember tm
  JOIN userdata u ON tm.u_ID = u.u_ID
  JOIN userrolesdata ur ON ur.u_ID = u.u_ID
  WHERE tm.team_ID=? AND ur.role_ID=6 AND ur.user_role_status=1
  ORDER BY u.u_ID
");
$stStudents->execute([$teamId]);
$students = $stStudents->fetchAll(PDO::FETCH_KEY_PAIR);
$studentIds = array_keys($students);
$N = count($students);

/* ---------- 取得評分紀錄 ---------- */
// 根據互評模式構建 SQL 查詢
if ($peMode === 'cross') {
  // 團隊間互評模式
  // 判斷當前團隊是評分團隊還是被評分團隊
  $isAssignTeam = $targetInfo['is_all'] || in_array($teamId, $assignTeamIds);
  $isReceiveTeam = $targetInfo['is_all'] || (empty($receiveTeamIds) && $targetInfo['is_all']) || in_array($teamId, $receiveTeamIds);
  
  // 構建查詢條件
  $conditions = [];
  $params = [];
  
  // 如果當前團隊是評分團隊（assign），查詢該團隊成員對其他團隊的評分
  if ($isAssignTeam) {
    if (!empty($receiveTeamIds)) {
      // 有指定被評分團隊
      $placeholders = implode(',', array_fill(0, count($receiveTeamIds), '?'));
      $conditions[] = "(tma.team_ID = ? AND tmb.team_ID IN ($placeholders))";
      $params = array_merge($params, [$teamId], $receiveTeamIds);
    } else {
      // 沒有指定被評分團隊，表示所有團隊都可以被評分
      $conditions[] = "tma.team_ID = ?";
      $params[] = $teamId;
    }
  }
  
  // 如果當前團隊是被評分團隊（receive），查詢其他團隊對該團隊成員的評分
  if ($isReceiveTeam) {
    if (!empty($assignTeamIds)) {
      // 有指定評分團隊
      $placeholders = implode(',', array_fill(0, count($assignTeamIds), '?'));
      $conditions[] = "(tma.team_ID IN ($placeholders) AND tmb.team_ID = ?)";
      $params = array_merge($params, $assignTeamIds, [$teamId]);
    } else {
      // 沒有指定評分團隊，表示所有團隊都可以評分
      $conditions[] = "tmb.team_ID = ?";
      $params[] = $teamId;
    }
  }
  
  if (empty($conditions)) {
    // 當前團隊不在評分或被評分列表中，返回空結果
    $rows = [];
  } else {
    $whereClause = implode(' OR ', $conditions);
    $params[] = $periodId; // period_ID 參數
    
    $sql = "
      SELECT pr.review_a_u_ID AS from_id,
             pr.review_b_u_ID AS to_id,
             pr.score,
             pr.review_comment,
             pr.review_created_d
      FROM peerreview pr
      JOIN teammember tma ON pr.review_a_u_ID = tma.u_ID
      JOIN teammember tmb ON pr.review_b_u_ID = tmb.u_ID
      WHERE pr.period_ID = ? AND ($whereClause)
      ORDER BY pr.review_created_d ASC, pr.review_ID ASC
    ";
    $st = $conn->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} else {
  // 團隊內互評模式（原有邏輯）
  $st = $conn->prepare("
    SELECT pr.review_a_u_ID AS from_id,
           pr.review_b_u_ID AS to_id,
           pr.score,
           pr.review_comment,
           pr.review_created_d
    FROM peerreview pr
    JOIN teammember tma ON pr.review_a_u_ID=tma.u_ID AND tma.team_ID=:tid
    JOIN teammember tmb ON pr.review_b_u_ID=tmb.u_ID AND tmb.team_ID=:tid
    WHERE pr.period_ID=:pid
    ORDER BY pr.review_created_d ASC, pr.review_ID ASC
  ");
  $st->execute([':tid'=>$teamId, ':pid'=>$periodId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- 準備矩陣 ---------- */
$score = []; $comment = [];
$didReview = []; $recvSum = []; $recvCnt = [];

foreach ($studentIds as $a){
  foreach ($studentIds as $b){
    $score[$a][$b] = null;
    $comment[$a][$b] = null;
  }
  $didReview[$a] = 0;
  $recvSum[$a] = 0;
  $recvCnt[$a] = 0;
}

foreach ($rows as $r){
  $a = $r['from_id'];
  $b = $r['to_id'];
  
  // 在團隊間互評模式下，只處理涉及當前團隊學生的評分
  // 如果 from_id 或 to_id 不在當前團隊學生列表中，跳過
  if (!isset($score[$a]) || !isset($score[$a][$b])) {
    // 如果 from_id 是當前團隊學生，但 to_id 不是，只統計評分次數
    if (in_array($a, $studentIds) && !in_array($b, $studentIds)) {
      $didReview[$a]++;
    }
    // 如果 to_id 是當前團隊學生，但 from_id 不是，只統計被評分
    if (in_array($b, $studentIds) && !in_array($a, $studentIds)) {
      $recvSum[$b] += (int)$r['score'];
      $recvCnt[$b]++;
    }
    continue;
  }

  $score[$a][$b] = (int)$r['score'];
  $comment[$a][$b] = (string)$r['review_comment'];

  $didReview[$a]++;
  $recvSum[$b] += (int)$r['score'];
  $recvCnt[$b]++;
}

$avg=[];
foreach ($studentIds as $sid){
  $avg[$sid] = $recvCnt[$sid] ? round($recvSum[$sid]/$recvCnt[$sid],2) : null;
}

$reviewersDistinct = 0;
foreach ($studentIds as $sid){
  if ($didReview[$sid] > 0) $reviewersDistinct++;
}

$isComplete = ($N > 0 && $reviewersDistinct === $N);

/* ==============================
   回傳 JSON
============================== */
echo json_encode([
  'success'=>true,
  'teamName'=>$teamName,
  'teamId'=>$teamId,
  'periodId'=>$periodId,
  'periodTitle'=>$periodTitle,
  'periodRange'=>$periodRange,
  'students'=>$students,
  'studentIds'=>$studentIds,
  'score'=>$score,
  'comment'=>$comment,
  'avg'=>$avg,
  'didReview'=>$didReview,
  'recvCnt'=>$recvCnt,
  'notReviewed'=>array_values(array_filter($studentIds, fn($x)=>$didReview[$x]==0)),
  'N'=>$N,
  'completed'=>$isComplete
]);
