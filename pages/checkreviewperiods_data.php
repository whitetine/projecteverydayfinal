<?php
session_start();
require '../includes/pdo.php';

$sort = $_REQUEST['sort'] ?? 'created';
$periodHasStatusColumn = hasPeriodStatusColumn($conn);
$requestAction = $_POST['action'] ?? '';
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalRows = (int)($conn->query("SELECT COUNT(*) FROM perioddata")->fetchColumn() ?: 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}
if ($requestAction === 'create' && !empty($_POST['period_ID'])) {
    $requestAction = 'update';
    $_POST['action'] = 'update';
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

function hasPeriodStatusColumn(PDO $conn) {
    static $hasStatus = null;
    if ($hasStatus !== null) return $hasStatus;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM perioddata LIKE 'pe_status'");
        $hasStatus = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $hasStatus = false;
    }
    return $hasStatus;
}

function targetTableColumns(PDO $conn) {
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

function targetColumnExists(PDO $conn, $column) {
    $columns = targetTableColumns($conn);
    return isset($columns[$column]);
}

function fetchTeamEnrollmentMeta(PDO $conn, array $teamIds) {
    if (!$teamIds || !tableExists($conn, 'teammember') || !tableExists($conn, 'enrollmentdata')) {
        return [];
    }
    $teamIds = array_values(array_unique(array_filter(array_map('intval', $teamIds))));
    if (!$teamIds) return [];
    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $sql = "SELECT tm.team_ID,
                   MAX(e.class_ID) AS class_ID,
                   MAX(e.cohort_ID) AS cohort_ID,
                   MAX(e.enroll_grade) AS grade_no
            FROM teammember tm
            JOIN enrollmentdata e ON tm.team_u_ID = e.enroll_u_ID
            WHERE tm.team_ID IN ($placeholders)
            GROUP BY tm.team_ID";
    $stmt = $conn->prepare($sql);
    $stmt->execute($teamIds);
    $meta = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $meta[(string)$row['team_ID']] = [
            'class_ID' => $row['class_ID'] ?? null,
            'cohort_ID' => $row['cohort_ID'] ?? null,
            'grade_no' => $row['grade_no'] ?? null,
        ];
    }
    return $meta;
}

function hydrateTeamInfoWithMeta(PDO $conn, array &$map) {
    if (!$map) return;
    $needsMeta = [];
    foreach ($map as $teamId => $info) {
        $needsClass = !array_key_exists('class_ID', $info) || $info['class_ID'] === null;
        $needsCohort = !array_key_exists('cohort_ID', $info) || $info['cohort_ID'] === null;
        $needsGrade = !array_key_exists('grade_no', $info);
        if ($needsClass || $needsCohort || $needsGrade) {
            $needsMeta[] = (int)$teamId;
        }
    }
    if (!$needsMeta) return;
    $meta = fetchTeamEnrollmentMeta($conn, $needsMeta);
    foreach ($meta as $teamId => $extra) {
        if (!isset($map[$teamId])) continue;
        foreach (['class_ID', 'cohort_ID', 'grade_no'] as $field) {
            if ((!isset($map[$teamId][$field]) || $map[$teamId][$field] === null) && array_key_exists($field, $extra)) {
                $map[$teamId][$field] = $extra[$field];
            }
        }
    }
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
    hydrateTeamInfoWithMeta($conn, $map);
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
    hydrateTeamInfoWithMeta($conn, $map);
    return $map;
}

