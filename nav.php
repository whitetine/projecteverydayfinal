<?php
// 确保 session 已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 使用正确的 session 变量名（与 modules/user.php 中设置的一致）
$user_name = $_SESSION['u_name'] ?? '未登入';
$user_img = $_SESSION['u_img'] ?? null;
$role_ID = $_SESSION['role_ID'] ?? null;
$role_name = $_SESSION['role_name'] ?? null;
$isAdmin = in_array($role_ID, [1, 2]);
?>
<nav class="navbar navbar-expand-lg fixed-top <?= $isAdmin ? 'navbar-dark admin-navbar' : 'navbar-light bg-light' ?>">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <button class="border-0 me-2 <?= $isAdmin ? 'text-white' : 'text-black' ?>" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            
            <a class="navbar-brand mb-0 <?= $isAdmin ? 'text-white' : 'text-black' ?>" href="main.php">
                <i class="fa-solid <?= $isAdmin ? 'fa-shield-halved' : 'fa-folder-open' ?> me-2"></i>專題系統
                <?php if ($isAdmin): ?>
                    <span class="badge bg-warning text-dark ms-2">管理員</span>
                <?php endif; ?>
            </a>
        </div>
        <div class="d-flex align-items-center">
            <form class="d-flex align-items-center gap-2 me-3 mb-0" role="search">
                <input class="form-control form-control-sm <?= $isAdmin ? 'bg-dark text-white border-secondary' : '' ?>" type="search" placeholder="搜尋" aria-label="搜尋" style="max-width: 200px;">
                <button class="btn btn-sm <?= $isAdmin ? 'btn-warning' : 'btn-secondary' ?>" type="submit">Search</button>
            </form>
            <div class="position-relative me-3" style="cursor:pointer;" onclick="$('#bell_box').modal('show')">
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="notificationCount" style="display: none;">0</span>
                <lord-icon src="https://cdn.lordicon.com/bpptgtfr.json"
                    trigger="hover"
                    colors="primary:<?= $isAdmin ? '#ffffff' : '#000000' ?>"
                    style="width:40px;height:40px">
                </lord-icon>
            </div>
            
            <!-- 使用者選單 -->
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle d-flex align-items-center <?= $isAdmin ? 'text-white' : 'text-dark' ?>" 
                        type="button" 
                        id="userMenuBtn" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false"
                        style="text-decoration: none; border: none; padding: 0.5rem;">
                    <?php if (!empty($user_img)): ?>
                        <img src="headshot/<?= htmlspecialchars($user_img) ?>"
                             width="32" height="32"
                             class="rounded-circle shadow-sm me-2" style="object-fit:cover;">
                    <?php else: ?>
                        <img src="https://cdn-icons-png.flaticon.com/512/1144/1144760.png"
                             width="32" height="32"
                             class="rounded-circle shadow-sm me-2" style="object-fit:cover;" alt="User">
                    <?php endif; ?>
                    <span class="fw-semibold"><?= htmlspecialchars($user_name ?: '未登入') ?></span>
                    <?php if ($role_name): ?>
                        <span class="ms-2 small opacity-75"><?= htmlspecialchars($role_name) ?></span>
                    <?php endif; ?>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 py-2" aria-labelledby="userMenuBtn">
                    <!-- 上方帳號資訊 -->
                    <li class="px-3 pb-2 small text-muted">
                        <?= htmlspecialchars($_SESSION['u_gmail'] ?? ($_SESSION['u_ID'] ?? '')) ?>
                    </li>
                    
                    <li>
                        <a class="dropdown-item ajax-link" href="#pages/user_profile.php">
                            <i class="fa-solid fa-address-card me-2"></i> 個人資料
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item ajax-link" href="#pages/admin_notify.php">
                            <i class="fa-solid fa-bell me-2"></i> 公告管理
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- 說明（次選單） -->
                    <li class="dropend dropdown-submenu">
                        <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-circle-question me-2"></i> 說明
                        </a>
                        <ul class="dropdown-menu shadow border-0 rounded-3 py-2">
                            <li><a class="dropdown-item" href="#" target="_blank">說明中心</a></li>
                            <li><a class="dropdown-item ajax-link" href="#pages/changelog.php">版本說明</a></li>
                            <li><a class="dropdown-item" href="terms.html" target="_blank">條款及政策</a></li>
                            <li><a class="dropdown-item ajax-link" href="#pages/bug_report.php">報告錯誤</a></li>
                            <li><a class="dropdown-item" href="https://example.com/app" target="_blank">下載應用程式</a></li>
                            <li><a class="dropdown-item ajax-link" href="#pages/shortcuts.php">鍵盤快捷鍵</a></li>
                        </ul>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <li>
                        <a class="dropdown-item text-danger" href="index.php">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> 登出
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>