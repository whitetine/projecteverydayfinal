<?php
session_start();
require "../includes/pdo.php";
header("Content-Type: application/json; charset=utf-8");

date_default_timezone_set("Asia/Taipei");

/* ==========================================
   權限：僅科辦 (role_ID = 2)
========================================== */
if (!isset($_SESSION["role_ID"]) || $_SESSION["role_ID"] != 2) {
    echo json_encode(["success" => false, "msg" => "無權限"]);
    exit;
}

$u_ID = $_SESSION["u_ID"];
$action = $_GET["action"] ?? $_POST["action"] ?? "";

/* 回傳格式統一 */
function respond($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

/* PDO 錯誤 */
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ==========================================
   函式：整理多行文字 → 多筆建議
========================================== */
function normalize_multi_line($text) {
    $lines = preg_split("/\r\n|\r|\n/", $text);
    $result = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "") continue;

        // 只剩數字 / 點 / 空白 → 略過
        if (preg_match('/^[0-9\.\s]+$/', $line)) continue;

        // 去掉前面編號 1. 2 3) ...
        $line = preg_replace('/^\s*\d+[\.\、\)\:]\s*/u', '', $line);

        if ($line === "") continue;

        // 結尾沒有標點 → 自動補 「。」
        $last = mb_substr($line, -1);
        if (!in_array($last, ["。", ".", "?", "？", "！", "!"])) {
            $line .= "。";
        }

        $result[] = $line;
    }

    return $result;  // 回傳陣列，每個是「一筆建議」
}

/* ==========================================
   action: listCohorts
   取得啟用中屆別
========================================== */
if ($action === "listCohorts") {

    $sql = "SELECT cohort_ID, cohort_name
            FROM cohortdata
            WHERE cohort_status = 1
            ORDER BY cohort_ID DESC";

    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    respond(["success" => true, "data" => $rows]);
}

/* ==========================================
   action: listGroups
   取得該屆的類組列表
========================================== */
if ($action === "listGroups") {

    $cohort_ID = $_GET["cohort_ID"] ?? 0;
    
    if (!$cohort_ID) {
        respond(["success" => false, "msg" => "缺少屆別參數"]);
    }

    $sql = "SELECT DISTINCT 
                g.group_ID,
                g.group_name
            FROM groupdata g
            JOIN teamdata t ON t.group_ID = g.group_ID
            WHERE t.cohort_ID = ?
              AND t.team_status = 1
              AND g.group_status = 1
            ORDER BY g.group_ID";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$cohort_ID]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(["success" => true, "data" => $rows]);
}

/* ==========================================
   action: listTeams
   取得該屆和類組的團隊
========================================== */
if ($action === "listTeams") {

    $cohort_ID = $_GET["cohort_ID"] ?? 0;
    $group_ID = $_GET["group_ID"] ?? 0;

    if (!$cohort_ID || !$group_ID) {
        respond(["success" => false, "msg" => "缺少參數"]);
    }

    $sql = "SELECT 
                t.team_ID,
                t.team_project_name,
                g.group_name
            FROM teamdata t
            JOIN groupdata g ON t.group_ID = g.group_ID
            WHERE t.cohort_ID = ?
              AND t.group_ID = ?
              AND t.team_status = 1
            ORDER BY t.team_ID";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$cohort_ID, $group_ID]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(["success" => true, "data" => $rows]);
}

/* ==========================================
   action: checkAllTeamsHaveSuggest
   檢查該屆別和類組的所有團隊是否都有建議
========================================== */
if ($action === "checkAllTeamsHaveSuggest") {
    
    $cohort_ID = $_GET["cohort_ID"] ?? 0;
    $group_ID = $_GET["group_ID"] ?? 0;
    
    if (!$cohort_ID || !$group_ID) {
        respond(["success" => false, "msg" => "缺少參數"]);
    }
    
    // 取得該屆別和類組的所有團隊
    $sql = "SELECT t.team_ID, t.team_project_name
            FROM teamdata t
            WHERE t.cohort_ID = ?
              AND t.group_ID = ?
              AND t.team_status = 1
            ORDER BY t.team_ID";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$cohort_ID, $group_ID]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($teams) === 0) {
        respond(["success" => false, "msg" => "沒有找到任何團隊"]);
    }
    
    // 檢查每個團隊是否有建議
    $teamsWithoutSuggest = [];
    foreach ($teams as $team) {
        $team_ID = $team['team_ID'];
        
        $sql = "SELECT COUNT(*) as count 
                FROM suggest 
                WHERE team_ID = ? 
                  AND suggest_status = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$team_ID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $teamsWithoutSuggest[] = $team['team_project_name'];
        }
    }
    
    if (count($teamsWithoutSuggest) > 0) {
        respond([
            "success" => false, 
            "msg" => "以下團隊尚未填寫建議：" . implode("、", $teamsWithoutSuggest),
            "teamsWithoutSuggest" => $teamsWithoutSuggest
        ]);
    }
    
    respond(["success" => true, "msg" => "所有團隊都已填寫建議"]);
}

