<?php
session_start();
require '../includes/pdo.php';

$sort = $_REQUEST['sort'] ?? 'created';

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(array $payload, int $statusCode = 200) {
    if (headers_sent() === false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

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

function normalizeIdList($raw) {
    if (!$raw) return [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $items = preg_split('/[,\s]+/', (string)$raw);
    }
    $ids = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item === '') continue;
        if (!is_numeric($item)) continue;
        $ids[] = (int)$item;
    }
    return array_values(array_unique($ids));
}

function getPostedCohortIdList() {
    $list = normalizeIdList($_POST['cohort_values'] ?? null);
    if (!$list) {
        $fallback = resolvePostedCohortId();
        if ($fallback) $list = [(int)$fallback];
    }
    return $list;
}

function getPostedClassIdList() {
    $raw = $_POST['pe_class_ID'] ?? null;
    $list = normalizeIdList($raw);
    return $list;
}

function tableExists(PDO $conn, $tableName) {
    static $cache = [];
    if (isset($cache[$tableName])) return $cache[$tableName];
    try {
        $quoted = $conn->quote($tableName);
        $stmt = $conn->query("SHOW TABLES LIKE {$quoted}");
        $cache[$tableName] = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $cache[$tableName] = false;
    }
    return $cache[$tableName];
}

function teamTableColumns(PDO $conn) {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM teamdata");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }
        }
    } catch (Exception $e) {
        $columns = [];
    }
    return $columns;
}

function teamColumnExists(PDO $conn, $column) {
    $columns = teamTableColumns($conn);
    return isset($columns[$column]);
}

function periodTableColumns(PDO $conn, bool $refresh = false) {
    static $columns = null;
    if (!$refresh && $columns !== null) return $columns;
    $columns = [];
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM perioddata");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }
        }
    } catch (Exception $e) {
        $columns = [];
    }
    return $columns;
}

function periodColumnExists(PDO $conn, $column, bool $refresh = false) {
    $columns = periodTableColumns($conn, $refresh);
    return isset($columns[$column]);
}

function getPeriodStatusColumn(PDO $conn) {
    static $statusColumn = null;
    if ($statusColumn !== null) return $statusColumn;
    if (periodColumnExists($conn, 'pe_status')) {
        $statusColumn = 'pe_status';
    } elseif (periodColumnExists($conn, 'status_ID')) {
        $statusColumn = 'status_ID';
    } else {
        $statusColumn = null;
$attemptedAddTargetColumn = false;
function ensurePeriodTargetStorage(PDO $conn) {
    global $attemptedAddTargetColumn;
    if (periodColumnExists($conn, 'pe_target_ID')) {
        return true;
    }
    if ($attemptedAddTargetColumn) {
        return false;
    }
    $attemptedAddTargetColumn = true;
    try {
        $conn->exec("ALTER TABLE perioddata ADD COLUMN pe_target_ID TEXT NULL");
    } catch (Exception $e) {
        // ignore
    }
    return periodColumnExists($conn, 'pe_target_ID', true);
}

    }
    return $statusColumn;
}

function petargetTableColumns(PDO $conn) {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    if (!tableExists($conn, 'petargetdata')) {
        return $columns;
    }
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM petargetdata");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }
        }
    } catch (Exception $e) {
        $columns = [];
    }
    return $columns;
}

function petargetColumnExists(PDO $conn, $column) {
    $columns = petargetTableColumns($conn);
    return isset($columns[$column]);
}

function fetchTeamInfoByIds(PDO $conn, array $teamIds) {
    if (!$teamIds) return [];
    $teamIds = array_values(array_unique(array_filter(array_map('intval', $teamIds))));
    if (!$teamIds) return [];
    $columns = ['team_ID'];
    if (teamColumnExists($conn, 'class_ID')) $columns[] = 'class_ID';
    if (teamColumnExists($conn, 'cohort_ID')) $columns[] = 'cohort_ID';
    if (teamColumnExists($conn, 'team_project_name')) $columns[] = 'team_project_name';
    if (teamColumnExists($conn, 'team_status')) $columns[] = 'team_status';
    $columnSql = implode(', ', array_unique($columns));
    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $sql = "SELECT {$columnSql} FROM teamdata WHERE team_ID IN ({$placeholders})";
    if (teamColumnExists($conn, 'team_status')) {
        $sql .= " AND team_status = 1";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($teamIds);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['team_ID']] = $row;
    }
    return $map;
}

