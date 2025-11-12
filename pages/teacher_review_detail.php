<?php
session_start();
require '../includes/pdo.php';



$u_ID      = $_SESSION['u_ID'];
$t_ID   = isset($_GET['team_ID'])   ? (int)$_GET['team_ID']   : 0;
$period_ID = isset($_GET['period_ID']) ? (int)$_GET['period_ID'] : 0;
// if ($t_ID <= 0) die('缺少或錯誤的 team_ID');

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//只允許啟用中的指導老師 (role_ID=4) 
$stRole = $conn->prepare("SELECT COUNT(*) FROM userrolesdata WHERE u_ID=? AND (role_ID=4 or role_ID=2) AND user_role_status=1");
$stRole->execute([$u_ID]);
if (!$stRole->fetchColumn()) {
  echo "<script>alert('此頁僅限指導老師查看');location.href='main.php';</script>";
  exit;
}

//驗證老師是否加入此組
$chk = $conn->prepare("
  SELECT COUNT(*)
  FROM teammember tm
  JOIN userrolesdata ur ON ur.u_ID = tm.u_ID
  WHERE tm.team_ID=:tid AND tm.u_ID=:uid AND ( ur.role_ID=4 or ur.role_ID = 2) AND ur.user_role_status=1
");
$chk->execute([':tid'=>$t_ID, ':uid'=>$u_ID]);
if (!$chk->fetchColumn()) {
  echo "<script>alert('你沒有權限查看此組別');location.href='teacher_review_status.php';</script>";
  exit;
}

//組名
$stName = $conn->prepare("
  SELECT COALESCE(team_project_name, CONCAT('Team', :tid)) AS team_name
  FROM teamdata WHERE team_ID=:tid LIMIT 1
");
$stName->execute([':tid'=>$t_ID]);
$teamName = $stName->fetchColumn();
if ($teamName === false) $teamName = 'Team'.$tt_IDeamId;

//期間：沒帶 period_ID 就抓啟用中的週；再不然抓最新一週 
if ($period_ID <= 0) {
  $active = $conn->query("
    SELECT period_ID, period_title, period_start_d, period_end_d
    FROM reviewperiods WHERE is_active=1
    ORDER BY period_start_d DESC LIMIT 1
  ")->fetch(PDO::FETCH_ASSOC);
  if ($active) {
    $period_ID    = (int)$active['period_ID'];
    $periodTitle = $active['period_title'];
    $periodRange = $active['period_start_d'].' ～ '.$active['period_end_d'];
  } else {
    $latest = $conn->query("
      SELECT period_ID, period_title, period_start_d, period_end_d
      FROM reviewperiods ORDER BY period_start_d DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($latest) {
      $period_ID    = (int)$latest['period_ID'];
      $periodTitle = $latest['period_title'];
      $periodRange = $latest['period_start_d'].' ～ '.$latest['period_end_d'];
    } else {
      $period_ID=0; $periodTitle='（尚無資料）'; $periodRange='';
    }
  }
} else {
  $stP = $conn->prepare("SELECT period_title, period_start_d, period_end_d FROM reviewperiods WHERE period_ID=?");
  $stP->execute([$period_ID]);
  $p = $stP->fetch(PDO::FETCH_ASSOC);
  $periodTitle = $p ? $p['period_title'] : '指定期間';
  $periodRange = $p ? ($p['period_start_d'].' ～ '.$p['period_end_d']) : '';
}

//本組啟用中的學生名單（統計只看學生）
$stStudents = $conn->prepare("
  SELECT u.u_ID, u.u_name
  FROM teammember tm
  JOIN userdata u ON tm.u_ID = u.u_ID
  JOIN userrolesdata ur ON ur.u_ID = u.u_ID
  WHERE tm.team_ID=? AND ur.role_ID=6 AND ur.user_role_status=1
  ORDER BY u.u_ID
");
$stStudents->execute([$t_ID]);
$students   = $stStudents->fetchAll(PDO::FETCH_KEY_PAIR);
$studentIds = array_keys($students);
$N          = count($students);

//取本期評分（同隊；不再用角色過濾，避免看不到） 
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
$st->execute([':tid'=>$t_ID, ':pid'=>$period_ID]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

//準備矩陣（分數 / 評論）與統計
$scoreMatrix = []; $commMatrix = [];
$didReview = []; $recvSum = []; $recvCnt = [];
foreach ($studentIds as $a){
  foreach ($studentIds as $b){ $scoreMatrix[$a][$b]=null; $commMatrix[$a][$b]=null; }
  $didReview[$a]=0; $recvSum[$a]=0; $recvCnt[$a]=0;
}
foreach ($rows as $r){
  $a=$r['from_id']; $b=$r['to_id'];
  if (!isset($scoreMatrix[$a]) || !array_key_exists($b,$scoreMatrix[$a])) continue; // 只記學生↔學生
  $scoreMatrix[$a][$b]=(int)$r['score'];
  $commMatrix[$a][$b]=(string)($r['review_comment']??'');
  $didReview[$a]++; $recvSum[$b]+=(int)$r['score']; $recvCnt[$b]++;
}
$avg=[]; foreach ($studentIds as $sid){ $avg[$sid]=$recvCnt[$sid]?round($recvSum[$sid]/$recvCnt[$sid],2):null; }

$reviewersDistinct=0; foreach ($studentIds as $sid){ if ($didReview[$sid]>0) $reviewersDistinct++; }
$expected=$N; $actual=$reviewersDistinct; $isComplete=($expected>0 && $actual===$expected);

//欄寬：固定，確保分數/評論兩種檢視欄寬一致
$firstPct = 18;   //第一欄（評分人）
$lastPct  = 10;   //最後一欄（已評數）
$midPct   = ($N > 0) ? (100 - $firstPct - $lastPct) / $N : (100 - $firstPct - $lastPct);
$midPct   = max(3, $midPct); //不要太窄

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<title>互評結果 - <?= h($teamName) ?></title>
<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<style>
  .table-matrix{ table-layout:fixed; width:100%; }
  .table-matrix th, .table-matrix td { text-align:center; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; }
  .cell-self { background:#f6f6f6; }
  .nowrap { white-space:nowrap; }
  .sticky-head th{ position:sticky; top:0; background:#f8f9fa; z-index:2; }

  /*檢視模式：預設只顯示分數；切到評論模式就只顯示評論 */
  .cell-score{ display:block; font-weight:600; }
  .cell-comment{
    display:none;
    text-align:left;
    max-height:48px;       /*超過就出現捲軸（可調） */
    overflow:auto;
    white-space:pre-wrap;
    word-break:break-word;
    padding:.25rem;
  }
  .comment-mode .cell-score{ display:none; }
  .comment-mode .cell-comment{ display:block; }
</style>
</head>
<body class="p-4" id="page">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h4 class="mb-1">組別：<?= h($teamName) ?>（ID: <?= h($t_ID) ?>）</h4>
      <div class="text-muted">期間：<?= h($periodTitle) ?> <?= $periodRange ? '（'.h($periodRange).'）' : '' ?></div>
    </div>
    <div class="text-end">
      <div class="mb-2">
        <span class="badge bg-secondary me-1">學生數：<?= $N ?></span>
        <span class="badge bg-info text-dark me-1">本週已評分學生數：<?= $actual ?></span>
        <span class="badge <?= $isComplete ? 'bg-success' : 'bg-danger' ?>"><?= $isComplete ? '已完成' : '未完成' ?></span>
      </div>
      <button id="toggleView" class="btn btn-sm btn-outline-dark me-2">顯示評論</button>
      <a class="btn btn-outline-secondary btn-sm" href="../../main.php#pages/teacher_review_status.php?period_ID=<?= h((string)$period_ID) ?>">回列表</a>
    </div>
  </div>

  <!-- 矩陣（欄寬固定，分數與評論兩種檢視寬度一致） -->
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-sm table-matrix sticky-head">
      <!-- 固定欄寬 -->
      <colgroup>
        <col style="width: <?= number_format($firstPct,2) ?>%;">
        <?php foreach ($studentIds as $_): ?>
          <col style="width: <?= number_format($midPct,2) ?>%;">
        <?php endforeach; ?>
        <col style="width: <?= number_format($lastPct,2) ?>%;">
      </colgroup>

      <thead class="table-light">
        <tr>
          <th class="nowrap">評分人 \ 被評人</th>
          <?php foreach ($studentIds as $sid): ?>
            <th class="nowrap">
              <?= h($students[$sid]) ?><br>
              <small class="text-muted"><?= h($sid) ?></small>
            </th>
          <?php endforeach; ?>
          <th>已評數</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($studentIds as $a): ?>
          <tr>
            <th class="text-start nowrap">
              <?= h($students[$a]) ?><br><small class="text-muted"><?= h($a) ?></small>
            </th>
            <?php foreach ($studentIds as $b): ?>
              <?php if ($a === $b): ?>
                <td class="cell-self">—</td>
              <?php else: ?>
                <?php $sc=$scoreMatrix[$a][$b]; $cm=$commMatrix[$a][$b]; ?>
                <td>
                  <div class="cell-score"><?= $sc!==null ? h($sc) : '' ?></div>
                  <div class="cell-comment"><?= ($cm!==null && $cm!=='') ? nl2br(h($cm)) : '' ?></div>
                </td>
              <?php endif; ?>
            <?php endforeach; ?>
            <td><strong><?= (int)$didReview[$a] ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- 被評平均分（本週） -->
  <h5 class="mt-3">被評平均分（本週）</h5>
  <table class="table table-bordered table-sm w-auto">
    <thead class="table-light"><tr><th>學生</th><th>平均分</th><th>被評次數</th></tr></thead>
    <tbody>
      <?php foreach ($studentIds as $sid): ?>
        <tr>
          <td class="nowrap"><?= h($students[$sid]) ?>（<?= h($sid) ?>）</td>
          <td><?= $avg[$sid]!==null ? h($avg[$sid]) : '—' ?></td>
          <td><?= (int)$recvCnt[$sid] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- 未完成清單 -->
  <div class="row mt-4">
    <div class="col-md-6">
      <h6>本週尚未評分的學生</h6>
      <ul class="mb-0">
        <?php
          $noReviewers = array_filter($studentIds, fn($x)=>$didReview[$x]==0);
          if (!$noReviewers) echo '<li class="text-muted">（無）</li>';
          foreach ($noReviewers as $sid) echo '<li>'.h($students[$sid]).'（'.h($sid).'）</li>';
        ?>
      </ul>
    </div>
  </div>

  <script>
    //分數 <-> 評論 單一檢視切換（欄寬不變）
    (function(){
      const page = document.getElementById('page');
      const btn  = document.getElementById('toggleView');
      let commentMode = false; // 預設顯示分數

      function apply(){
        page.classList.toggle('comment-mode', commentMode);
        btn.textContent = commentMode ? '顯示分數' : '顯示評論';
      }
      btn.addEventListener('click', ()=>{ commentMode = !commentMode; apply(); });

      //可選：網址帶 ?view=comment 預設就進評論檢視
      const q = new URLSearchParams(location.search);
      if (q.get('view') === 'comment') commentMode = true;

      apply();
    })();
  </script>
</body>
</html>