function fetchPeriodTargetTeams(PDO $conn, array $periodIds) {
    if (!$periodIds || !tableExists($conn, 'petargetdata')) {
        return [];
    }
    $periodIds = array_values(array_unique(array_filter(array_map('intval', $periodIds))));
    if (!$periodIds) return [];
    $placeholders = implode(',', array_fill(0, count($periodIds), '?'));
    $includeStatus = targetColumnExists($conn, 'pe_target_status');
    $includeCohort = targetColumnExists($conn, 'pe_cohort_ID');
    $includeClass = targetColumnExists($conn, 'pe_class_ID');
    $includeGrade = targetColumnExists($conn, 'pe_grade_no');
    $selectColsArr = ['period_ID', 'pe_team_ID'];
    if ($includeStatus) $selectColsArr[] = 'pe_target_status';
    if ($includeCohort) $selectColsArr[] = 'pe_cohort_ID';
    if ($includeClass) $selectColsArr[] = 'pe_class_ID';
    if ($includeGrade) $selectColsArr[] = 'pe_grade_no';
    $selectCols = implode(', ', $selectColsArr);
    $stmt = $conn->prepare("SELECT {$selectCols} FROM petargetdata WHERE period_ID IN ($placeholders)");
    $stmt->execute($periodIds);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['period_ID'];
        if (!isset($map[$pid])) {
            $map[$pid] = [
                'assign' => [],
                'receive' => [],
                'cohort_id' => null,
                'class_id' => null,
                'grade_no' => null,
                'cohort_ids' => [],
                'class_ids' => []
            ];
        }
        $teamId = $row['pe_team_ID'];
        if ($teamId !== null) {
            $role = 'assign';
            if ($includeStatus) {
                $role = ((int)($row['pe_target_status'] ?? 1) === 0) ? 'receive' : 'assign';
            }
            $map[$pid][$role][] = (int)$teamId;
        }
        if ($includeCohort && isset($row['pe_cohort_ID']) && $row['pe_cohort_ID'] !== null) {
            $cid = (int)$row['pe_cohort_ID'];
            if ($map[$pid]['cohort_id'] === null) {
                $map[$pid]['cohort_id'] = $cid;
            }
            $map[$pid]['cohort_ids'][$cid] = true;
        }
        if ($includeClass && isset($row['pe_class_ID']) && $row['pe_class_ID'] !== null) {
            $classId = (int)$row['pe_class_ID'];
            if ($map[$pid]['class_id'] === null) {
                $map[$pid]['class_id'] = $classId;
            }
            $map[$pid]['class_ids'][$classId] = true;
        }
        if ($includeGrade && $map[$pid]['grade_no'] === null && array_key_exists('pe_grade_no', $row) && $row['pe_grade_no'] !== null) {
            $map[$pid]['grade_no'] = (int)$row['pe_grade_no'];
        }
    }
    foreach ($map as &$info) {
        $info['cohort_ids'] = array_values(array_unique(array_map('intval', array_keys($info['cohort_ids']))));
        $info['class_ids'] = array_values(array_unique(array_map('intval', array_keys($info['class_ids']))));
    }
    unset($info);
    return $map;
}

function determineTargetTeams(PDO $conn, array $payload, array $cohortIds, array $classIds) {
    $assignIds = $payload['assign'];
    if ($assignIds) {
        $teams = fetchTeamInfoByIds($conn, $assignIds);
        if ($teams) return $teams;
    }
    if (!empty($payload['is_all'])) {
        return fetchTeamsByFilters($conn, $cohortIds, $classIds);
    }
    return [];
}

