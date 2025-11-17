<?php
session_start();
$sort = $_GET['sort'] ?? 'created';  
?>

<h3 class="mb-3">評分時段管理</h3>

<!-- 模式選擇 -->
<div class="d-flex align-items-center gap-3 flex-wrap mb-3">
  <label class="form-label mb-0">模式：</label>
    <div class="dropdown">
    <button class="btn btn-primary dropdown-toggle" type="button" id="modeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      <span id="modeLabel">請選擇模式</span>
      </button>
      <ul class="dropdown-menu" aria-labelledby="modeDropdown">
      <li>
        <button class="dropdown-item mode-option" type="button" data-mode="in" data-hint="同一團隊成員彼此互評，適合隊內檢視。">
          團隊內互評
        </button>
      </li>
      <li>
        <button class="dropdown-item mode-option" type="button" data-mode="cross" data-hint="不同團隊之間互評，適合跨隊交流與評比。">
          團隊間互評
        </button>
      </li>
      </ul>
    </div>
  <input type="hidden" id="mode_value" value="in">
  <small class="text-muted" id="modeHint">請選擇模式以查看說明</small>
  </div>

  <!-- 表單 -->
  <div class="card mb-4">
    <div class="card-body">
      <form id="periodForm" method="post" action="pages/checkreviewperiods_data.php" class="row g-3">
        <input type="hidden" name="action" id="form_action" value="create">
        <input type="hidden" name="period_ID" id="period_ID">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">

        <div class="col-md-3">
          <label class="form-label">開始日</label>
          <input type="datetime-local" class="form-control" name="period_start_d" id="period_start_d" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">結束日</label>
          <input type="datetime-local" class="form-control" name="period_end_d" id="period_end_d" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">標題</label>
          <input type="text" class="form-control" name="period_title" id="period_title" required>
        </div>

        <!-- 屆別：自訂多選 -->
        <div class="col-md-3">
          <label class="form-label">屆別</label>
          <div class="cohort-dropdown" id="cohortDropdown">
            <button type="button" class="btn btn-outline-secondary cohort-btn w-100" id="cohortBtn">
              <span id="cohortLabel">請選擇屆別</span>
              <span class="chevron">&#9662;</span>
            </button>
            <div class="cohort-menu" id="cohortMenu">
              <div class="cohort-options" id="cohortOptions">
                <div class="text-muted small px-3 py-2">載入中...</div>
              </div>
            </div>
          </div>
          <div class="cohort-tags" id="cohortTags"></div>
          <small class="text-muted d-block mt-1">可多選，優先使用第一個屆別建立時段。</small>
          <input type="hidden" name="cohort_values" id="cohort_values" value="">
          <input type="hidden" name="cohort_primary" id="cohort_primary" value="">
        </div>
        <div class="col-md-3">
          <label class="form-label">指定團隊</label>
          <select class="form-select" name="pe_target_ID" id="team_ID" required>
            <option value="">請先選擇屆別</option>
          </select>
        </div>

        <input type="hidden" name="pe_status" id="pe_status" value="1">

        <div class="col-12">
          <button class="btn btn-primary" type="submit" id="submitBtn">新增</button>
          <button class="btn btn-secondary" type="button" onclick="resetForm()">清空</button>
        </div>
      </form>
    </div>
 

  <!-- 資料表 -->
  <div id="periodTable"></div>
</div>
<link rel="stylesheet" href="css/checkreviewperiods.css">
<script src="js/checkreviewperiods.js"></script>

