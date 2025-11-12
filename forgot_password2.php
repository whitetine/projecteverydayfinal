<?php
session_start();
include "includes/pdo.php";

$message = '';
$messageType = ''; // 'success' or 'error'

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = $_POST['account'] ?? '';
    
    if (!$account) {
        $message = '請提供帳號';
        $messageType = 'error';
    } else {
        // 查詢資料庫是否有這個帳號
        $sql = "SELECT * FROM userdata WHERE u_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$account]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $message = '找不到此帳號';
            $messageType = 'error';
        } else {
            $to = $user['u_gmail'];
            $password = $user['u_password'];
            
            // 寄信（用之前的 Google App Script API）
            $url = "https://script.google.com/macros/s/AKfycby-KZRj7ceUxw4QadRbASpsgrj4xtz8wnzR-jARhzUchU7aUlo4U-K0ULZq-u4HGXE/exec";
            
            $data = [
                'to' => $to,
                'subject' => '您的密碼查詢',
                'message' => "您的帳號：$account\n您的密碼為：$password"
            ];
            
            $options = [
                "http" => [
                    "method" => "POST",
                    "header" => "Content-type: application/x-www-form-urlencoded",
                    "content" => http_build_query($data)
                ]
            ];
            
            $context = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
            
            if ($result) {
                $message = '密碼已成功寄送至您的註冊信箱，請查收！';
                $messageType = 'success';
            } else {
                $message = '寄信失敗，請稍後再試或聯絡系統管理員';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼 - 專題日總彙</title>
    <?php include "head.php"; ?>
    <link rel="stylesheet" href="css/login.css?v=<?= time() ?>">
    <style>
        .forgot-password-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a2e 50%, #16213e 100%);
            color: #fff;
        }

        .forgot-form-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
        }

        .forgot-form {
            background: rgba(255, 255, 255, .08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .35);
            transition: .3s;
        }

        .forgot-form:hover {
            transform: translateY(-2px);
        }

        .forgot-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #fff;
            letter-spacing: .12em;
            font-weight: 600;
        }

        .forgot-subtitle {
            text-align: center;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, .7);
        }

        .forgot-input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .forgot-input-group label {
            display: block;
            margin-bottom: .5rem;
            font-size: .9rem;
            color: rgba(255, 255, 255, .85);
        }

        .forgot-input-group input {
            width: 100%;
            padding: .75rem .9rem;
            border: 1px solid rgba(255, 255, 255, .3);
            border-radius: 10px;
            background: rgba(255, 255, 255, .10);
            color: #fff;
            font-size: 1rem;
            transition: border-color .3s;
        }

        .forgot-input-group input:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 10px rgba(74, 144, 226, .28);
        }

        .forgot-input-group input::placeholder {
            color: rgba(255, 255, 255, .5);
        }

        .forgot-submit-btn {
            width: 100%;
            padding: .9rem;
            background: linear-gradient(45deg, #4a90e2, #357abd);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: .25s;
            margin-top: .5rem;
            font-weight: 500;
        }

        .forgot-submit-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, .4);
        }

        .forgot-submit-btn:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .forgot-message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: .9rem;
        }

        .forgot-message.success {
            background: rgba(76, 175, 80, .2);
            border: 1px solid rgba(76, 175, 80, .5);
            color: #81c784;
        }

        .forgot-message.error {
            background: rgba(244, 67, 54, .2);
            border: 1px solid rgba(244, 67, 54, .5);
            color: #e57373;
        }

        .forgot-back-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .85rem;
        }

        .forgot-back-link a {
            color: #93c5fd;
            text-decoration: none;
            transition: color .3s;
        }

        .forgot-back-link a:hover {
            color: #60a5fa;
            text-decoration: underline;
        }

        .forgot-icon {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #4a90e2;
        }
    </style>
</head>
<body id="indexbody">
    <!-- 背景效果 -->
    <div id="techbg-host"
         class="position-fixed top-0 start-0 w-100 h-100"
         data-mode="login" data-speed="1.12" data-density="1.35"
         data-contrast="bold"
         style="z-index:0; pointer-events:none;"></div>

    <!-- 額外波浪效果 -->
    <div class="fx-background">
        <div class="wave wave1"></div>
        <div class="wave wave2"></div>
        <div class="wave wave3"></div>
    </div>

    <div class="forgot-password-container">
        <div class="forgot-form-overlay">
            <form class="forgot-form" method="POST" action="">
                <div class="forgot-icon">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h1 class="forgot-title">忘記密碼</h1>
                <p class="forgot-subtitle">請輸入您的帳號，我們將寄送密碼至您的註冊信箱</p>

                <?php if ($message): ?>
                    <div class="forgot-message <?= $messageType ?>">
                        <i class="fa-solid <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="forgot-input-group">
                    <label for="account">
                        <i class="fa-solid fa-user me-2"></i>帳號
                    </label>
                    <input 
                        type="text" 
                        id="account" 
                        name="account" 
                        placeholder="請輸入您的帳號" 
                        required 
                        autofocus
                        value="<?= htmlspecialchars($_POST['account'] ?? '') ?>"
                    >
                </div>

                <button type="submit" class="forgot-submit-btn">
                    <i class="fa-solid fa-paper-plane me-2"></i>送出
                </button>

                <div class="forgot-back-link">
                    <a href="index.php">
                        <i class="fa-solid fa-arrow-left me-2"></i>返回登入頁面
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