function syncPeriodTargets(PDO $conn, $periodId, $rawTargetValue, array $cohortIds, array $classIds, string $mode = 'in') {
    if (!$periodId || !tableExists($conn, 'petargetdata')) {
        return;
    }
    $payload = parseTeamTarget($rawTargetValue);
    $assignTargets = determineTargetTeams($conn, $payload, $cohortIds, $classIds);
    $receiveTargets = [];
    if (!empty($payload['receive'])) {
        $receiveTargets = fetchTeamInfoByIds($conn, $payload['receive']);
    } elseif ($mode === 'in') {
        $receiveTargets = $assignTargets;
    }
    $conn->prepare("DELETE FROM petargetdata WHERE period_ID=?")->execute([$periodId]);
    if (!$assignTargets && !$receiveTargets) {
        return;
    }
    $columns = ['period_ID', 'pe_team_ID'];
    $valuesTemplate = '(?, ?';
    $includeClass = targetColumnExists($conn, 'pe_class_ID');
    $includeCohort = targetColumnExists($conn, 'pe_cohort_ID');
    $includeGrade = targetColumnExists($conn, 'pe_grade_no');
    $includeStatus = targetColumnExists($conn, 'pe_target_status');
    if ($includeClass) { $columns[] = 'pe_class_ID'; $valuesTemplate .= ', ?'; }
    if ($includeCohort) { $columns[] = 'pe_cohort_ID'; $valuesTemplate .= ', ?'; }
    if ($includeGrade) { $columns[] = 'pe_grade_no'; $valuesTemplate .= ', ?'; }
    if ($includeStatus) { $columns[] = 'pe_target_status'; $valuesTemplate .= ', ?'; }
    $valuesTemplate .= ')';
    $columnSql = implode(', ', $columns);
    $sql = "INSERT INTO petargetdata ({$columnSql}) VALUES {$valuesTemplate}";
    $stmt = $conn->prepare($sql);
    $entries = [];
    foreach ($assignTargets as $info) {
        $entries[] = ['info' => $info, 'status' => 1];
    }
    foreach ($receiveTargets as $info) {
        $entries[] = ['info' => $info, 'status' => 0];
    }
    foreach ($entries as $entry) {
        $info = $entry['info'];
        if (!isset($info['class_ID']) || $info['class_ID'] === null) {
            $info['class_ID'] = $classIds[0] ?? null;
        }
        if (!isset($info['cohort_ID']) || $info['cohort_ID'] === null) {
            $info['cohort_ID'] = $cohortIds[0] ?? null;
        }
        if (!array_key_exists('grade_no', $info)) {
            $info['grade_no'] = null;
        }
        $params = [$periodId, $info['team_ID']];
        if ($includeClass) $params[] = $info['class_ID'];
        if ($includeCohort) $params[] = $info['cohort_ID'];
        if ($includeGrade) $params[] = $info['grade_no'];
        if ($includeStatus) $params[] = $entry['status'];
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
        return $role === 'receive' ? 'ALL' : '－';
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

/* 排序 */
switch ($sort) {
    case 'start':
        $orderBy = 'ORDER BY p.period_start_d DESC, p.period_ID DESC';
        break;
    case 'end':
        $orderBy = 'ORDER BY p.period_end_d DESC, p.period_ID DESC';
        break;
    case 'active':
        if ($periodHasStatusColumn) {
            $orderBy = 'ORDER BY p.pe_status DESC, p.pe_created_d DESC';
        } else {
            $orderBy = 'ORDER BY p.pe_created_d DESC, p.period_ID DESC';
        }
        break;
    default:
        $orderBy = 'ORDER BY p.pe_created_d DESC, p.period_ID DESC';
}

/* CRUD: create */
if ($requestAction === 'create') {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeRoleId = false;
    $hasPeClassId = false;
    $columns = [];
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCohortId = in_array('cohort_ID', $columns);
        $hasPeTargetId = in_array('pe_target_ID', $columns);
        $hasPeRoleId = in_array('pe_role_ID', $columns);
        $hasPeClassId = in_array('pe_class_ID', $columns);
    } catch (Exception $e) {
        // 如果檢查失敗，使用預設值
    }

    // 根據欄位存在情況動態建立 SQL
    $fields = ['period_start_d', 'period_end_d', 'period_title'];
    $values = [$_POST['period_start_d'], $_POST['period_end_d'], $_POST['period_title']];
    $placeholders = ['?', '?', '?'];

    if ($hasPeTargetId) {
        $fields[] = 'pe_target_ID';
        $values[] = $_POST['pe_target_ID'] ?? null;
        $placeholders[] = '?';
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

    if ($hasPeStatus = in_array('pe_status', $columns)) {
        $fields[] = 'pe_status';
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
    $mode = ($_POST['pe_mode'] ?? 'in') === 'cross' ? 'cross' : 'in';
    syncPeriodTargets($conn, $newPeriodId, $_POST['pe_target_ID'] ?? '', $cohortList, $classList, $mode);
    
    // 如果是 AJAX 請求，返回 JSON；否則重定向
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'msg' => '已新增評分時段']);
        exit;
    }
    header("Location: checkreviewperiods.php?sort=$sort");
    exit;
}