function fetchTeamsByFilters(PDO $conn, array $cohortIds, array $classIds) {
    $columns = ['team_ID'];
    if (teamColumnExists($conn, 'class_ID')) $columns[] = 'class_ID';
    if (teamColumnExists($conn, 'cohort_ID')) $columns[] = 'cohort_ID';
    if (teamColumnExists($conn, 'team_project_name')) $columns[] = 'team_project_name';
    if (teamColumnExists($conn, 'team_status')) $columns[] = 'team_status';
    $columnSql = implode(', ', array_unique($columns));
    $sql = "SELECT {$columnSql} FROM teamdata";
    $conditions = [];
    $params = [];
    if (teamColumnExists($conn, 'team_status')) {
        $conditions[] = "team_status = 1";
    }
    if ($cohortIds && teamColumnExists($conn, 'cohort_ID')) {
        $placeholders = implode(',', array_fill(0, count($cohortIds), '?'));
        $conditions[] = "cohort_ID IN ({$placeholders})";
        $params = array_merge($params, $cohortIds);
    }
    if ($classIds && teamColumnExists($conn, 'class_ID')) {
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $conditions[] = "class_ID IN ({$placeholders})";
        $params = array_merge($params, $classIds);
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['team_ID']] = $row;
    }
    return $map;
}

function fetchPeriodTargetTeams(PDO $conn, array $periodIds) {
    if (!$periodIds || !tableExists($conn, 'petargetdata')) {
        return [];
    }
    $periodIds = array_values(array_unique(array_filter(array_map('intval', $periodIds))));
    if (!$periodIds) return [];
    $placeholders = implode(',', array_fill(0, count($periodIds), '?'));
    $stmt = $conn->prepare("SELECT period_ID, pe_team_ID, pe_cohort_ID FROM petargetdata WHERE period_ID IN ($placeholders)");
    $stmt->execute($periodIds);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['period_ID'];
        if (!isset($map[$pid])) {
            $map[$pid] = [
                'team_ids' => [],
                'cohort_ids' => []
            ];
        }
        if (isset($row['pe_team_ID']) && $row['pe_team_ID'] !== null) {
            $map[$pid]['team_ids'][] = (int)$row['pe_team_ID'];
        }
        if (isset($row['pe_cohort_ID']) && $row['pe_cohort_ID'] !== null) {
            $map[$pid]['cohort_ids'][] = (int)$row['pe_cohort_ID'];
        }
    }
    return $map;
}

function determineTargetTeams(PDO $conn, array $payload, array $cohortIds, array $classIds) {
    $targetIds = $payload['receive'];
    if (!$targetIds) {
        $targetIds = $payload['assign'];
    }
    if ($targetIds) {
        $teams = fetchTeamInfoByIds($conn, $targetIds);
        if ($teams) return $teams;
    }
    if ($payload['is_all'] || empty($targetIds)) {
        return fetchTeamsByFilters($conn, $cohortIds, $classIds);
    }
    return [];
}

