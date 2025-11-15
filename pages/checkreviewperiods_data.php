<?php
session_start();
require '../includes/pdo.php';

if (!isset($_SESSION['u_ID'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$allowedSort = ['created', 'start', 'end', 'active'];
$sort = $_REQUEST['sort'] ?? 'created';
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'created';
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
    $sql = "INSERT INTO perioddata
        (period_start_d, period_end_d, period_title, pe_target_ID, cohort_ID,
         pe_created_d, pe_created_u_ID, pe_role_ID, pe_status)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['period_start_d'],
        $_POST['period_end_d'],
        $_POST['period_title'],
        $_POST['pe_target_ID'],
        $_POST['cohort_ID'],
        $_SESSION['u_ID'] ?? null,
        $_SESSION['role_ID'] ?? null,
        isset($_POST['pe_status']) ? 1 : 0
    ]);
    header("Location: checkreviewperiods.php?sort=$sort");
    exit;
}

/* CRUD: update */
if ($_POST['action'] ?? '' === 'update') {
    $sql = "UPDATE perioddata
            SET period_start_d=?, period_end_d=?, period_title=?, 
                pe_target_ID=?, cohort_ID=?, pe_status=?
            WHERE period_ID=?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $_POST['period_start_d'],
        $_POST['period_end_d'],
        $_POST['period_title'],
        $_POST['pe_target_ID'],
        $_POST['cohort_ID'],
        isset($_POST['pe_status']) ? 1 : 0,
        $_POST['period_ID']
    ]);
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

/* 取得屆別 */
if (isset($_GET['cohort_list'])) {
    $stmt = $conn->prepare("SELECT cohort_ID, cohort_name, year_label FROM cohortdata ORDER BY cohort_ID ASC");
    $stmt->execute();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

/* 取得團隊清單 */
if (isset($_GET['team_list'])) {
    $stmt = $conn->prepare("SELECT team_ID, team_project_name, cohort_ID FROM teamdata ORDER BY team_project_name ASC");
    $stmt->execute();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

/* 取得表格資料 */
$sql = "SELECT p.*, c.cohort_name, c.year_label, t.team_project_name
        FROM perioddata p
        LEFT JOIN cohortdata c ON p.cohort_ID = c.cohort_ID
        LEFT JOIN teamdata t ON p.pe_target_ID = t.team_ID
        $orderBy";
$stmt = $conn->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');

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
      <td><?= $rankByCreated[$r['period_ID']] ?></td>
      <td><?= htmlspecialchars($r['period_start_d']) ?></td>
      <td><?= htmlspecialchars($r['period_end_d']) ?></td>
      <td><?= htmlspecialchars($r['period_title']) ?></td>
      <td><?= htmlspecialchars($r['team_project_name'] ?? $r['pe_target_ID']) ?></td>
      <td><?= htmlspecialchars($r['cohort_name']) ?> (<?= htmlspecialchars($r['year_label']) ?>)</td>
      <td><?= $r['pe_status'] ? '✔' : '✘' ?></td>
      <td><?= htmlspecialchars($r['pe_created_d']) ?></td>
      <td>
        <button class="btn btn-sm btn-outline-primary" 
          onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>

        <form method="post" action="checkreviewperiods_data.php" class="d-inline" onsubmit="return confirm('確定刪除？');">
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
