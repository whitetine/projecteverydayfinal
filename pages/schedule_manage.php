<?php
session_start();
require '../includes/pdo.php';

// 檢查權限（科辦 role_ID = 2 或主任 role_ID = 1）
$role_ID = $_SESSION['role_ID'] ?? null;
if (!isset($role_ID) || !in_array($role_ID, [1, 2])) {
    echo "<script>alert('此頁面僅限主任和科辦使用');location.href='../main.php';</script>";
    exit;
}

// 獲取屆別列表
$cohorts = $conn->query("SELECT cohort_ID, cohort_name FROM cohortdata WHERE cohort_status = 1 ORDER BY cohort_ID DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
// 動態判斷路徑（支援直接訪問和 AJAX 載入）
// 當通過 AJAX 載入時，路徑應該相對於 main.php（在根目錄）
// 檢查是否為 AJAX 請求或從 main.php 載入
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isFromMain = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'main.php') !== false;
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
$isInPages = strpos($scriptPath, '/pages/') !== false;

// 如果是 AJAX 載入或從 main.php 載入，或確實在 pages 目錄下，使用相對路徑
// 預設使用相對路徑，因為這個文件在 pages 目錄下
$basePath = '../';
?>
<link rel="stylesheet" href="../css/schedule_manage.css?v=<?= time() ?>" id="scheduleManageCSS">

<div class="schedule-manage-container">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="page-title">專題期中審查報告時程表</h1>
        <div class="header-controls d-flex align-items-center gap-3 flex-wrap">
            <div class="cohort-selector">
                <label for="cohortSelect" class="me-2">選擇屆別：</label>
                <select id="cohortSelect" class="form-select" style="width: auto; display: inline-block;">
                    <option value="">請選擇屆別</option>
                    <?php foreach ($cohorts as $cohort): ?>
                        <option value="<?= $cohort['cohort_ID'] ?>"><?= htmlspecialchars($cohort['cohort_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="time-controls d-flex align-items-center gap-2">
                <div class="time-input-group">
                    <label for="startTime" class="me-2">場次準備開始時間：</label>
                    <input type="datetime-local" id="startTime" class="form-control" style="width: auto;">
                </div>
                <div class="time-input-group">
                    <label for="endTime" class="me-2">最後一組報告完成時間：</label>
                    <input type="datetime-local" id="endTime" class="form-control" style="width: auto;" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- 團隊時程表 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">專題期中審查報告時程表</h5>
        </div>
        <div class="card-body p-3">
            <div id="scheduleTableContainer">
                <!-- 紫色標題欄 -->
                <div class="table-header-bar">
                    <div class="header-cell">報告時間</div>
                    <div class="header-cell">組次</div>
                    <div class="header-cell">學號</div>
                    <div class="header-cell">姓名</div>
                    <div class="header-cell">專題題目</div>
                    <div class="header-cell">指導老師</div>
                </div>
                <table class="table mb-0" id="scheduleTable">
                    <thead style="display: none;">
                        <tr>
                            <th>報告時間</th>
                            <th>組次</th>
                            <th>學號</th>
                            <th>姓名</th>
                            <th>專題題目</th>
                            <th>指導老師</th>
                        </tr>
                    </thead>
                    <tbody id="scheduleTableBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                請選擇屆別以載入團隊資料
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// 動態設置 CSS 和 JS 路徑（解決 AJAX 載入時的相對路徑問題）
(function() {
    // 獲取當前頁面的基礎路徑
    const getBasePath = function() {
        const path = window.location.pathname;
        // 如果路徑包含 /main.php，則基礎路徑是 main.php 所在的目錄
        if (path.includes('/main.php')) {
            return path.substring(0, path.indexOf('/main.php') + 1);
        }
        // 如果路徑包含 /pages/，則基礎路徑是 pages 的上一層
        if (path.includes('/pages/')) {
            return path.substring(0, path.indexOf('/pages/') + 1);
        }
        // 預設使用相對路徑
        return '../';
    };
    
    const basePath = getBasePath();
    console.log('計算的基礎路徑:', basePath);
    
    // 更新 CSS 路徑
    const cssLink = document.getElementById('scheduleManageCSS');
    if (cssLink) {
        const cssPath = basePath + (basePath.endsWith('/') ? '' : '/') + 'css/schedule_manage.css?v=<?= time() ?>';
        cssLink.href = cssPath;
        console.log('CSS 路徑:', cssPath);
    }
    
    // 動態載入 JS
    const script = document.createElement('script');
    const jsPath = basePath + (basePath.endsWith('/') ? '' : '/') + 'js/schedule_manage.js?v=<?= time() ?>';
    script.src = jsPath;
    console.log('JS 路徑:', jsPath);
    script.onerror = function() {
        console.error('無法載入 schedule_manage.js，路徑:', jsPath);
    };
    script.onload = function() {
        console.log('schedule_manage.js 載入成功');
    };
    document.head.appendChild(script);
})();
</script>

