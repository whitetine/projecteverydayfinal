<?php
session_start();
require "../includes/pdo.php";
date_default_timezone_set("Asia/Taipei");

/* ==========================================
   權限：主任 (role_ID = 1) 和 科辦 (role_ID = 2)
========================================== */
$role_ID = $_SESSION["role_ID"] ?? null;
if (!isset($role_ID) || !in_array($role_ID, [1, 2])) {
    die("無權限");
}

$cohort_ID = $_GET["cohort_ID"] ?? 0;
$group_ID = $_GET["group_ID"] ?? 0;

if (!$cohort_ID || !$group_ID) {
    die("缺少參數");
}

// 檢查欄位名稱
function columnExists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$teamUserField = columnExists($conn, 'teammember', 'team_u_ID') ? 'team_u_ID' : 'u_ID';
$userRoleUidField = columnExists($conn, 'userrolesdata', 'ur_u_ID') ? 'ur_u_ID' : 'u_ID';

// 取得類組名稱
$stmt = $conn->prepare("SELECT group_name FROM groupdata WHERE group_ID = ?");
$stmt->execute([$group_ID]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);
$group_name = $group['group_name'] ?? '';

// 取得屆別名稱
$stmt = $conn->prepare("SELECT cohort_name FROM cohortdata WHERE cohort_ID = ?");
$stmt->execute([$cohort_ID]);
$cohort = $stmt->fetch(PDO::FETCH_ASSOC);
$cohort_name = $cohort['cohort_name'] ?? '';

