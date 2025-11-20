<?php
/**
 * 角色選擇頁面
 * 當用戶有多個角色時，需要先選擇角色才能進入系統
 */
session_start();
require __DIR__ . '/../includes/pdo.php';

// 檢查是否已登入
if (!isset($_SESSION['u_ID'])) {
    echo '<script>alert("請先登入");location.href="index.php";</script>';
    exit;
}

// 如果已經有角色且只有一個角色，直接跳轉
if (isset($_SESSION['role_ID']) && !empty($_SESSION['role_ID'])) {
    echo '<script>location.href="main.php";</script>';
    exit;
}

$u_ID = $_SESSION['u_ID'];
$u_name = $_SESSION['u_name'] ?? '使用者';

// 獲取用戶的所有角色
$stmt = $conn->prepare("
    SELECT r.role_ID, r.role_name
    FROM userrolesdata ur
    JOIN roledata r ON ur.role_ID = r.role_ID
    WHERE ur.ur_u_ID = ? AND ur.user_role_status = 1
    ORDER BY r.role_ID
");
$stmt->execute([$u_ID]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果只有一個角色，直接設置並跳轉
if (count($roles) === 1) {
    $_SESSION['role_ID'] = $roles[0]['role_ID'];
    $_SESSION['role_name'] = $roles[0]['role_name'];
    echo '<script>location.href="main.php";</script>';
    exit;
}

// 如果沒有角色，顯示錯誤
if (count($roles) === 0) {
    echo '<div class="alert alert-danger">此帳號尚未設定任何角色</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>選擇登入身分</title>
    <?php include "../head.php"; ?>
    <link rel="stylesheet" href="../css/login.css?v=<?= time() ?>">
    <style>
        body {
            background: #0f0f0f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        /* 登入頁面背景效果 */
        #techbg-host {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        
        .fx-background {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }
        
        .wave {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 100px;
            background: linear-gradient(45deg, rgba(255,255,255,.08), rgba(100,149,237,.10));
            border-radius: 50% 50% 0 0;
            animation: wave 10s ease-in-out infinite;
        }
        
        .wave1 { animation-delay: 0s; opacity: .6; }
        .wave2 { animation-delay: 2s; opacity: .4; height: 120px; }
        .wave3 { animation-delay: 4s; opacity: .3; height: 80px; }
        
        @keyframes wave {
            0%, 100% { transform: translateX(0) translateY(0); }
            50% { transform: translateX(-25%) translateY(-20px); }
        }
        
        .particles {
            position: absolute;
            inset: 0;
            z-index: 2;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255,255,255,.5);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) scale(1); opacity: 0; }
        }
        .role-select-container {
            background: rgba(255, 255, 255, .08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .35);
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            position: relative;
            z-index: 3;
        }
        .role-select-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        .role-select-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        .role-select-title p {
            color: rgba(255, 255, 255, .85);
            font-size: 1rem;
        }
        .role-list {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .role-card {
            border: 2px solid rgba(255, 255, 255, .3);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(5px);
        }
        .role-card:hover {
            border-color: rgba(255, 255, 255, .5);
            background: rgba(255, 255, 255, .15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
        }
        .role-card.selected {
            border-color: #4a90e2;
            background: linear-gradient(135deg, rgba(74, 144, 226, .3) 0%, rgba(53, 122, 189, .3) 100%);
            color: white;
            box-shadow: 0 0 20px rgba(74, 144, 226, .4);
        }
        .role-card.selected .role-icon {
            color: #fff;
        }
        .role-card.selected .role-name {
            color: #fff;
        }
        .role-icon {
            font-size: 2.5rem;
            color: rgba(255, 255, 255, .9);
            margin-bottom: 1rem;
        }
        .role-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .95);
            margin: 0;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, #4a90e2, #357abd);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, .4);
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
        }
    </style>
</head>
<body id="indexbody">
    <!-- 登入頁面背景 -->
    <div id="techbg-host"
         class="position-fixed top-0 start-0 w-100 h-100"
         data-mode="login" data-speed="1.12" data-density="1.35"
         data-contrast="bold"
         style="z-index:0; pointer-events:none;"></div>
    
    <!-- 波浪和粒子效果 -->
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
    
    <div class="role-select-container">
        <div class="role-select-title">
            <h1>選擇登入身分</h1>
            <p>您好，<?= htmlspecialchars($u_name) ?>，請選擇您要使用的身分</p>
        </div>
        
        <div class="role-list" id="roleList">
            <?php foreach ($roles as $role): ?>
                <div class="role-card" 
                     data-role-id="<?= $role['role_ID'] ?>"
                     data-role-name="<?= htmlspecialchars($role['role_name']) ?>"
                     onclick="selectRole(this)">
                    <div class="role-icon">
                        <?php
                        // 根據角色ID顯示不同圖標
                        $icons = [
                            1 => 'fa-user-tie',      // 班導
                            2 => 'fa-user-shield',   // 主任
                            3 => 'fa-user-graduate', // 助教
                            4 => 'fa-chalkboard-teacher', // 指導老師
                            5 => 'fa-user',          // 其他
                            6 => 'fa-user-graduate'  // 學生
                        ];
                        $icon = $icons[$role['role_ID']] ?? 'fa-user';
                        ?>
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <h3 class="role-name"><?= htmlspecialchars($role['role_name']) ?></h3>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button class="btn-submit" id="submitBtn" onclick="submitRole()" disabled>
            <span class="loading" id="loading">
                <i class="fa-solid fa-spinner fa-spin me-2"></i>
            </span>
            <span>確認進入</span>
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedRole = null;

        function selectRole(element) {
            // 移除所有選中狀態
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // 添加選中狀態
            element.classList.add('selected');
            
            // 保存選中的角色
            selectedRole = {
                role_ID: element.dataset.roleId,
                role_name: element.dataset.roleName
            };
            
            // 啟用提交按鈕
            document.getElementById('submitBtn').disabled = false;
        }

        async function submitRole() {
            if (!selectedRole) {
                if (window.Swal) {
                    Swal.fire('提醒', '請選擇一個身分', 'warning');
                } else {
                    alert('請選擇一個身分');
                }
                return;
            }

            const btn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            btn.disabled = true;
            loading.classList.add('show');

            try {
                const formData = new FormData();
                formData.append('role_ID', selectedRole.role_ID);
                formData.append('role_name', selectedRole.role_name);

                const response = await fetch('../api.php?do=role_session', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data && data.ok === true) {
                    // 成功，跳轉到主頁面
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: '設定成功',
                            text: '正在進入系統...',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '../main.php';
                        });
                    } else {
                        window.location.href = '../main.php';
                    }
                } else {
                    const errorMsg = data.msg || '設定角色失敗，請重試';
                    if (window.Swal) {
                        Swal.fire('錯誤', errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    btn.disabled = false;
                    loading.classList.remove('show');
                }
            } catch (error) {
                console.error('錯誤:', error);
                const errorMsg = '發生錯誤，請重試';
                if (window.Swal) {
                    Swal.fire('錯誤', errorMsg, 'error');
                } else {
                    alert(errorMsg);
                }
                btn.disabled = false;
                loading.classList.remove('show');
            }
        }
    </script>
</body>
</html>