function syncPeriodTargets(PDO $conn, $periodId, $rawTargetValue, array $cohortIds, array $classIds) {
    if (!$periodId || !tableExists($conn, 'petargetdata')) {
        return;
    }
    $payload = parseTeamTarget($rawTargetValue);
    $targets = determineTargetTeams($conn, $payload, $cohortIds, $classIds);
    $conn->prepare("DELETE FROM petargetdata WHERE period_ID=?")->execute([$periodId]);
    if (!$targets) {
        return;
    }
    $columns = ['period_ID', 'pe_team_ID'];
    $valuesTemplate = '(?, ?';
    $petargetHasClass = petargetColumnExists($conn, 'pe_class_ID');
    $petargetHasCohort = petargetColumnExists($conn, 'pe_cohort_ID');
    $petargetHasGrade = petargetColumnExists($conn, 'pe_grade_no');
    $petargetHasStatus = petargetColumnExists($conn, 'status_ID');
    $includeClass = $petargetHasClass && teamColumnExists($conn, 'class_ID');
    $includeCohort = $petargetHasCohort && teamColumnExists($conn, 'cohort_ID');
    $includeGrade = $petargetHasGrade && teamColumnExists($conn, 'grade_no');
    if ($includeClass) { $columns[] = 'pe_class_ID'; $valuesTemplate .= ', ?'; }
    if ($includeCohort) { $columns[] = 'pe_cohort_ID'; $valuesTemplate .= ', ?'; }
    if ($includeGrade) { $columns[] = 'pe_grade_no'; $valuesTemplate .= ', ?'; }
    if ($petargetHasStatus) { $columns[] = 'status_ID'; $valuesTemplate .= ', ?'; }
    $valuesTemplate .= ')';
    $columnSql = implode(', ', $columns);
    $sql = "INSERT INTO petargetdata ({$columnSql}) VALUES {$valuesTemplate}";
    $stmt = $conn->prepare($sql);
    foreach ($targets as $info) {
        $params = [$periodId, $info['team_ID']];
        if ($includeClass) $params[] = $info['class_ID'] ?? null;
        if ($includeCohort) $params[] = $info['cohort_ID'] ?? null;
        if ($includeGrade) $params[] = $info['grade_no'] ?? null;
        if ($petargetHasStatus) $params[] = 1;
        $stmt->execute($params);
    }
}

function describeTeamSelection(array $payload, string $role, array $teamNameMap) {
    $isAll = !empty($payload['is_all']);
    if ($role === 'assign') {
        if ($isAll) return '全部 (ALL)';
    } else {
        if ($isAll) return 'ALL';
    }
    $ids = $payload[$role] ?? [];
    if (!$ids || !count($ids)) {
        return $role === 'receive' ? '－' : '－';
    }
    $labels = [];
    foreach ($ids as $id) {
        $key = (string)$id;
        if (isset($teamNameMap[$key])) {
            $labels[] = $teamNameMap[$key];
        }
    }
    if (count($labels) === 1) {
        return $labels[0];
    }
    if (!count($labels)) {
        return count($ids) > 1 ? '多個團隊' : $ids[0];
    }
    return '多個團隊 (' . count($labels) . ')';
}

function resolvePostedCohortId() {
    $primary = $_POST['cohort_primary'] ?? null;
    $raw = $_POST['cohort_ID'] ?? null;
    if (is_array($raw)) {
        $raw = $raw[0] ?? null;
    }
    return $primary ?: $raw;
}

function resolvePostedClassId() {
    $raw = $_POST['pe_class_ID'] ?? null;
    if (is_array($raw)) {
        $raw = $raw[0] ?? null;
    }
    return ($raw === '' ? null : $raw);
}

function resolvePostedMode() {
    $raw = strtolower(trim($_POST['pe_mode'] ?? $_POST['mode_value'] ?? ''));
    return $raw === 'cross' ? 'cross' : 'in';
}

$periodStatusColumn = getPeriodStatusColumn($conn);

/* 排序 */
switch ($sort) {
    case 'start':  $orderBy = 'ORDER BY p.period_start_d DESC, p.period_ID DESC'; break;
    case 'end':    $orderBy = 'ORDER BY p.period_end_d DESC, p.period_ID DESC'; break;
    case 'active':
        if ($periodStatusColumn) {
            $orderBy = "ORDER BY p.{$periodStatusColumn} DESC, p.pe_created_d DESC";
        } else {
            $orderBy = 'ORDER BY p.pe_created_d DESC, p.period_ID DESC';
        }
        break;
    default:       $orderBy = 'ORDER BY p.pe_created_d DESC, p.period_ID DESC';
}