// 取得標題（直接使用資料庫的 suggest_name）
// 從該屆別和類組的所有建議中取得最新的標題
$stmt = $conn->prepare("
    SELECT s.suggest_name 
    FROM suggest s
    JOIN teamdata t ON s.team_ID = t.team_ID
    WHERE t.cohort_ID = ? 
      AND t.group_ID = ?
      AND s.suggest_status IN (1, 2, 3, 4)
      AND s.suggest_name IS NOT NULL
      AND TRIM(s.suggest_name) != ''
    ORDER BY s.suggest_d DESC, s.suggest_ID DESC
    LIMIT 1
");
$stmt->execute([$cohort_ID, $group_ID]);
$title_result = $stmt->fetch(PDO::FETCH_ASSOC);

// 直接使用資料庫的 suggest_name
$page_title = '';
if ($title_result && isset($title_result['suggest_name']) && trim($title_result['suggest_name']) !== '') {
    $page_title = trim($title_result['suggest_name']);
}

// 取得該屆別和類組的所有團隊
$sql = "SELECT 
            t.team_ID,
            t.team_project_name
        FROM teamdata t
        WHERE t.cohort_ID = ?
          AND t.group_ID = ?
          AND t.team_status = 1
        ORDER BY t.team_ID";

$stmt = $conn->prepare($sql);
$stmt->execute([$cohort_ID, $group_ID]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取得每個團隊的成員和建議
$teamData = [];
foreach ($teams as $team) {
    $team_ID = $team['team_ID'];
    
    // 取得團隊成員（只取得學生，role_ID = 6）
    $sql = "SELECT 
                tm.{$teamUserField} AS u_ID,
                COALESCE(ud.u_name, tm.{$teamUserField}) AS u_name
            FROM teammember tm
            JOIN userrolesdata ur 
                  ON ur.{$userRoleUidField} = tm.{$teamUserField}
                 AND ur.role_ID = 6
                 AND ur.user_role_status = 1
            LEFT JOIN userdata ud ON ud.u_ID = tm.{$teamUserField}
            WHERE tm.team_ID = ?
              AND tm.tm_status = 1
            GROUP BY tm.{$teamUserField}, ud.u_name
            ORDER BY tm.{$teamUserField}";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$team_ID]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 取得最新建議（包含標題和狀態）
    $sql = "SELECT suggest_comment, suggest_name, suggest_status 
            FROM suggest 
            WHERE team_ID = ? 
              AND suggest_status IN (1, 2, 3, 4)
            ORDER BY suggest_d DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$team_ID]);
    $suggest = $stmt->fetch(PDO::FETCH_ASSOC);
    $suggest_comment = $suggest['suggest_comment'] ?? '';
    $suggest_status = $suggest['suggest_status'] ?? null;
    
    // 狀態對應文字
    $status_text = '—';
    if ($suggest_status == 3) {
        $status_text = '通過';
    } elseif ($suggest_status == 4) {
        $status_text = '複審';
    } elseif ($suggest_status == 2) {
        $status_text = '不通過';
    }
    
    $teamData[] = [
        'team_ID' => $team_ID,
        'project_name' => $team['team_project_name'],
        'members' => $members,
        'suggest' => $suggest_comment,
        'status' => $status_text,
        'suggest_name' => $suggest['suggest_name'] ?? null
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
    :root {
        --kai: "DFKai-SB", "BiauKai", "標楷體", "Microsoft JhengHei", serif;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
    }

    body{
        font-family: var(--kai);
        color:#111;
        margin:0;
        padding:0;
        background:#fff;
    }

    /* 讓整個內容置中，產生跟圖2一樣的留白 */
    #report{
        width: 90%;
        max-width: 1000px;
        margin: 25px auto;
    }

    /* 標題：大、置中、與表格有明顯距離 */
    .title{
        font-family: var(--kai);
        text-align:center;
        font-weight:700;
        font-size:24px;
        letter-spacing:.06em;
        margin: 10px 0 25px;
    }

    /* 表格外框（很重要，圖2就是這種） */
    .table-wrapper{
        border: 2px solid #000;
        padding: 0;
        margin-top: 10px;
    }

    table{
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
        font-size:18px;
    }

     th, td{
         border:1px solid #000;
         padding:10px 12px;
     }

     thead th{
         background:#cfe3f6;
         text-align:center;
         vertical-align:middle;
         font-weight:700;
         font-family: var(--kai);
     }

     /* 欄位寬度（依照圖2調整過） */
     .col-title{ width:25%; text-align:center; vertical-align:middle; }
     .col-members{ width:23%; text-align:center; vertical-align:middle; }
     .col-result{ width:12%; text-align:center; vertical-align:middle; }
     .col-comment{ width:40%; text-align:left; line-height:1.6; vertical-align:top; }
     
     /* 確保題目、組員、審查結果都置中 */
     .project-name, .members, .review-result {
         text-align: center;
         vertical-align: middle;
     }

    /* 成員欄位換行整齊 */
    .members-list{
        white-space:pre-line;
        line-height:1.5;
    }

    /* 無建議提示 */
    .no-suggest{
        color:#666;
        font-style:italic;
    }
</style>

</head>
<body>
    <div class="header">
        <?php if (!empty($page_title)): ?>
            <div class="title"><?php echo htmlspecialchars($page_title); ?></div>
        <?php else: ?>
            <div class="title" style="color: #999; font-style: italic;">（尚未設定標題）</div>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>題目</th>
                <th>組員</th>
                <th>審查結果</th>
                <th>審查意見</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teamData as $team): ?>
            <tr>
                <td class="project-name col-title"><?php echo htmlspecialchars($team['project_name']); ?></td>
                <td class="members col-members">
                    <?php 
                    $memberList = [];
                    foreach ($team['members'] as $member) {
                        $memberList[] = htmlspecialchars($member['u_ID'] . ' ' . $member['u_name']);
                    }
                    echo implode('<br>', $memberList);
                    ?>
                </td>
                <td class="review-result col-result"><?php echo htmlspecialchars($team['status']); ?></td>
                <td class="suggest col-comment">
                    <?php 
                    if (!empty($team['suggest'])) {
                        // 保持換行，讓每個編號項目都在單獨的一行
                        $suggest_text = $team['suggest'];
                        // 確保編號格式正確（數字. 後面有空格）
                        $suggest_text = preg_replace('/(\d+)\.\s*/', '$1. ', $suggest_text);
                        // 使用 nl2br 將換行符號轉換為 <br> 標籤
                        echo nl2br(htmlspecialchars($suggest_text));
                    } else {
                        echo '<span class="no-suggest">（無審查意見）</span>';
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        window.onload = function() {
            // 生成檔案名稱
            const pageTitle = '<?php echo htmlspecialchars($page_title, ENT_QUOTES); ?>';
            const fileName = (pageTitle || '建議') + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
            
            // 取得要轉換的元素
            const element = document.body;
            
            // 設定選項
            const opt = {
                margin: [10, 10, 10, 10],
                filename: fileName,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'landscape' 
                }
            };
            
            // 生成並下載 PDF
            html2pdf().set(opt).from(element).save().then(function() {
                // PDF 下載完成後，可以選擇關閉視窗或顯示訊息
                // window.close(); // 可選：自動關閉視窗
            }).catch(function(error) {
                console.error('PDF 生成失敗:', error);
                alert('PDF 生成失敗，請使用瀏覽器的列印功能（Ctrl+P）');
            });
        };
    </script>
</body>
</html>

