<?php
session_start();
if (!isset($_SESSION['u_ID'])) {
  echo '<div class="alert alert-warning m-4">請先登入</div>';
  exit;
}
?>
<div id="work-draft-page" class="container my-4">
  <h1 class="mb-4">我的日誌紀錄</h1>
  <!-- <a href="#work_form" data-page="work_form" class="btn btn-outline-secondary spa-link">回日誌填寫</a> -->
<a href="#pages/work_form.php" data-page="work_form" class="btn btn-outline-secondary spa-link">
  回日誌填寫
</a>
  <!-- 篩選列 -->
  <form id="draft-filter" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3">
      <div>
        <label class="form-label mb-1">起始日期</label>
        <input type="date" name="from" class="form-control">
      </div>
      <div>
        <label class="form-label mb-1">結束日期</label>
        <input type="date" name="to" class="form-control">
      </div>
      <div class="ms-auto">
        <button type="submit" class="btn btn-primary">套用篩選</button>
      </div>
    </div>
  </form>

  <!-- 資料表 -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="draft-table">
          <thead class="table-light">
            <tr>
              <th>建立時間</th>
              <th>標題</th>
              <th>內容預覽</th>
              <th>附件</th>
              <th>狀態</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="pager-bar" id="draft-pager"></div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">日誌內容</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="vm-meta" class="mb-2 text-muted"></div>
        <h5 id="vm-title" class="mb-3"></h5>
        <pre id="vm-content" class="mb-3"></pre>
        <div id="vm-file"></div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="css/pages/work-draft.css">
<script src="../js/work-draft.js"></script>
