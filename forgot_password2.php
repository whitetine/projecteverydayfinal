<?php
include "includes/pdo.php";

$account = $_POST['account'] ?? '';

if (!$account) {
    echo "請提供帳號";
    exit;
}

// 查詢資料庫是否有這個帳號
$sql = "SELECT * FROM userdata WHERE u_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$account]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "找不到此帳號";
    exit;
}

$to = $user['u_gmail'];   // 假設 email 存在這個欄位
$password = $user['u_password'];  // 直接寄原始密碼（⚠ 不建議 plaintext 密碼設計）

// 寄信（用妳之前的 Google App Script API）
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
$result = file_get_contents($url, false, $context);

if ($result) {
    echo "ok";
} else {
    echo "寄信失敗，請稍後再試";
}
