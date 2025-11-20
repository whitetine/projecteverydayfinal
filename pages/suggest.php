
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
        <p class="suggest-subtitle">請選擇屆別後填寫各團隊的審查建議</p>
    </div>

    <!-- 屆別選單 -->
    <div class="suggest-cohort-box">
        <label>選擇屆別</label>
        <select id="sg-cohort" class="form-select"></select>
    </div>

    <!-- 團隊列表 -->
    <div id="sg-team-list"></div>

</div>

<!-- 引入 JS / CSS -->
<link rel="stylesheet" href="css/suggest.css">
<script src="js/suggest.js"></script>
