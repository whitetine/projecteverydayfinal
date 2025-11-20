<?php
session_start();
if (!isset($_SESSION['u_ID'])) {
    echo "<script>alert('請先登入');location.href='../index.php';</script>";
    exit;
}

$role_ID = $_SESSION['role_ID'] ?? 0;
if ($role_ID != 6) {
    echo "<script>alert('此頁面僅限學生使用');location.href='../main.php';</script>";
    exit;
}

require_once __DIR__ . '/../includes/pdo.php';

$u_ID = $_SESSION['u_ID'];

// 檢查學生是否已有團隊
$hasTeam = false;
$currentTeam = null;

try {
    // 檢查 teammember 表結構（兼容兩種版本）
    $teamUserField = 'team_u_ID';
    $stmt = $conn->prepare("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $teamUserField = 'u_ID';
    }
    
    $sql = "SELECT t.team_ID, t.team_project_name, t.team_status, t.cohort_ID
            FROM teamdata t
            INNER JOIN teammember tm ON t.team_ID = tm.team_ID
            WHERE tm.{$teamUserField} = ? AND t.team_status = 1
            ORDER BY t.team_update_d DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$u_ID]);
    $currentTeam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentTeam) {
        $hasTeam = true;
    }
} catch (Exception $e) {
    // 如果查詢失敗，假設沒有團隊
    $hasTeam = false;
}

