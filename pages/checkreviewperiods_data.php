<?php
session_start();
require '../includes/pdo.php';

$sort = $_REQUEST['sort'] ?? 'created';

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
    case 'start':  $orderBy = 'ORDER BY p.period_start_d DESC, p.period_ID DESC'; break;
    case 'end':    $orderBy = 'ORDER BY p.period_end_d DESC, p.period_ID DESC'; break;
    case 'active': $orderBy = 'ORDER BY p.pe_status DESC, p.pe_created_d DESC'; break;
    default:       $orderBy = 'ORDER BY p.pe_created_d DESC, p.period_ID DESC';
}

/* CRUD: create */
if ($_POST['action'] ?? '' === 'create') {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeRoleId = false;
    $hasPeClassId = false;
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

    $fields[] = 'pe_status';
    $values[] = isset($_POST['pe_status']) ? 1 : 0;
    $placeholders[] = '?';

    $sql = "INSERT INTO perioddata (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    $stmt->execute($values);
    
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
if ($_POST['action'] ?? '' === 'update') {
    // 檢查欄位是否存在
    $hasCohortId = false;
    $hasPeTargetId = false;
    $hasPeClassId = false;
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

    $sets[] = 'pe_status=?';
    $values[] = isset($_POST['pe_status']) ? 1 : 0;
    $values[] = $_POST['period_ID']; // WHERE 條件

    $sql = "UPDATE perioddata SET " . implode(', ', $sets) . " WHERE period_ID=?";

    $stmt = $conn->prepare($sql);
    $stmt->execute($values);
    
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
if ($_POST['action'] ?? '' === 'delete') {
    $stmt = $conn->prepare("DELETE FROM perioddata WHERE period_ID=?");
    $stmt->execute([$_POST['period_ID']]);
    header("Location: checkreviewperiods.php?sort=$sort");
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

$stmt = $conn->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<table class="table table-bordered table-striped">
  <thead class="table-light">
    <tr>
      <th>序號</th><th>開始日</th><th>結束日</th><th>標題</th>
      <th>指定團隊</th><th>屆別</th><th>啟用</th>
      <th>建立時間</th><th>操作</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $r): ?>
    <tr>
      <td><?= $rankByCreated[$r['period_ID']] ?? '' ?></td>
      <td><?= htmlspecialchars($r['period_start_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_end_d'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['period_title'] ?? '') ?></td>
      <td><?php
        $targetRaw = $r['pe_target_ID'] ?? '';
        if ($targetRaw === 'ALL' || $targetRaw === '' || $targetRaw === null) {
          echo $targetRaw === 'ALL' ? '全部 (ALL)' : '－';
        } elseif (strpos($targetRaw, ',') !== false) {
          echo '多個團隊';
        } elseif (!empty($r['team_project_name'])) {
          echo htmlspecialchars($r['team_project_name']);
        } else {
          echo htmlspecialchars($targetRaw);
        }
      ?></td>
      <td><?= 
        ($r['cohort_name'] ?? '') ? 
        htmlspecialchars($r['cohort_name']) . ' (' . htmlspecialchars($r['year_label'] ?? '') . ')' : 
        '－'
      ?></td>
      <td><?= ($r['pe_status'] ?? 0) ? '✔' : '✘' ?></td>
      <td><?= htmlspecialchars($r['pe_created_d'] ?? '') ?></td>
      <td>
        <button class="btn btn-sm btn-outline-primary" 
          onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>

        <form method="post" action="pages/checkreviewperiods_data.php" class="d-inline" onsubmit="return confirm('確定刪除？');">
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