/* CRUD: create */
if (($_POST['action'] ?? '') === 'create') {
    try {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeRoleId = false;
    $hasPeClassId = false;
    $hasPeMode = false;
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCohortId = in_array('cohort_ID', $columns);
        $hasPeTargetId = in_array('pe_target_ID', $columns);
        $hasPeRoleId = in_array('pe_role_ID', $columns);
        $hasPeClassId = in_array('pe_class_ID', $columns);
        $hasPeMode = in_array('pe_mode', $columns);
    } catch (Exception $e) {
        // 如果檢查失敗，使用預設值
    }

    if (!$hasPeTargetId && ensurePeriodTargetStorage($conn)) {
        $hasPeTargetId = true;
    }

    // 根據欄位存在情況動態建立 SQL
    $fields = ['period_start_d', 'period_end_d', 'period_title'];
    $values = [$_POST['period_start_d'], $_POST['period_end_d'], $_POST['period_title']];
    $placeholders = ['?', '?', '?'];

    if ($hasPeTargetId) {
        $rawTarget = $_POST['pe_target_ID'] ?? null;
        if ($rawTarget !== null) {
            $fields[] = 'pe_target_ID';
            $values[] = $rawTarget;
            $placeholders[] = '?';
        }
    }
    if ($hasCohortId) {
        $fields[] = 'cohort_ID';
        $values[] = resolvePostedCohortId();
        $placeholders[] = '?';
    }
    if ($hasPeClassId) {
        $fields[] = 'pe_class_ID';
        $values[] = ($classId = resolvePostedClassId()) !== null ? (int)$classId : null;
        $placeholders[] = '?';
    }

    $fields[] = 'pe_created_d';
    $placeholders[] = 'NOW()';

    $fields[] = 'pe_created_u_ID';
    $values[] = $_SESSION['u_ID'] ?? null;
    $placeholders[] = '?';

    if ($hasPeRoleId) {
        $fields[] = 'pe_role_ID';
        $values[] = $_SESSION['role_ID'] ?? null;
        $placeholders[] = '?';
    }

    $modeValue = resolvePostedMode();
    if ($hasPeMode) {
        $fields[] = 'pe_mode';
        $values[] = $modeValue;
        $placeholders[] = '?';
    }

    if ($periodStatusColumn) {
        $fields[] = $periodStatusColumn;
        $values[] = isset($_POST['pe_status']) ? 1 : 0;
        $placeholders[] = '?';
    }

    $sql = "INSERT INTO perioddata (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
        $newPeriodId = (int)$conn->lastInsertId();
        $cohortList = getPostedCohortIdList();
        $classList = getPostedClassIdList();
        syncPeriodTargets($conn, $newPeriodId, $_POST['pe_target_ID'] ?? '', $cohortList, $classList);
        
        // 如果是 AJAX 請求，返回 JSON；否則重定向
        if (isAjaxRequest()) {
            jsonResponse(['success' => true, 'msg' => '已新增評分時段']);
        }
        header("Location: checkreviewperiods.php?sort=$sort");
        exit;
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            jsonResponse([
                'success' => false,
                'msg' => '新增評分時段失敗：' . $e->getMessage()
            ], 200);
        }
        throw $e;
    }
}

/* CRUD: update */
if (($_POST['action'] ?? '') === 'update') {
    try {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeClassId = false;
    $hasPeMode = false;
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCohortId = in_array('cohort_ID', $columns);
        $hasPeTargetId = in_array('pe_target_ID', $columns);
        $hasPeClassId = in_array('pe_class_ID', $columns);
        $hasPeMode = in_array('pe_mode', $columns);
    } catch (Exception $e) {
        // 如果檢查失敗，使用預設值
    }

    if (!$hasPeTargetId && ensurePeriodTargetStorage($conn)) {
        $hasPeTargetId = true;
    }

    // 根據欄位存在情況動態建立 SQL
    $sets = [
        'period_start_d=?',
        'period_end_d=?',
        'period_title=?'
    ];
    $values = [
        $_POST['period_start_d'],
        $_POST['period_end_d'],
        $_POST['period_title']
    ];

    if ($hasPeTargetId) {
        $rawTarget = $_POST['pe_target_ID'] ?? null;
        if ($rawTarget !== null) {
            $sets[] = 'pe_target_ID=?';
            $values[] = $rawTarget;
        }
    }
    if ($hasCohortId) {
        $sets[] = 'cohort_ID=?';
        $values[] = resolvePostedCohortId();
    }
    if ($hasPeClassId) {
        $sets[] = 'pe_class_ID=?';
        $values[] = ($classId = resolvePostedClassId()) !== null ? (int)$classId : null;
    }

    $modeValue = resolvePostedMode();
    if ($hasPeMode) {
        $sets[] = 'pe_mode=?';
        $values[] = $modeValue;
    }

    if ($periodStatusColumn) {
        $sets[] = "{$periodStatusColumn}=?";
        $values[] = isset($_POST['pe_status']) ? 1 : 0;
    }
    $values[] = $_POST['period_ID']; // WHERE 條件

    $sql = "UPDATE perioddata SET " . implode(', ', $sets) . " WHERE period_ID=?";

        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
        $cohortList = getPostedCohortIdList();
        $classList = getPostedClassIdList();
        syncPeriodTargets($conn, (int)($_POST['period_ID'] ?? 0), $_POST['pe_target_ID'] ?? '', $cohortList, $classList);
        
        // 如果是 AJAX 請求，返回 JSON；否則重定向
        if (isAjaxRequest()) {
            jsonResponse(['success' => true, 'msg' => '已更新評分時段']);
        }
        header("Location: checkreviewperiods.php?sort=$sort");
        exit;
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            jsonResponse([
                'success' => false,
                'msg' => '更新評分時段失敗：' . $e->getMessage()
            ], 200);
        }
        throw $e;
    }
}

