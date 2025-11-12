<?php
$user_ID = $_SESSION['u_ID'] ?? null;
$user_img = $_SESSION['u_img'] ?? null;
$user_name = $_SESSION['u_name'] ?? null;
$role_name = $_SESSION['role_name'] ?? null;
$role_ID = $_SESSION['role_ID'] ?? null;


    if (!isset($_SESSION['u_ID'])) {
      echo "<script>alert('請先登入!');location.href='index.php';</script>";
      exit;
    }
?>

<div class="sb-sidenav-menu">
<!-- 使用者選單（點一下整塊就會跳出像圖一的選單） -->
<div class="nav-link dropdown user-menu dropend w-100 px-2 mt-2">
  <!-- 觸發按鈕：把原本的使用者卡改成 button -->
  <button class="nav-link w-100 border-0 d-flex align-items-center gap-2 p-2 rounded sidebar-user-trigger"
          id="userMenuBtn"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          aria-expanded="false">
    <?php if (!empty($user_img)): ?>
      <img src="headshot/<?= htmlspecialchars($user_img) ?>"
           width="32" height="32"
           class="rounded-circle shadow-sm" style="object-fit:cover;">
    <?php else: ?>
      <img src="https://cdn-icons-png.flaticon.com/512/1144/1144760.png"
           width="32" height="32"
           class="rounded-circle shadow-sm" style="object-fit:cover;" alt="User">
    <?php endif; ?>

    <span class="fw-semibold text-truncate" style="max-width:120px;">
      <?= htmlspecialchars($user_name ?: '未登入') ?>
    </span>
    <span class="ms-auto small opacity-75"><?= htmlspecialchars($role_name ?: '無') ?></span>
  </button>

  <!-- 主選單（像圖一） -->
  <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 py-2" aria-labelledby="userMenuBtn">
    <!-- 上方帳號資訊（可放 email 或學號） -->
    <li class="px-3 pb-2 small text-muted">
      <?= htmlspecialchars($_SESSION['u_gmail'] ?? ($_SESSION['u_ID'] ?? '')) ?>
    </li>

<li>
  <a class="dropdown-item ajax-link" href="pages/user_profile.php">
    <i class="fa-solid fa-address-card me-2"></i> 個人資料
  </a>
</li>
<li>
  <a class="dropdown-item ajax-link" href="pages/admin_notify.php">
    <i class="fa-solid fa-bell me-2"></i> 公告管理
  </a>
</li>

    <!-- <li><a class="dropdown-item ajax-link" href="pages/admin_usermanage.php">
      <i class="fa-solid fa-users me-2"></i> 帳號管理
    </a></li> -->

    <li><hr class="dropdown-divider"></li>

    <!-- 說明（像圖二：次選單） -->
    <li class="dropend dropdown-submenu">
      <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">
        <i class="fa-solid fa-circle-question me-2"></i> 說明
      </a>
      
      <ul class="dropdown-menu shadow border-0 rounded-3 py-2">
        <li><a class="dropdown-item" href="#" target="_blank">說明中心</a></li>
        <li><a class="dropdown-item ajax-link" href="pages/changelog.php">版本說明</a></li>
        <li><a class="dropdown-item" href="terms.html" target="_blank">條款及政策</a></li>
        <li><a class="dropdown-item ajax-link" href="pages/bug_report.php">報告錯誤</a></li>
        <li><a class="dropdown-item" href="https://example.com/app" target="_blank">下載應用程式</a></li>
        <li><a class="dropdown-item ajax-link" href="pages/shortcuts.php">鍵盤快捷鍵</a></li>
      </ul>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li><a class="dropdown-item text-danger" href="index.php">
      <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> 登出
    </a></li>
  </ul>
</div>


  <?php if ($role_ID == 2): ?>

    <!-- 功能選單 -->
    <a class="nav-link ajax-link" href="pages/admin_usermanage.php">
      <i class="fa-solid fa-user"></i><span>帳號管理</span>
    </a>
    <a class="nav-link ajax-link" href="pages/group_manage.php">
      <i class="fa-solid fa-table-cells"></i><span>類組管理</span>
    </a>
       <!-- <a class="nav-link ajax-link" href="pages/admin_file.php">
      <i class="fa-solid fa-file-lines"></i><span>文件管理</span></a> -->
 <a class="nav-link ajax-link" href="#">
      <i class="fa-solid fa-envelope"></i><span>最新消息</span></a>
       <a class="nav-link ajax-link" href="pages/file.php">
      <i class="fa-solid fa-folder"></i><span>文件管理(更新)</span></a>
       <a class="nav-link ajax-link" href="pages/apply.php">
      <i class="fa-solid fa-file-lines"></i><span>申請文件上傳</span></a>
       <a class="nav-link ajax-link" href="pages/teacher_review_status.php">
      <i class="fa-solid fa-star-half-alt"></i><span>互評(status)</span></a>
          <a class="nav-link ajax-link" href="pages/work_draft.php">
      <i class="fa-solid fa-file-lines"></i><span>work_draft</span></a>
          <a class="nav-link ajax-link" href="pages/work_save.php">
      <i class="fa-solid fa-file-lines"></i><span>work_save</span></a>

          <a class="nav-link ajax-link" href="pages/work_form.php">
      <i class="fa-solid fa-pen-to-square"></i><span>work_form</span></a>

          <a class="nav-link ajax-link" href="pages/apply_preview.php">
      <i class="fa-solid fa-pen-to-square"></i><span>apply_preview</span></a>
       <!-- <a class="nav-link ajax-link" href="pages/admin_notify.php">
      <i class="fa-solid fa-file-lines"></i><span>notify</span></a> -->
      <?php elseif ($role_ID == 6): ?>
 <a class="nav-link ajax-link" href="pages/apply.php">
      <i class="fa-solid fa-file-lines"></i><span>文件管理</span>
    </a>

    
          <a class="nav-link ajax-link" href="pages/work_form.php">
      <i class="fa-solid fa-file-lines"></i><span>work_form</span></a>
    <?php elseif ($role_ID == 4): ?>
      
       <a class="nav-link ajax-link" href="pages/teacher_review_status.php">
      <i class="fa-solid fa-file-lines"></i><span>互評(status)</span></a>
          <a class="nav-link ajax-link" href="pages/teacher_review_detail.php">
      <i class="fa-solid fa-file-lines"></i><span>互評(detail)</span></a>
    <?php endif; ?>
   
</div>