/* CRUD: update */
if ($requestAction === 'update') {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeClassId = false;
    $columns = [];
    try {
        $checkStmt = $conn->query("SHOW COLUMNS FROM perioddata");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $hasCohortId = in_array('cohort_ID', $columns);
        $hasPeTargetId = in_array('pe_target_ID', $columns);
        $hasPeClassId = in_array('pe_class_ID', $columns);
    } catch (Exception $e) {
        // 如果檢查失敗，使用預設值
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
        $sets[] = 'pe_target_ID=?';
        $values[] = $_POST['pe_target_ID'] ?? null;
    }
    if ($hasCohortId) {
        $sets[] = 'cohort_ID=?';
        $values[] = resolvePostedCohortId();
    }
    if ($hasPeClassId) {
        $sets[] = 'pe_class_ID=?';
        $values[] = ($classId = resolvePostedClassId()) !== null ? (int)$classId : null;
    }

    if (in_array('pe_status', $columns)) {
        $sets[] = 'pe_status=?';
        $values[] = isset($_POST['pe_status']) ? 1 : 0;
    }
    $values[] = $_POST['period_ID']; // WHERE 條件

    $sql = "UPDATE perioddata SET " . implode(', ', $sets) . " WHERE period_ID=?";

    $stmt = $conn->prepare($sql);
    $stmt->execute($values);
    $cohortList = getPostedCohortIdList();
    $classList = getPostedClassIdList();
    $mode = ($_POST['pe_mode'] ?? 'in') === 'cross' ? 'cross' : 'in';
    syncPeriodTargets($conn, (int)($_POST['period_ID'] ?? 0), $_POST['pe_target_ID'] ?? '', $cohortList, $classList, $mode);
    
    // 如果是 AJAX 請求，返回 JSON；否則重定向
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'msg' => '已更新評分時段']);
        exit;
    }
    header("Location: checkreviewperiods.php?sort=$sort");
    exit;
}

/* CRUD: delete */
if ($requestAction === 'delete') {
    $periodId = (int)($_POST['period_ID'] ?? 0);
    $deleted = false;
    if ($periodId > 0) {
        if (tableExists($conn, 'petargetdata')) {
            $conn->prepare("DELETE FROM petargetdata WHERE period_ID=?")->execute([$periodId]);
        }
        $stmt = $conn->prepare("DELETE FROM perioddata WHERE period_ID=?");
        $deleted = $stmt->execute([$periodId]);
    }
    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $deleted,
            'period_ID' => $periodId
        ]);
        exit;
    }
    header("Location: checkreviewperiods.php?sort=$sort");
    exit;
}

if ($requestAction === 'toggle_status') {
    $periodId = (int)($_POST['period_ID'] ?? 0);
    $targetStatus = (int)($_POST['target_status'] ?? 0) === 1 ? 1 : 0;
    $success = false;
    if (!$periodHasStatusColumn) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'msg' => 'perioddata 未包含 pe_status 欄位，無法切換狀態',
            'period_ID' => $periodId
        ]);
        exit;
    }
    if ($periodId > 0) {
        $stmt = $conn->prepare("UPDATE perioddata SET pe_status=? WHERE period_ID=?");
        $success = $stmt->execute([$targetStatus, $periodId]);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'status' => $targetStatus,
        'period_ID' => $periodId
    ]);
    exit;
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

  $hasClassColumn = teamColumnExists($conn, 'class_ID');
  $hasCohortColumn = teamColumnExists($conn, 'cohort_ID');

  $selectColumns = ['team_ID', 'team_project_name'];
  if ($hasCohortColumn) $selectColumns[] = 'cohort_ID';
  if ($hasClassColumn) $selectColumns[] = 'class_ID';

  $sql = "SELECT " . implode(', ', $selectColumns) . "
          FROM teamdata
          WHERE team_status = 1";
  $params = [];

  if ($hasCohortColumn) {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $sql .= " AND cohort_ID IN ($placeholders)";
      $params = array_merge($params, $ids);
  }

  if ($hasClassColumn && !empty($classIds)) {
      $classPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
      $sql .= " AND class_ID IN ($classPlaceholders)";
      $params = array_merge($params, $classIds);
  }

  if (!$hasCohortColumn) {
      // 沒有 cohort_ID 欄位，先抓全部候選回頭再過濾
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $sql .= " AND team_ID IN (
          SELECT DISTINCT tm.team_ID
          FROM teammember tm
          JOIN enrollmentdata e ON tm.team_u_ID = e.enroll_u_ID
          WHERE e.cohort_ID IN ($placeholders)
      )";
      $params = array_merge($params, $ids);
  }

  $sql .= " ORDER BY team_project_name ASC";
  $stmt = $conn->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!empty($rows) && (!$hasClassColumn && !empty($classIds))) {
      $meta = fetchTeamEnrollmentMeta($conn, array_column($rows, 'team_ID'));
      $rows = array_values(array_filter($rows, function ($row) use ($meta, $classIds) {
          $teamId = (string)$row['team_ID'];
          $classId = isset($meta[$teamId]['class_ID']) ? (int)$meta[$teamId]['class_ID'] : null;
          return $classId && in_array($classId, $classIds, true);
      }));
  }

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
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

