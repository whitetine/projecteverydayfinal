<?php
session_start();
require '../includes/pdo.php';

// if (!isset($_SESSION['u_ID'])) {
//   echo "<script>alert('請先登入');location.href='index.php';</script>";
//   exit;
// }
$u_ID = $_SESSION['u_ID'];

// $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// //僅允許啟用中的指導老師 (role_ID=4)
// $stRole = $conn->prepare("SELECT COUNT(*) FROM userrolesdata WHERE u_ID=? AND role_ID=4 AND user_role_status=1");
// $stRole->execute([$uid]);
// if (!$stRole->fetchColumn()) {
//   echo "<script>alert('此頁僅限指導老師查看');location.href='main.php';</script>";
//   exit;
// }

//取全部週次；預設選「當週」(is_active=1)，若沒有則選最新一週
$periods = $conn->query("
  SELECT period_ID, period_title, period_start_d, period_end_d, is_active
  FROM reviewperiods
  ORDER BY period_start_d DESC
")->fetchAll(PDO::FETCH_ASSOC);

$active = null;
foreach ($periods as $p) {
  if ((int)$p['is_active'] === 1) {
    $active = $p;
    break;
  }
}

$selectedPeriodId = null;
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
  }
}

//只列出「這位老師加入的組別」(老師自己在 teammember，且角色=4 啟用)
$stTeams = $conn->prepare("
  SELECT DISTINCT tm.team_ID,
         COALESCE(td.team_project_name, CONCAT('Team ', tm.team_ID)) AS team_name
  FROM teammember tm
  JOIN userrolesdata ur ON ur.u_ID = tm.u_ID
  LEFT JOIN teamdata td ON td.team_ID = tm.team_ID
  WHERE tm.u_ID = :u_ID
    AND ur.role_ID = 4
    AND ur.user_role_status = 1
  ORDER BY tm.team_ID
");
$stTeams->execute([':u_ID' => $u_ID]);
$teams = $stTeams->fetchAll(PDO::FETCH_ASSOC);

//該組「學生人數 N」(啟用中的學生 role_ID=6)
$stMember = $conn->prepare("
  SELECT COUNT(*)
  FROM teammember tm
  JOIN userrolesdata ur ON tm.u_ID = ur.u_ID
  WHERE tm.team_ID = ?
    AND ur.role_ID = 6
    AND ur.user_role_status = 1
");

//本週「已評分的學生數」= 有送出評分的 reviewer 去重 (限定同隊、限定選定週次、且 reviewer 必須是啟用中的學生)
$stActual = $conn->prepare("
  SELECT COUNT(DISTINCT pr.review_a_u_ID)
  FROM peerreview pr
  JOIN teammember tma ON pr.review_a_u_ID = tma.u_ID AND tma.team_ID = ?
  JOIN teammember tmb ON pr.review_b_u_ID = tmb.u_ID AND tmb.team_ID = ?
  JOIN userrolesdata ura ON pr.review_a_u_ID = ura.u_ID
  WHERE pr.period_ID = ?
    AND ura.role_ID = 6
    AND ura.user_role_status = 1
");

$rows = [];
foreach ($teams as $t) {
  $t_ID   = (int)$t['team_ID'];
  $teamName = $t['team_name'];

  // 應有筆數 = 該組學生總數 N
  $stMember->execute([$t_ID]);
  $expected = (int)$stMember->fetchColumn();

  // 已完成 = 本週有送出評分的學生數（distinct reviewer）
  $stActual->execute([$t_ID, $t_ID, $selectedPeriodId]);
  $actual = (int)$stActual->fetchColumn();

  $rows[] = [
    'team_ID'     => $t_ID,
    'team_name'   => $teamName,
    'expected'    => $expected,
    'actual'      => $actual,
    'is_complete' => ($expected > 0 && $actual === $expected),
  ];
}
?>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">我的組別互評完成狀態</h3>

    <!-- 週次篩選（預設當週），改選自動提交 -->
    <form class="d-flex align-items-center flex-nowrap" method="get">
      <!-- <label class="mb-0 me-2 text-muted text-nowrap">週次：</label> -->
      <select name="period_ID"
        class="form-select form-select-sm w-auto"
        onchange="this.form.submit()">
        <?php foreach ($periods as $p): ?>
          <option value="<?= (int)$p['period_ID'] ?>"
            <?= ((int)$p['period_ID'] === (int)$selectedPeriodId) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['period_title']) ?>（<?= htmlspecialchars($p['period_start_d']) ?> ~ <?= htmlspecialchars($p['period_end_d']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn btn-sm btn-primary ms-2">套用</button></noscript>
    </form>

  </div>

  <?php if ($active): ?>
    <div class="text-end mb-2 small text-muted">
      期間：<?= htmlspecialchars($active['period_title']) ?>（<?= htmlspecialchars($active['period_start_d']) ?> ~ <?= htmlspecialchars($active['period_end_d']) ?>）
    </div>
  <?php endif; ?>

  <table class="table table-bordered align-middle align-items-center ">
    <thead class="table-light align-items-center ">
      <tr>
        <th>組別名稱</th>
        <th>學生數</th>
        <th>已完成（本週已評分學生數）</th>
        <th>狀態</th>
        <th>查看</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="5" class="text-center text-muted">（你尚未被加入任何組別）</td>
        </tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <!-- <td><?= htmlspecialchars($r['team_name']) ?>（ID: <?= htmlspecialchars($r['team_ID']) ?>）</td> -->
            <td><?= htmlspecialchars($r['team_name']) ?></td>
            <td><?= $r['expected'] ?></td>
            <td><?= $r['actual'] ?></td>
            <td><?= $r['is_complete'] ? '已完成' : '未評分' ?></td>
            <td>
              <a class="btn btn-sm btn-warning"
                href="../../main.php#pages/teacher_review_detail.php?team_ID=<?= urlencode($r['team_ID']) ?>&period_ID=<?= urlencode($selectedPeriodId) ?>">
                查看結果
              </a>
            </td>
          </tr>
      <?php endforeach;
      endif; ?>
    </tbody>
  </table>