/* ==========================================
   action: listSuggests
   取得某團隊所有建議（多筆）
========================================== */
if ($action === "listSuggests") {

    $team_ID = $_GET["team_ID"] ?? 0;

    $sql = "SELECT 
                suggest_ID,
                suggest_comment,
                suggest_d
            FROM suggest
            WHERE team_ID = ?
              AND suggest_status = 1
            ORDER BY suggest_ID DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$team_ID]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(["success" => true, "data" => $rows]);
}

/* ==========================================
   action: addSuggest
   多行 → 單筆建議（合併）
========================================== */
if ($action === "addSuggest") {

    $team_ID = $_POST["team_ID"] ?? 0;
    $content = $_POST["content"] ?? "";

    if (!$team_ID || trim($content) === "") {
        respond(["success" => false, "msg" => "參數錯誤"]);
    }

    // 處理多行內容：忽略只有編號的行，然後重新編號
    $lines = preg_split("/\r\n|\r|\n/", $content);
    $validLines = [];
    
    // 第一步：過濾掉只有編號的行和空行
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // 空行跳過
        if ($trimmedLine === "") continue;
        
        // 檢查是否只有編號（如：1. 或 2 或 . 或 2) 等）
        // 匹配：開頭是數字+標點符號，後面只有空白或沒有內容
        // 也匹配：只有數字+小數點（如：2.2 但沒有其他內容）
        if (preg_match('/^\s*\d+[\.\、\)\:]\s*$/u', $trimmedLine) || 
            preg_match('/^\s*\d+\s*$/u', $trimmedLine) ||
            preg_match('/^\s*[\.\、\)\:]\s*$/u', $trimmedLine) ||
            // 匹配像 "2.2" 這種只有數字和小數點的（但沒有其他文字）
            preg_match('/^\s*\d+\.\d+\s*$/u', $trimmedLine)) {
            continue; // 跳過只有編號的行
        }
        
        // 保留有效行（包括原始格式）
        $validLines[] = $line;
    }
    
    if (count($validLines) == 0) {
        respond(["success" => false, "msg" => "內容無有效建議"]);
    }
    
    // 第二步：重新編號，將每行開頭的編號替換為新的連續編號
    $cleanLines = [];
    $newNumber = 1;
    
    foreach ($validLines as $line) {
        $trimmedLine = trim($line);
        $content = "";
        
        // 使用 preg_match 精確捕獲內容部分
        // 模式1：數字 + 點 + 空白 + 內容（如：1. 測試）
        if (preg_match('/^\s*\d+\.\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        // 模式2：數字 + 其他標點 + 空白 + 內容（如：1) 測試、1: 測試）
        elseif (preg_match('/^\s*\d+[\)\:]\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        // 模式3：數字 + 頓號 + 內容（如：1、測試）
        elseif (preg_match('/^\s*\d+、\s*(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        // 模式4：數字 + 空白 + 內容（沒有標點，如：1 測試）
        elseif (preg_match('/^\s*\d+\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        // 如果沒有匹配到任何編號模式，直接使用原內容
        else {
            $content = $trimmedLine;
        }
        
        // 確保有內容（去掉編號後不能為空）
        $content = trim($content);
        // 過濾掉：空內容、只有標點、只有數字、只有數字+標點
        // 特別檢查：如果內容是 "2." 這種格式，也要過濾掉
        if ($content !== "" && 
            $content !== "." && 
            !preg_match('/^\d+\.?\s*$/', $content) &&
            !preg_match('/^[\.\、\)\:]\s*$/', $content) &&
            !preg_match('/^\d+[\.\、\)\:]\s*$/', $content) &&
            // 額外檢查：確保不是只有數字+點（如 "2."）
            !preg_match('/^\d+\.$/', $content)) {
            // 加上新編號
            $cleanLines[] = $newNumber . ". " . $content;
            $newNumber++;
        }
    }
    
    if (count($cleanLines) == 0) {
        respond(["success" => false, "msg" => "內容無有效建議"]);
    }
    
    // 合併所有行，用換行符連接
    $finalContent = implode("\n", $cleanLines);
    
    // 去除最後多餘的換行
    $finalContent = rtrim($finalContent, "\n\r");

    $sql = "INSERT INTO suggest
            (suggest_u_ID, team_ID, suggest_comment, suggest_d, suggest_status)
            VALUES (?, ?, ?, NOW(), 1)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$u_ID, $team_ID, $finalContent]);

    $suggestId = $conn->lastInsertId();

    respond(["success" => true, "msg" => "已新增建議", "suggest_ID" => $suggestId]);
}

/* ==========================================
   action: updateSuggest
   更新建議
========================================== */
if ($action === "updateSuggest") {

    $suggest_ID = $_POST["suggest_ID"] ?? 0;
    $team_ID = $_POST["team_ID"] ?? 0;
    $content = $_POST["content"] ?? "";

    if (!$suggest_ID || !$team_ID || trim($content) === "") {
        respond(["success" => false, "msg" => "參數錯誤"]);
    }

    // 處理多行內容：忽略只有編號的行，然後重新編號
    $lines = preg_split("/\r\n|\r|\n/", $content);
    $validLines = [];
    
    // 第一步：過濾掉只有編號的行和空行
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // 空行跳過
        if ($trimmedLine === "") continue;
        
        // 檢查是否只有編號（如：1. 或 2 或 . 或 2) 等）
        // 也匹配：只有數字+小數點（如：2.2 但沒有其他內容）
        if (preg_match('/^\s*\d+[\.\、\)\:]\s*$/u', $trimmedLine) || 
            preg_match('/^\s*\d+\s*$/u', $trimmedLine) ||
            preg_match('/^\s*[\.\、\)\:]\s*$/u', $trimmedLine) ||
            // 匹配像 "2.2" 這種只有數字和小數點的（但沒有其他文字）
            preg_match('/^\s*\d+\.\d+\s*$/u', $trimmedLine)) {
            continue; // 跳過只有編號的行
        }
        
        // 保留有效行（包括原始格式）
        $validLines[] = $line;
    }
    
    if (count($validLines) == 0) {
        respond(["success" => false, "msg" => "內容無有效建議"]);
    }
    
    // 第二步：重新編號，將每行開頭的編號替換為新的連續編號
    $cleanLines = [];
    $newNumber = 1;
    
    foreach ($validLines as $line) {
        $trimmedLine = trim($line);
        $content = "";
        
        // 使用 preg_match 精確捕獲內容部分
        if (preg_match('/^\s*\d+\.\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        elseif (preg_match('/^\s*\d+[\)\:]\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        elseif (preg_match('/^\s*\d+、\s*(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        elseif (preg_match('/^\s*\d+\s+(.+)$/u', $trimmedLine, $matches)) {
            $content = trim($matches[1]);
        }
        else {
            $content = $trimmedLine;
        }
        
        // 確保有內容（去掉編號後不能為空）
        $content = trim($content);
        // 過濾掉：空內容、只有標點、只有數字、只有數字+標點
        // 特別檢查：如果內容是 "2." 這種格式，也要過濾掉
        if ($content !== "" && 
            $content !== "." && 
            !preg_match('/^\d+\.?\s*$/', $content) &&
            !preg_match('/^[\.\、\)\:]\s*$/', $content) &&
            !preg_match('/^\d+[\.\、\)\:]\s*$/', $content) &&
            // 額外檢查：確保不是只有數字+點（如 "2."）
            !preg_match('/^\d+\.$/', $content)) {
            $cleanLines[] = $newNumber . ". " . $content;
            $newNumber++;
        }
    }
    
    if (count($cleanLines) == 0) {
        respond(["success" => false, "msg" => "內容無有效建議"]);
    }
    
    // 合併所有行，用換行符連接
    $finalContent = implode("\n", $cleanLines);
    $finalContent = rtrim($finalContent, "\n\r");

    // 更新資料
    $sql = "UPDATE suggest 
            SET suggest_comment = ?, suggest_d = NOW() 
            WHERE suggest_ID = ? AND team_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$finalContent, $suggest_ID, $team_ID]);

    respond(["success" => true, "msg" => "已更新建議", "suggest_ID" => $suggest_ID]);
}

/* ==========================================
   action: deleteSuggest
========================================== */
if ($action === "deleteSuggest") {

    $sid = $_POST["suggest_ID"] ?? 0;

    if (!$sid) respond(["success" => false, "msg" => "參數錯誤"]);

    // 直接刪除（你可改成 soft delete）
    $sql = "DELETE FROM suggest WHERE suggest_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sid]);

    respond(["success" => true, "msg" => "已刪除"]);
}

/* ==========================================
   action 不存在
========================================== */
respond(["success" => false, "msg" => "未知 action"]);
