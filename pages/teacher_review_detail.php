<?php
// 嘗試從 URL 參數取得 team_ID 和 period_ID（可能為空，因為使用 hash 路由）
$teamId = isset($_GET['team_ID']) ? (int)$_GET['team_ID'] : 0;
$periodId = isset($_GET['period_ID']) ? (int)$_GET['period_ID'] : 0;
?>
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h4 class="mb-1" id="team-name"></h4>
      <div class="text-muted" id="period-info"></div>
    </div>
    <div class="text-end">
      <div class="mb-2" id="stat-badges"></div>

      <button id="toggleView" class="btn btn-sm btn-outline-dark me-2">顯示評論</button>

      <a class="btn btn-outline-secondary btn-sm"
         id="back-link"
         href="#">
        回列表
      </a>
    </div>
  </div>

  <!-- 評分矩陣 -->
  <div class="table-responsive mb-4" id="matrix-wrapper"></div>

  <!-- 被評平均分 -->
  <h5 class="mt-3">被評平均分（本週）</h5>
  <div id="avg-table-wrapper"></div>

  <!-- 未完成 -->
  <div class="row mt-4">
    <div class="col-md-6">
      <h6>本週尚未評分的學生</h6>
      <ul class="mb-0" id="no-review-list"></ul>
    </div>
  </div>
  
  <!-- 錯誤訊息區域 -->
  <div id="error-message" class="alert alert-danger m-3" style="display: none;"></div>

<script>
  // 從 URL hash 中解析參數（因為使用 hash 路由時，參數在 hash 中）
  function getUrlParams() {
    const hash = window.location.hash || '';
    const query = hash.split('?')[1];
    if (!query) return {};
    
    const params = {};
    query.split('&').forEach(param => {
      const [key, value] = param.split('=');
      if (key && value) {
        params[decodeURIComponent(key)] = decodeURIComponent(value);
      }
    });
    return params;
  }
  
  const urlParams = getUrlParams();
  const TEAM_ID = urlParams.team_ID ? parseInt(urlParams.team_ID) : <?= $teamId ?>;
  const PERIOD_ID = urlParams.period_ID ? parseInt(urlParams.period_ID) : <?= $periodId ?>;
  
  // 如果沒有團隊 ID，設置為 0，讓 JavaScript 處理錯誤
  if (!TEAM_ID || TEAM_ID <= 0) {
    window.TEAM_ID = 0;
    window.PERIOD_ID = PERIOD_ID || 0;
  } else {
    window.TEAM_ID = TEAM_ID;
    window.PERIOD_ID = PERIOD_ID || 0;
  }
</script>

<script src="js/teacher_review_detail.js"></script>


