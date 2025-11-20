<?php
session_start();
if (!isset($_SESSION['u_ID'])) {
  echo "<script>alert('請先登入');location.href='index.php';</script>";
  exit;
}
?>
<!doctype html>
<html lang="zh-Hant">

<head>
  <meta charset="utf-8">
  <title>我的組別互評完成狀態</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/teacher_review_status.css">

</head>

<body class="p-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">我的組別互評完成狀態</h3>

    <!-- 標題選單 -->
    <form id="periodForm" class="d-flex align-items-center flex-nowrap">
      <label class="mb-0 me-2 text-muted text-nowrap">標題：</label>
      <select id="periodSelect" name="period_ID" class="form-select form-select-sm" style="min-width: 300px;"></select>
    </form>
  </div>

  <div id="periodInfo" class="text-end mb-2 small text-muted"></div>

  <table class="table table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th>組別</th>
        <th>應有筆數（學生數）</th>
        <th>已完成（本週已評分學生數）</th>
        <th>狀態</th>
        <th>動作</th>
      </tr>
    </thead>
    <tbody id="reviewStatusBody"></tbody>
  </table>

  <a class="btn btn-secondary" href="main.php">回首頁</a>

  <script src="js/teacher_review_status.js"></script>
</body>
</html>
