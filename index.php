<!DOCTYPE html>
<html lang="zh-Hant">
<?php
session_start();
$_SESSION = [];
?>
<head name="app-base" content="/myprojecteverydaysforlasttest/">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>專題日總彙</title>

  <?php include "head.php"; ?> <!-- 確保這裡有 Vue 3 (global)、Bootstrap 5、Font Awesome（可選）、SweetAlert2（可選） -->
  <!-- <link rel="stylesheet" href="css/main.css?v=<?= time() ?>" /> -->
  <link rel="stylesheet" href="css/login.css?v=<?= time() ?>" />
</head>

<body id="indexbody">
  <!-- 你的墨色科技背景（原本的） -->
  <div id="techbg-host"
       class="position-fixed top-0 start-0 w-100 h-100"
       data-mode="login" data-speed="1.12" data-density="1.35"
       data-contrast="bold"
       style="z-index:0; pointer-events:none;"></div>

  <!-- Vue 根節點 -->
  <div id="app"></div>

  <!-- 在 #app 外載入 -->
  <!-- <script src="js/breeze-ink-bg.js"></script> -->
  <script src="js/login.js?v=<?= time() ?>"></script>
</body>
</html>