if ($hasCohortId) {
    // 如果有 cohort_ID 欄位，使用 JOIN
    if ($hasPeTargetId) {
        // 同時 JOIN 團隊資料（只當 pe_target_ID 不是 'ALL' 時）
        $sql = "SELECT p.*, c.cohort_name, c.year_label, 
                       CASE 
                         WHEN p.pe_target_ID = 'ALL' THEN NULL
                         ELSE t.team_project_name
                       END as team_project_name
                FROM perioddata p
                LEFT JOIN cohortdata c ON p.cohort_ID = c.cohort_ID
                LEFT JOIN teamdata t ON CAST(p.pe_target_ID AS CHAR) = CAST(t.team_ID AS CHAR) 
                    AND p.pe_target_ID != 'ALL'
                $orderBy";
    } else {
        $sql = "SELECT p.*, c.cohort_name, c.year_label, 
                       NULL as team_project_name
                FROM perioddata p
                LEFT JOIN cohortdata c ON p.cohort_ID = c.cohort_ID
                $orderBy";
    }
} else {
    // 如果沒有 cohort_ID 欄位，只查詢 perioddata
    if ($hasPeTargetId) {
        $sql = "SELECT p.*, NULL as cohort_name, NULL as year_label,
                       CASE 
                         WHEN p.pe_target_ID = 'ALL' THEN NULL
                         ELSE t.team_project_name
                       END as team_project_name
                FROM perioddata p
                LEFT JOIN teamdata t ON CAST(p.pe_target_ID AS CHAR) = CAST(t.team_ID AS CHAR) 
                    AND p.pe_target_ID != 'ALL'
                $orderBy";
    } else {
        $sql = "SELECT p.*, NULL as cohort_name, NULL as year_label,
                       NULL as team_project_name
                FROM perioddata p
                $orderBy";
    }
}
$sql .= " LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$periodIdList = array_values(array_unique(array_map(function ($item) {
    return (int)($item['period_ID'] ?? 0);
}, $rows)));
$periodTargetMap = fetchPeriodTargetTeams($conn, $periodIdList);

$cohortDisplayMap = [];
$cohortIds = [];
foreach ($rows as $row) {
    $cid = (int)($row['cohort_ID'] ?? 0);
    if ($cid > 0) {
        $cohortIds[$cid] = true;
    }
}
foreach ($periodTargetMap as $info) {
    $cid = (int)($info['cohort_id'] ?? 0);
    if ($cid > 0) {
        $cohortIds[$cid] = true;
    }
    if (!empty($info['cohort_ids'])) {
        foreach ($info['cohort_ids'] as $extraCid) {
            $extraCid = (int)$extraCid;
            if ($extraCid > 0) {
                $cohortIds[$extraCid] = true;
            }
        }
    }
}
if ($cohortIds && tableExists($conn, 'cohortdata')) {
    $placeholders = implode(',', array_fill(0, count($cohortIds), '?'));
    $stmtCohort = $conn->prepare("SELECT cohort_ID, cohort_name, year_label FROM cohortdata WHERE cohort_ID IN ($placeholders)");
    $stmtCohort->execute(array_keys($cohortIds));
    foreach ($stmtCohort->fetchAll(PDO::FETCH_ASSOC) as $cohortRow) {
        $label = trim((string)($cohortRow['cohort_name'] ?? ''));
        $year = trim((string)($cohortRow['year_label'] ?? ''));
        if ($label !== '') {
            $cohortDisplayMap[(int)$cohortRow['cohort_ID']] = $year !== '' ? "{$label} ({$year})" : $label;
        }
    }
}