// 獲取當前屆別
$stmt = $conn->prepare("
    SELECT cohort_ID, cohort_name 
    FROM cohortdata 
    WHERE cohort_status = 1 
    ORDER BY cohort_ID DESC 
    LIMIT 1
");
$stmt->execute();
$currentCohort = $stmt->fetch(PDO::FETCH_ASSOC);
$cohort_ID = $currentCohort['cohort_ID'] ?? null;

// 檢查是否有已提交的申請（待審核或退件狀態）
// 狀態：1=待審核，2=退件，3=通過
// 包括：申請者本人 或 被申請的成員
$pendingApplication = null;
try {
    // 先查詢申請者本人的申請
    $stmt = $conn->prepare("
        SELECT tap_ID, tap_status, tap_update_d, tap_member
        FROM teamapply
        WHERE tap_u_ID = ? AND tap_status IN (1, 2)
        ORDER BY tap_update_d DESC
        LIMIT 1
    ");
    $stmt->execute([$u_ID]);
    $pendingApplication = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 如果沒有找到，檢查是否在 tap_member 中（被申請的成員）
    if (!$pendingApplication) {
        $stmt = $conn->prepare("
            SELECT tap_ID, tap_status, tap_update_d, tap_member
            FROM teamapply
            WHERE tap_status IN (1, 2)
            ORDER BY tap_update_d DESC
        ");
        $stmt->execute();
        $allApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allApplications as $app) {
            $member_ids = json_decode($app['tap_member'] ?? '[]', true);
            if (is_array($member_ids) && in_array($u_ID, $member_ids)) {
                $pendingApplication = $app;
                break;
            }
        }
    }
} catch (Exception $e) {
    // 忽略錯誤
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>專題申請表</title>
    <?php include "../head.php"; ?>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/team_apply.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body id="indexbody">
    <!-- 登入頁面背景效果 -->
    <div id="techbg-host"
         class="position-fixed top-0 start-0 w-100 h-100"
         data-mode="login" data-speed="1.12" data-density="1.35"
         data-contrast="bold"
         style="z-index:0; pointer-events:none;"></div>
    
    <!-- 額外波浪 + 粒子層 -->
    <div class="fx-background">
        <div class="wave wave1"></div>
        <div class="wave wave2"></div>
        <div class="wave wave3"></div>
        <div class="particles">
            <?php for ($i = 0; $i < 24; $i++): ?>
                <div class="particle" style="left: <?= rand(0, 100) ?>%; animation-delay: <?= rand(0, 5) ?>s;"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="team-apply-container">
        <?php if ($hasTeam): ?>
            <!-- 已有團隊，顯示提示 -->
            <div class="has-team-section">
                <div class="has-team-card">
                    <i class="fas fa-check-circle"></i>
                    <h2>您已有專題團隊</h2>
                    <p class="team-info">團隊名稱：<strong><?= htmlspecialchars($currentTeam['team_project_name']) ?></strong></p>
                    <p class="team-id">團隊ID：<?= htmlspecialchars($currentTeam['team_ID']) ?></p>
                    <a href="../main.php#pages/student_milestone.php" class="btn-back">返回里程碑頁面</a>
                </div>
            </div>
        <?php elseif ($pendingApplication): ?>
            <!-- 已提交的申請（唯讀顯示） -->
            <div class="apply-form-section" id="readonlyFormSection">
                <div class="form-header">
                    <h1><i class="fas fa-file-alt"></i> 專題申請表</h1>
                    <p class="form-subtitle">您已提交的申請資料（唯讀）</p>
                    <?php if ($pendingApplication['tap_status'] == 2): ?>
                        <div class="alert alert-warning" style="margin-top: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i> 您的申請已被退件，請重新填寫表單。
                        </div>
                    <?php elseif ($pendingApplication['tap_status'] == 1): ?>
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <i class="fas fa-clock"></i> 您的申請正在審核中，請耐心等待。
                        </div>
                    <?php elseif ($pendingApplication['tap_status'] == 3): ?>
                        <div class="alert alert-success" style="margin-top: 1rem;">
                            <i class="fas fa-check-circle"></i> 您的申請已通過審核。
                        </div>
                    <?php endif; ?>
                </div>

                <form id="teamApplyFormReadonly" class="readonly-form">
                    <!-- 唯讀表單內容（由 JavaScript 填充） -->
                    <div id="readonlyFormContent">
                        <!-- 指導老師 -->
                        <div class="form-group">
                            <label class="required">指導老師</label>
                            <input type="text" class="form-control" readonly id="readonly_teacher">
                        </div>

                        <!-- 團隊成員 -->
                        <div class="form-group">
                            <label class="required">團隊成員</label>
                            <div id="readonly_memberList" class="member-list"></div>
                        </div>

                        <!-- 專題名稱 -->
                        <div class="form-group">
                            <label class="required">專題名稱</label>
                            <input type="text" class="form-control" readonly id="readonly_project_name">
                        </div>

                        <!-- 申請表照片 -->
                        <div class="form-group">
                            <label class="required">專題申請表（紙本照片）</label>
                            <div class="image-upload-container">
                                <div id="readonly_imagePreview" class="image-preview" style="display: none;">
                                    <img id="readonly_previewImg" src="" alt="申請表照片">
                                </div>
                            </div>
                        </div>

                        <!-- 說明文字 -->
                        <div class="form-group">
                            <label>說明文字</label>
                            <textarea class="form-control" rows="4" readonly id="readonly_comment"></textarea>
                        </div>

                        <!-- 狀態資訊 -->
                        <div class="form-group">
                            <label>申請狀態</label>
                            <div id="readonly_status" class="status-badge"></div>
                        </div>

                        <!-- 返回按鈕 -->
                        <div class="form-actions">
                            <a href="../index.php" class="btn-back">
                                <i class="fas fa-sign-out-alt"></i> 登出
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- 申請表單 -->
            <div class="apply-form-section">
                <div class="form-header">
                    <h1><i class="fas fa-file-alt"></i> 專題申請表</h1>
                    <p class="form-subtitle">請填寫以下資訊以申請成立專題團隊</p>
                </div>

                <form id="teamApplyForm" enctype="multipart/form-data">
                    <!-- 指導老師 -->
                    <div class="form-group">
                        <label for="teacher_id" class="required">指導老師</label>
                        <select id="teacher_id" name="teacher_id" class="form-control" required>
                            <option value="">請選擇指導老師</option>
                        </select>
                        <small class="form-text">請選擇您的專題指導老師</small>
                    </div>

                    <!-- 團隊成員 -->
                    <div class="form-group">
                        <label class="required">團隊成員</label>
                        <div class="member-input-container">
                            <div class="member-input-wrapper">
                                <input type="text" 
                                       id="memberInput" 
                                       class="form-control member-input" 
                                       placeholder="輸入學號（例如：110534201）"
                                       autocomplete="off">
                                <button type="button" id="addMemberBtn" class="btn-add-member">
                                    <i class="fas fa-plus"></i> 新增
                                </button>
                            </div>
                            <small class="form-text">請輸入團隊成員的學號，系統會自動驗證並顯示姓名</small>
                        </div>
                        <div id="memberList" class="member-list"></div>
                    </div>

                    <!-- 專題名稱 -->
                    <div class="form-group">
                        <label for="project_name" class="required">專題名稱</label>
                        <input type="text" 
                               id="project_name" 
                               name="project_name" 
                               class="form-control" 
                               placeholder="請輸入專題名稱"
                               required
                               maxlength="100">
                        <small class="form-text">請輸入您的專題名稱</small>
                    </div>

                    <!-- 圖片上傳 -->
                    <div class="form-group">
                        <label for="apply_image" class="required">專題申請表（紙本照片）</label>
                        <div class="image-upload-container">
                            <input type="file" 
                                   id="apply_image" 
                                   name="apply_image" 
                                   class="form-control file-input" 
                                   accept="image/*"
                                   required>
                            <div id="imagePreview" class="image-preview" style="display: none;">
                                <img id="previewImg" src="" alt="預覽">
                                <button type="button" id="removeImageBtn" class="btn-remove-image">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text">請上傳專題申請表的紙本照片（JPG、PNG格式）</small>
                    </div>

                    <!-- 說明文字（可選） -->
                    <div class="form-group">
                        <label for="comment">說明文字（選填）</label>
                        <textarea id="comment" 
                                  name="comment" 
                                  class="form-control" 
                                  rows="4" 
                                  placeholder="如有其他需要說明的事項，請在此填寫"></textarea>
                    </div>

                    <!-- 提交按鈕 -->
                    <div class="form-actions">
                        <button type="submit" id="submitBtn" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> 提交申請
                        </button>
                        <button type="button" id="resetBtn" class="btn-reset">
                            <i class="fas fa-redo"></i> 重置表單
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.TEAM_APPLY_CONFIG = {
            u_ID: '<?= htmlspecialchars($u_ID) ?>',
            cohort_ID: <?= $cohort_ID ?? 'null' ?>,
            apiPath: '../api.php'
        };
    </script>
    <script src="../js/team_apply.js"></script>
</body>
</html>

