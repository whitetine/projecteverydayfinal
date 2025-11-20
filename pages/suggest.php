
<?php
session_start();
if (!isset($_SESSION['u_ID'])) {
    echo "<script>alert('請先登入');location.href='../index.php';</script>";
    exit;
}
?>
<!-- === 建議系統 === -->
<div class="suggest-wrapper">

    <div class="suggest-header">
        <h4 class="suggest-title">期中期末建議</h4>
        <p class="suggest-subtitle">請選擇屆別和類組後填寫各團隊的審查建議</p>
    </div>

    <!-- 篩選選單 -->
    <div class="suggest-filter-box">
        <div class="suggest-cohort-box">
            <label>選擇屆別</label>
            <select id="sg-cohort" class="form-select"></select>
        </div>
        <div class="suggest-group-box">
            <label>選擇類組</label>
            <div class="suggest-group-controls">
                <select id="sg-group" class="form-select" disabled>
                    <option value="">請先選擇屆別</option>
                </select>
                <button id="sg-export-btn" class="sg-btn-export" disabled>
                    <span>匯出</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 團隊列表 -->
    <div id="sg-team-list"></div>

</div>

<!-- 引入 JS / CSS -->
<link rel="stylesheet" href="css/suggest.css">
<script src="js/suggest.js"></script>