/* CRUD: delete */
if (($_POST['action'] ?? '') === 'delete') {
    $periodId = (int)($_POST['period_ID'] ?? 0);
    if ($periodId > 0) {
        $stmt = $conn->prepare("DELETE FROM perioddata WHERE period_ID=?");
        $stmt->execute([$periodId]);
    }
    if (isAjaxRequest()) {
        jsonResponse(['success' => true, 'msg' => '已刪除評分時段']);
    }
    header("Location: checkreviewperiods.php?sort=$sort");
    exit;
}

if (($_POST['action'] ?? '') === 'toggle_status') {
    $periodId = (int)($_POST['period_ID'] ?? 0);
    $targetStatus = (int)($_POST['target_status'] ?? 0) === 1 ? 1 : 0;
    $success = false;
    if (!$periodStatusColumn) {
        jsonResponse([
            'success' => false,
            'msg' => '目前資料表沒有狀態欄位可供更新'
        ], 200);
    }
    if ($periodId > 0) {
        $stmt = $conn->prepare("UPDATE perioddata SET {$periodStatusColumn}=? WHERE period_ID=?");
        $success = $stmt->execute([$targetStatus, $periodId]);
    }
    jsonResponse([
        'success' => $success,
        'status' => $targetStatus,
        'period_ID' => $periodId
    ], 200);
}

