<?php
session_start();
require 'pdo.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['u_ID'])) {
    echo json_encode(['success' => false, 'msg' => '尚未登入']);
    exit;
}
$uid = $_SESSION['u_ID'];
// 確保 u_ID 是字串格式
$uid = (string)$uid;
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 調試：記錄當前使用者ID
error_log("當前使用者ID: '$uid' (類型: " . gettype($uid) . ")");

/* 驗證是否為啟用中的指導老師 */
$stRole = $conn->prepare("
    SELECT COUNT(*) FROM userrolesdata
    WHERE u_ID=? AND role_ID=4 AND user_role_status=1
");
$stRole->execute([$uid]);
if (!$stRole->fetchColumn()) {
    echo json_encode(['success' => false, 'msg' => '無權限']);
    exit;
}

/* 檢查是否有 pe_mode、pe_target_ID 和 pe_created_u_ID 欄位 */
$hasPeMode = false;
$hasPeTargetId = false;
$hasCreatedUserId = false;
try {
  $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_mode'");
  $hasPeMode = $checkStmt->rowCount() > 0;
  $checkStmt2 = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_target_ID'");
  $hasPeTargetId = $checkStmt2->rowCount() > 0;
  $checkStmt3 = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_created_u_ID'");
  $hasCreatedUserId = $checkStmt3->rowCount() > 0;
} catch (Exception $e) {
  // 忽略錯誤
}

/* 取得全部週次 - 優先使用 perioddata 表 */
$periodFields = 'period_ID, period_title, period_start_d, period_end_d';
$periodTable = 'perioddata';
$periodActiveField = 'pe_status';

// 檢查 perioddata 表是否存在
$usePerioddata = false;
try {
  $testStmt = $conn->query("SHOW TABLES LIKE 'perioddata'");
  if ($testStmt->rowCount() > 0) {
    $usePerioddata = true;
    if ($hasPeMode) {
      $periodFields .= ', pe_mode';
    }
    if ($hasPeTargetId) {
      $periodFields .= ', pe_target_ID';
    }
  }
} catch (Exception $e) {
  // 如果查詢失敗，使用 periodsdata
  $usePerioddata = false;
}

// 如果 perioddata 表不存在，使用 periodsdata 表
if (!$usePerioddata) {
  $periodTable = 'periodsdata';
  $periodActiveField = 'is_active';
}

// 構建查詢，根據使用者過濾
$whereClause = '';
$queryParams = [];

// 預設匹配的建立者
$matchedUserId = $uid;

// 如果使用 perioddata 表且有 pe_created_u_ID 欄位，根據使用者過濾（只顯示自己新增的時段）
if ($usePerioddata && $hasCreatedUserId && $uid) {
  // 確保 u_ID 是字串格式，因為 pe_created_u_ID 可能是字串
  $uid = (string)$uid;
  
  // 調試：先查詢看看是否有資料
  try {
    // 先查詢所有時段的 pe_created_u_ID 值
    $allUsers = $conn->query("SELECT DISTINCT pe_created_u_ID FROM $periodTable LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    error_log("調試 - 資料庫中所有建立者: " . implode(', ', $allUsers));
    error_log("調試 - 當前使用者ID: '$uid' (類型: " . gettype($uid) . ")");
    
    // 檢查是否有完全匹配
    if (in_array($uid, $allUsers, true)) {
      error_log("調試 - 找到完全匹配的使用者ID");
    } else {
      error_log("調試 - 警告：使用者ID '$uid' 不在資料庫建立者列表中");
      // 嘗試寬鬆匹配（去除空格、轉換類型）
      $found = false;
      foreach ($allUsers as $dbUser) {
        $dbUserStr = trim((string)$dbUser);
        $uidStr = trim((string)$uid);
        if ($uidStr === $dbUserStr) {
          error_log("調試 - 寬鬆匹配成功：'$uid' 匹配 '$dbUser'");
          $matchedUserId = $dbUser;
          $found = true;
          break;
        }
      }
      if (!$found) {
        error_log("調試 - 沒有找到匹配的使用者ID，將使用原始值 '$uid' 查詢");
      }
    }
    
    // 使用匹配的使用者ID查詢
    $testQuery = $conn->prepare("SELECT COUNT(*) FROM $periodTable WHERE pe_created_u_ID = ?");
    $testQuery->execute([$matchedUserId]);
    $testCount = $testQuery->fetchColumn();
    error_log("調試 - 查詢 $periodTable 表，使用者 '$matchedUserId' 建立的時段數量: $testCount");
    
    $whereClause = ' WHERE pe_created_u_ID = ?';
    $queryParams[] = $matchedUserId;
  } catch (Exception $e) {
    error_log("調試查詢失敗: " . $e->getMessage());
    // 如果調試查詢失敗，仍然使用原始邏輯
    $whereClause = ' WHERE pe_created_u_ID = ?';
    $queryParams[] = $uid;
  }
}

try {
  $sql = "
    SELECT $periodFields, $periodActiveField as is_active
    FROM $periodTable
    $whereClause
    ORDER BY period_start_d DESC
  ";
  
  // 調試：記錄查詢資訊
  error_log("查詢評分時段 - 表: $periodTable, WHERE: $whereClause, 使用者: $uid, SQL: $sql");
  
  if (!empty($queryParams)) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($queryParams);
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("查詢參數: " . implode(', ', $queryParams));
  } else {
    $periods = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
  
  // 調試：記錄查詢結果
  error_log("查詢結果數量: " . count($periods));
  if (count($periods) > 0) {
    error_log("第一個時段資料: " . json_encode($periods[0], JSON_UNESCAPED_UNICODE));
  }
  
} catch (Exception $e) {
  // 如果查詢失敗，返回錯誤
  error_log("查詢評分時段失敗: " . $e->getMessage());
  echo json_encode([
      'success' => false,
      'msg' => '查詢評分時段失敗：' . $e->getMessage(),
      'debug' => [
          'table' => $periodTable,
          'where' => $whereClause,
          'uid' => $uid,
          'hasCreatedUserId' => $hasCreatedUserId,
          'usePerioddata' => $usePerioddata
      ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($periods)) {
    // 如果根據使用者過濾後沒有資料，提供詳細的調試資訊
    $debugInfo = [
        'table' => $periodTable,
        'usePerioddata' => $usePerioddata,
        'hasCreatedUserId' => $hasCreatedUserId,
        'uid' => $uid,
        'whereClause' => $whereClause
    ];
    
    if ($usePerioddata && $hasCreatedUserId && $uid) {
      // 嘗試查詢所有資料看看是否有資料
      try {
        // 檢查是否有 pe_created_u_ID 欄位
        $checkFields = $periodFields;
        if ($hasCreatedUserId) {
            $checkFields .= ', pe_created_u_ID';
        }
        $allPeriods = $conn->query("
          SELECT $checkFields, $periodActiveField as is_active
          FROM $periodTable
          ORDER BY period_start_d DESC
          LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $userPeriods = array_filter($allPeriods, function($p) use ($uid) {
            return isset($p['pe_created_u_ID']) && $p['pe_created_u_ID'] === $uid;
        });
        
        if (!empty($allPeriods)) {
          echo json_encode([
              'success' => false,
              'msg' => '沒有找到您建立的評分時段資料（資料庫中有 ' . count($allPeriods) . ' 筆時段，其中 ' . count($userPeriods) . ' 筆是您建立的）',
              'debug' => $debugInfo
          ], JSON_UNESCAPED_UNICODE);
        } else {
          echo json_encode([
              'success' => false,
              'msg' => '沒有找到任何評分時段資料',
              'debug' => $debugInfo
          ], JSON_UNESCAPED_UNICODE);
        }
      } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'msg' => '沒有找到任何評分時段資料：' . $e->getMessage(),
            'debug' => $debugInfo
        ], JSON_UNESCAPED_UNICODE);
      }
    } else {
      echo json_encode([
          'success' => false,
          'msg' => '沒有找到任何評分時段資料',
          'debug' => $debugInfo
      ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* 判斷選取週次 */
$selectedPeriodId = null;
$active = null;

foreach ($periods as $p) {
    if ((int)$p['is_active'] === 1) {
        $active = $p;
        break;
    }
}

if (isset($_GET['period_ID']) && ctype_digit($_GET['period_ID'])) {
    $pid = (int)$_GET['period_ID'];
    foreach ($periods as $p) {
        if ((int)$p['period_ID'] === $pid) {
            $selectedPeriodId = $pid;
            $active = $p;
            break;
        }
    }
}

if ($selectedPeriodId === null) {
    if ($active) {
        $selectedPeriodId = (int)$active['period_ID'];
    } elseif (!empty($periods)) {
        $selectedPeriodId = (int)$periods[0]['period_ID'];
        $active = $periods[0];
    } else {
        // 即使沒有 periods，也要返回空資料而不是錯誤
        echo json_encode([
            'success' => true,
            'periods' => [],
            'active' => null,
            'period_ID' => null,
            'rows' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* 取得指導老師加入的組別 */
// 檢查 teammember 表使用哪個欄位名稱
$hasTeamUId = false;
try {
  $checkTeamUId = $conn->query("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
  $hasTeamUId = $checkTeamUId->rowCount() > 0;
} catch (Exception $e) {
  // 忽略錯誤
}

// 根據實際欄位名稱構建查詢
$userIdField = $hasTeamUId ? 'team_u_ID' : 'u_ID';

$stTeams = $conn->prepare("
  SELECT DISTINCT tm.team_ID,
         COALESCE(td.team_project_name, CONCAT('Team ', tm.team_ID)) AS team_name
  FROM teammember tm
  JOIN userrolesdata ur ON ur.u_ID = tm.$userIdField
  LEFT JOIN teamdata td ON td.team_ID = tm.team_ID
  WHERE tm.$userIdField = :uid
    AND ur.role_ID = 4
    AND ur.user_role_status = 1
    AND (tm.tm_status IS NULL OR tm.tm_status = 1)
  ORDER BY tm.team_ID
");
$stTeams->execute([':uid' => $uid]);
$teams = $stTeams->fetchAll(PDO::FETCH_ASSOC);

// 調試：記錄查詢結果
error_log("查詢團隊 - 使用者欄位: $userIdField, 使用者ID: $uid, 找到團隊數: " . count($teams));

// 如果沒有找到團隊，返回空陣列而不是錯誤
if (empty($teams)) {
    error_log("沒有找到團隊 - 使用者ID: $uid, 使用者欄位: $userIdField");
    // 即使沒有團隊，也要返回 periods 資料
    echo json_encode([
        'success'    => true,
        'periods'    => $periods,
        'active'     => $active,
        'period_ID'  => $selectedPeriodId,
        'rows'       => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 該組學生數 */
// 使用動態欄位名稱
$stMember = $conn->prepare("
  SELECT COUNT(*)
  FROM teammember tm
  JOIN userrolesdata ur ON ur.u_ID = tm.$userIdField
  WHERE tm.team_ID = ?
    AND ur.role_ID = 6
    AND ur.user_role_status = 1
    AND (tm.tm_status IS NULL OR tm.tm_status = 1)
");

// 解析 pe_target_ID 的函數
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

/* 已評分的學生 - 根據互評模式動態構建查詢 */
function buildReviewQuery($conn, $teamId, $periodId, $peMode, $targetInfo, $userIdField = 'u_ID') {
  if ($peMode === 'cross') {
    // 團隊間互評模式
    $isAssignTeam = $targetInfo['is_all'] || in_array($teamId, array_map('intval', $targetInfo['assign']));
    $isReceiveTeam = $targetInfo['is_all'] || (empty($targetInfo['receive']) && $targetInfo['is_all']) || in_array($teamId, array_map('intval', $targetInfo['receive']));
    
    $conditions = [];
    $params = [];
    
    // 如果當前團隊是評分團隊（assign），查詢該團隊成員對其他團隊的評分
    if ($isAssignTeam) {
      if (!empty($targetInfo['receive'])) {
        $receiveTeamIds = array_map('intval', $targetInfo['receive']);
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
      if (!empty($targetInfo['assign'])) {
        $assignTeamIds = array_map('intval', $targetInfo['assign']);
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
      // 當前團隊不在評分或被評分列表中，返回 0
      return ['sql' => 'SELECT 0 as cnt', 'params' => []];
    }
    
    $whereClause = implode(' OR ', $conditions);
    // 注意：period_ID 參數應該在 WHERE 子句的第一個位置
    array_unshift($params, $periodId);
    
    $sql = "
      SELECT COUNT(DISTINCT pr.review_a_u_ID) as cnt
      FROM peerreview pr
      JOIN teammember tma ON pr.review_a_u_ID = tma.$userIdField
      JOIN teammember tmb ON pr.review_b_u_ID = tmb.$userIdField
      JOIN userrolesdata ura ON ura.u_ID = pr.review_a_u_ID
      WHERE pr.period_ID = ? AND ($whereClause)
        AND ura.role_ID = 6
        AND ura.user_role_status = 1
        AND (tma.tm_status IS NULL OR tma.tm_status = 1)
        AND (tmb.tm_status IS NULL OR tmb.tm_status = 1)
    ";
    
    return ['sql' => $sql, 'params' => $params];
  } else {
    // 團隊內互評模式（原有邏輯）
    $sql = "
      SELECT COUNT(DISTINCT pr.review_a_u_ID) as cnt
      FROM peerreview pr
      JOIN teammember tma ON pr.review_a_u_ID = tma.$userIdField AND tma.team_ID = ?
      JOIN teammember tmb ON pr.review_b_u_ID = tmb.$userIdField AND tmb.team_ID = ?
      JOIN userrolesdata ura ON ura.u_ID = pr.review_a_u_ID
      WHERE pr.period_ID = ?
        AND ura.role_ID = 6
        AND ura.user_role_status = 1
        AND (tma.tm_status IS NULL OR tma.tm_status = 1)
        AND (tmb.tm_status IS NULL OR tmb.tm_status = 1)
    ";
    return ['sql' => $sql, 'params' => [$teamId, $teamId, $periodId]];
  }
}

// 取得當前選取週次的互評模式資訊
$currentPeriod = null;
foreach ($periods as $p) {
    if ((int)$p['period_ID'] === $selectedPeriodId) {
        $currentPeriod = $p;
        break;
    }
}

$peMode = 'in';
$peTargetId = 'ALL';
if ($currentPeriod) {
    $peMode = ($hasPeMode && isset($currentPeriod['pe_mode'])) ? $currentPeriod['pe_mode'] : 'in';
    $peTargetId = ($hasPeTargetId && isset($currentPeriod['pe_target_ID'])) ? $currentPeriod['pe_target_ID'] : 'ALL';
}

$targetInfo = parseTeamTarget($peTargetId);

$rows = [];
foreach ($teams as $t) {
    $tid = (int)$t['team_ID'];

    $stMember->execute([$tid]);
    $expectedResult = $stMember->fetchColumn();
    $expected = $expectedResult !== false && $expectedResult !== null ? (int)$expectedResult : 0;

    // 根據互評模式構建查詢
    $queryInfo = buildReviewQuery($conn, $tid, $selectedPeriodId, $peMode, $targetInfo, $userIdField);
    
    // 確保即使沒有評分資料也返回 0
    $actual = 0;
    try {
        $stActual = $conn->prepare($queryInfo['sql']);
        $stActual->execute($queryInfo['params']);
        $result = $stActual->fetchColumn();
        $actual = $result !== false && $result !== null ? (int)$result : 0;
    } catch (Exception $e) {
        // 如果查詢失敗，設為 0
        $actual = 0;
        error_log("查詢評分人數失敗 (team_ID: $tid, period_ID: $selectedPeriodId): " . $e->getMessage());
    }

    // 即使沒有評分資料也要顯示團隊資料
    $rows[] = [
        'team_ID'     => $tid,
        'team_name'   => $t['team_name'],
        'expected'    => $expected,
        'actual'      => $actual,
        'is_complete' => ($expected > 0 && $actual === $expected)
    ];
}

// 確保返回的資料包含所有必要欄位
$response = [
    'success'    => true,
    'periods'    => $periods,
    'active'     => $active,
    'period_ID'  => $selectedPeriodId,
    'rows'       => $rows,
    'meta'       => [
        'current_user' => $uid,
        'matched_period_creator' => $matchedUserId,
        'period_count' => count($periods),
        'team_count'   => count($teams)
    ]
];

// 調試：記錄返回的資料
error_log("返回資料 - periods數量: " . count($periods) . ", rows數量: " . count($rows) . ", selectedPeriodId: $selectedPeriodId");

echo json_encode($response, JSON_UNESCAPED_UNICODE);