$teamIdSet = [];
foreach ($rows as &$rowItem) {
    $payload = parseTeamTarget($rowItem['pe_target_ID'] ?? '');
    $fallbackTargets = $periodTargetMap[(int)($rowItem['period_ID'] ?? 0)] ?? [
        'assign' => [],
        'receive' => [],
        'cohort_id' => null,
        'class_id' => null,
        'cohort_ids' => [],
        'class_ids' => []
    ];
    if (!count($payload['assign']) && !empty($fallbackTargets['assign'])) {
        $payload['assign'] = array_map('strval', $fallbackTargets['assign']);
        $payload['is_all'] = false;
    }
    if (!count($payload['receive']) && !empty($fallbackTargets['receive'])) {
        $payload['receive'] = array_map('strval', $fallbackTargets['receive']);
    }
    $cohortList = [];
    $classList = [];
    $directCohortId = (int)($rowItem['cohort_ID'] ?? 0);
    if ($directCohortId > 0) {
        $cohortList[] = $directCohortId;
    }
    if (!empty($fallbackTargets['cohort_ids'])) {
        $cohortList = array_merge($cohortList, array_map('intval', $fallbackTargets['cohort_ids']));
    } elseif (!empty($fallbackTargets['cohort_id'])) {
        $cohortList[] = (int)$fallbackTargets['cohort_id'];
    }
    if (!empty($rowItem['pe_class_ID'])) {
        $classList[] = (int)$rowItem['pe_class_ID'];
    }
    if (!empty($fallbackTargets['class_ids'])) {
        $classList = array_merge($classList, array_map('intval', $fallbackTargets['class_ids']));
    } elseif (!empty($fallbackTargets['class_id'])) {
        $classList[] = (int)$fallbackTargets['class_id'];
    }
    $cohortList = array_values(array_unique(array_filter($cohortList)));
    $classList = array_values(array_unique(array_filter($classList)));
    $rowItem['_cohort_ids'] = $cohortList;
    $rowItem['_class_ids'] = $classList;
    $rowItem['_team_assign_ids'] = $payload['assign'];
    $rowItem['_team_receive_ids'] = $payload['receive'];

    $cohortDisplay = '－';
    $directLabel = trim((string)($rowItem['cohort_name'] ?? ''));
    $directYear = trim((string)($rowItem['year_label'] ?? ''));
    if ($directLabel !== '') {
        $cohortDisplay = $directYear !== '' ? "{$directLabel} ({$directYear})" : $directLabel;
    } else {
        $displayCohortId = $cohortList[0] ?? null;
        if ($displayCohortId !== null && isset($cohortDisplayMap[$displayCohortId])) {
            $cohortDisplay = $cohortDisplayMap[$displayCohortId];
        }
    }
    $rowItem['_cohort_display'] = $cohortDisplay;
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
      <th>啟用</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['period_start_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_end_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_title'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['_cohort_display'] ?? '－') ?></td>
      <td><?= htmlspecialchars(describeTeamSelection($r['_team_payload'], 'assign', $teamNameMap)) ?></td>
      <td><?= htmlspecialchars(describeTeamSelection($r['_team_payload'], 'receive', $teamNameMap)) ?></td>
      <td>
        <?php $isActive = (int)($r['pe_status'] ?? 0) === 1; ?>
        <button type="button"
          class="btn btn-sm btn-status-toggle <?= $isActive ? 'btn-success' : 'btn-outline-secondary' ?>"
          data-period-id="<?= $r['period_ID'] ?>"
          data-next-status="<?= $isActive ? 0 : 1 ?>">
          <?= $isActive ? '啟用中' : '已停用' ?>
        </button>
      </td>
      <td>
        <button class="btn btn-sm btn-outline-primary" 
          onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>

        <form method="post" action="pages/checkreviewperiods_data.php" class="d-inline delete-form" data-role="delete">
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
<?php if ($totalPages > 1): ?>
<div class="period-pagination" data-period-page="<?= $page ?>">
  <?php if ($page > 1): ?>
    <button type="button" class="page-btn nav" data-page="<?= $page - 1 ?>">&laquo;</button>
  <?php else: ?>
    <button type="button" class="page-btn nav disabled">&laquo;</button>
  <?php endif; ?>
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <button type="button"
      class="page-btn number <?= $i === $page ? 'active' : '' ?>"
      <?= $i === $page ? '' : 'data-page="' . $i . '"' ?>>
      <?= $i ?>
    </button>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <button type="button" class="page-btn nav" data-page="<?= $page + 1 ?>">&raquo;</button>
  <?php else: ?>
    <button type="button" class="page-btn nav disabled">&raquo;</button>
  <?php endif; ?>
</div>
<?php endif; ?>