/* 取得班級 */
if (isset($_GET['class_list'])) {

  ob_clean();
  header('Content-Type: application/json; charset=utf-8');

  try {
      $stmt = $conn->prepare("
          SELECT c_ID, c_name
          FROM classdata
          ORDER BY c_ID ASC
      ");
      $stmt->execute();
      echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
  } catch (Exception $e) {
      echo json_encode([], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

/* 取得屆別 */
if (isset($_GET['cohort_list'])) {

  ob_clean(); // 🔥 清除之前所有 output（防止 BOM）
  header('Content-Type: application/json; charset=utf-8');

  $stmt = $conn->prepare("
      SELECT
          cohort_ID,
          cohort_name,
          year_label
      FROM cohortdata
      WHERE cohort_status = 1  /* 如果你只想抓啟用的 */
      ORDER BY cohort_ID ASC
  ");
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  exit;
}


/* 取得指定屆別的團隊 */
if (isset($_GET['team_list'])) {

  ob_clean();
  header('Content-Type: application/json; charset=utf-8');

  $cohortId = $_GET['cohort_id'] ?? null;

  if (!$cohortId) {
      echo json_encode([]);
      exit;
  }

  $ids = array_filter(array_map('intval', explode(',', $cohortId)), function($v) {
      return $v > 0;
  });

  if (empty($ids)) {
      echo json_encode([]);
      exit;
  }

  $classParam = $_GET['class_id'] ?? '';
  $classIds = array_filter(array_map('intval', explode(',', $classParam)), function($v) {
      return $v > 0;
  });

  $hasClassColumn = false;
  try {
      $colStmt = $conn->query("SHOW COLUMNS FROM teamdata LIKE 'class_ID'");
      $hasClassColumn = $colStmt->rowCount() > 0;
  } catch (Exception $e) {
      $hasClassColumn = false;
  }

  $sql = "
      SELECT team_ID, team_project_name
      FROM teamdata
      WHERE team_status = 1
        AND cohort_ID IN (%s)
  ";
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $params = $ids;

  if ($hasClassColumn && !empty($classIds)) {
      $classPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
      $sql .= " AND class_ID IN ($classPlaceholders)";
      $params = array_merge($params, $classIds);
  }

  $sql .= " ORDER BY team_project_name ASC";
  $stmt = $conn->prepare(sprintf($sql, $placeholders));
  $stmt->execute($params);

  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
  exit;
}


/* 取得表格資料 */
// 先檢查 perioddata 表是否有 cohort_ID 欄位
$hasCohortId = false;
try {
    $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'cohort_ID'");
    $hasCohortId = $checkStmt->rowCount() > 0;
} catch (Exception $e) {
    // 如果檢查失敗，假設沒有這個欄位
    $hasCohortId = false;
}

// 檢查是否有 pe_target_ID 欄位
$hasPeTargetId = false;
try {
    $checkStmt2 = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_target_ID'");
    $hasPeTargetId = $checkStmt2->rowCount() > 0;
} catch (Exception $e) {
    $hasPeTargetId = false;
}
$hasPeMode = false;
try {
    $checkStmtMode = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_mode'");
    $hasPeMode = $checkStmtMode->rowCount() > 0;
} catch (Exception $e) {
    $hasPeMode = false;
}

if ($hasCohortId || $hasPeMode) {
    $selectedCols = ['p.*'];
    if ($hasCohortId) {
        $selectedCols[] = 'c.cohort_name';
        $selectedCols[] = 'c.year_label';
    } else {
        $selectedCols[] = 'NULL as cohort_name';
        $selectedCols[] = 'NULL as year_label';
    }
    if ($hasPeTargetId) {
        $selectedCols[] = "CASE WHEN p.pe_target_ID = 'ALL' THEN NULL ELSE t.team_project_name END as team_project_name";
    } else {
        $selectedCols[] = 'NULL as team_project_name';
    }
    $selectedCols[] = $hasPeMode ? 'p.pe_mode as pe_mode' : "NULL as pe_mode";
    $sql = "SELECT " . implode(', ', $selectedCols) . "
            FROM perioddata p";
    if ($hasCohortId) {
        $sql .= " LEFT JOIN cohortdata c ON p.cohort_ID = c.cohort_ID";
    }
    if ($hasPeTargetId) {
        $sql .= " LEFT JOIN teamdata t ON CAST(p.pe_target_ID AS CHAR) = CAST(t.team_ID AS CHAR) AND p.pe_target_ID != 'ALL'";
    }
    $sql .= " $orderBy";
} else {
    if ($hasPeTargetId) {
        $sql = "SELECT p.*, NULL as cohort_name, NULL as year_label,
                       CASE 
                         WHEN p.pe_target_ID = 'ALL' THEN NULL
                         ELSE t.team_project_name
                       END as team_project_name,
                       " . ($hasPeMode ? 'p.pe_mode' : "NULL as pe_mode") . "
                FROM perioddata p
                LEFT JOIN teamdata t ON CAST(p.pe_target_ID AS CHAR) = CAST(t.team_ID AS CHAR) 
                    AND p.pe_target_ID != 'ALL'
                $orderBy";
    } else {
        $sql = "SELECT p.*, NULL as cohort_name, NULL as year_label,
                       NULL as team_project_name,
                       " . ($hasPeMode ? 'p.pe_mode' : "NULL as pe_mode") . "
                FROM perioddata p
                $orderBy";
    }
}

$stmt = $conn->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 解析團隊資訊以供顯示 */
$periodIdList = array_values(array_unique(array_map(function ($item) {
    return (int)($item['period_ID'] ?? 0);
}, $rows)));
$periodTargetMap = fetchPeriodTargetTeams($conn, $periodIdList);

$teamIdSet = [];
foreach ($rows as &$rowItem) {
$payload = parseTeamTarget($rowItem['pe_target_ID'] ?? '');
$rawMode = trim(strtolower($rowItem['mode'] ?? $rowItem['pe_mode'] ?? ''));
    $targetInfo = $periodTargetMap[(int)($rowItem['period_ID'] ?? 0)] ?? ['team_ids' => [], 'cohort_ids' => []];
    $fallbackTargets = $targetInfo['team_ids'] ?? [];
    $cohortValues = array_unique(array_filter($targetInfo['cohort_ids'] ?? []));
    $rowItem['cohort_values'] = implode(',', $cohortValues);
    
    // 判斷是否為團隊間互評：有 receive 欄位且不為空，或原始值是 JSON 格式
    $isCrossMode = !empty($payload['receive']);
    
    if ($fallbackTargets) {
        if ($isCrossMode) {
            if (!$payload['receive'] || $payload['is_all']) {
                $payload['receive'] = array_map('strval', $fallbackTargets);
                $payload['is_all'] = false;
            }
        } else {
            if ($payload['is_all'] || (!count($payload['assign']) && !count($payload['receive']))) {
                $payload['assign'] = array_map('strval', $fallbackTargets);
                $payload['receive'] = array_map('strval', $fallbackTargets);
                $payload['is_all'] = false;
            }
        }
    }
    if (!empty($payload['assign']) || !empty($payload['receive'])) {
        $rowItem['pe_target_ID'] = json_encode([
            'assign' => $payload['assign'],
            'receive' => $payload['receive']
        ], JSON_UNESCAPED_UNICODE);
    }
$rowItem['mode'] = (!empty($payload['receive']) || $rawMode === 'cross') ? 'cross' : 'in';
    $rowItem['_team_payload'] = $payload;
    foreach (['assign', 'receive'] as $role) {
        foreach ($payload[$role] as $teamId) {
            $teamId = (string)$teamId;
            if ($teamId !== '') {
                $teamIdSet[$teamId] = true;
            }
        }
    }
}
unset($rowItem);

$teamNameMap = [];
if (!empty($teamIdSet)) {
    $placeholders = implode(',', array_fill(0, count($teamIdSet), '?'));
    $stmtTeam = $conn->prepare("SELECT team_ID, team_project_name FROM teamdata WHERE team_ID IN ($placeholders)");
    $stmtTeam->execute(array_keys($teamIdSet));
    foreach ($stmtTeam->fetchAll(PDO::FETCH_ASSOC) as $teamRow) {
        $teamNameMap[(string)$teamRow['team_ID']] = $teamRow['team_project_name'];
    }
}

/* Rank by created */
$rankByCreated = [];
$tmp = $rows;
usort($tmp, function($a,$b){
    $c = strcmp($a['pe_created_d'], $b['pe_created_d']);
    return $c ?: ($a['period_ID'] <=> $b['period_ID']);
});
$i=1;
foreach ($tmp as $r) $rankByCreated[$r['period_ID']] = $i++;


/* 回傳表格 HTML */
?>
<table class="table table-bordered table-striped period-table">
  <thead class="table-light">
    <tr>
      <th>開始日</th>
      <th>結束日</th>
      <th>標題</th>
      <th>屆別</th>
      <th>指定團隊</th>
      <th>被評分團隊</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['period_start_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_end_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_title'] ?? '') ?></td>
      <td><?= 
        ($r['cohort_name'] ?? '') ? 
        htmlspecialchars($r['cohort_name']) . ' (' . htmlspecialchars($r['year_label'] ?? '') . ')' : 
        '－'
      ?></td>
      <td><?= htmlspecialchars(describeTeamSelection($r['_team_payload'], 'assign', $teamNameMap)) ?></td>
      <td><?= htmlspecialchars(describeTeamSelection($r['_team_payload'], 'receive', $teamNameMap)) ?></td>
      <td>
        <button class="btn btn-sm btn-outline-primary" 
          onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>

        <form method="post" action="pages/checkreviewperiods_data.php" class="d-inline period-delete-form">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="period_ID" value="<?= $r['period_ID'] ?>">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <button class="btn btn-sm btn-outline-danger">刪除</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
