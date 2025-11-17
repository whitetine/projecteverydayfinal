<?php
session_start();
$sort = $_GET['sort'] ?? 'created';  
?>

  <h3 class="mb-3">評分時段管理</h3>

  <!-- 排序 -->
  <form method="get" class="mb-3">
    <label class="form-label">排序條件：</label>
    <select name="sort" onchange="this.form.submit()" class="form-select d-inline w-auto">
      <option value="created" <?= $sort==='created'?'selected':'' ?>>建立時間(預設)</option>
      <option value="start"   <?= $sort==='start'  ?'selected':'' ?>>開始日</option>
      <option value="end"     <?= $sort==='end'    ?'selected':'' ?>>結束日</option>
      <option value="active"  <?= $sort==='active' ?'selected':'' ?>>啟用狀態</option>
    </select>
  </form>

  <!-- 表單 -->
  <div class="card mb-4">
    <div class="card-body">
      <form id="periodForm" method="post" action="pages/checkreviewperiods_data.php" class="row g-3">
        <input type="hidden" name="action" id="form_action" value="create">
        <input type="hidden" name="period_ID" id="period_ID">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

        <div class="col-md-3">
          <label class="form-label">開始日</label>
          <input type="date" class="form-control" name="period_start_d" id="period_start_d" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">結束日</label>
          <input type="date" class="form-control" name="period_end_d" id="period_end_d" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">標題</label>
          <input type="text" class="form-control" name="period_title" id="period_title" required>
        </div>

        <!-- 屆別：由後端 AJAX 載入 -->
        <div class="col-md-3">
          <label class="form-label">屆別</label>
          <select class="form-select" name="cohort_ID" id="cohort_ID" required>
            <option value="">載入中...</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">指定團隊</label>
          <select class="form-select" name="pe_target_ID" id="team_ID" required>
            <option value="">請先選擇屆別</option>
          </select>
        </div>

        <div class="col-md-2 d-flex align-items-end">
          <!-- 真正的欄位：用於送出表單 -->
          <input class="form-check-input d-none" type="checkbox" name="pe_status" id="pe_status">
          <!-- UI 切換按鈕 -->
          <button type="button" id="pe_status_btn" class="btn btn-danger">停用</button>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit" id="submitBtn">新增</button>
          <button class="btn btn-secondary" type="button" onclick="resetForm()">清空</button>
        </div>
      </form>
    </div>
 

  <!-- 資料表 -->
  <div id="periodTable"></div>
</div>
<link rel="stylesheet" href="/css/checkreviewperiods.css">
<script src="/js/checkreviewperiods.js"></script>

