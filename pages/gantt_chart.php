<?php
/**
 * 甘特圖頁面
 * 
 * 修改記錄：
 * 2025-11-18 - 創建甘特圖頁面
 *   改動內容：顯示里程碑的甘特圖，學生和老師都能訪問
 *   相關功能：里程碑甘特圖視覺化
 *   方式：使用 Chart.js 或自定義甘特圖實現
 */

session_start();
require '../includes/pdo.php';

$u_ID = $_SESSION['u_ID'] ?? null;
$role_ID = $_SESSION['role_ID'] ?? null;

if (!$u_ID) {
    echo '<div class="alert alert-danger">請先登入</div>';
    exit;
}

// 獲取用戶的團隊ID（學生或老師）
$team_ID = null;
if ($role_ID == 6) {
    // 學生：獲取所屬團隊
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT t.team_ID
            FROM teamdata t
            JOIN teammember tm ON t.team_ID = tm.team_ID
            WHERE tm.team_u_ID = ? AND t.team_status = 1
            LIMIT 1
        ");
        $stmt->execute([$u_ID]);
    } catch (Exception $e) {
        $stmt = $conn->prepare("
            SELECT DISTINCT t.team_ID
            FROM teamdata t
            JOIN teammember tm ON t.team_ID = tm.team_ID
            WHERE tm.u_ID = ? AND t.team_status = 1
            LIMIT 1
        ");
        $stmt->execute([$u_ID]);
    }
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    $team_ID = $team['team_ID'] ?? null;
} elseif ($role_ID == 4) {
    // 老師：可以查看所有團隊，但需要從URL參數獲取
    $team_ID = isset($_GET['team_ID']) ? (int)$_GET['team_ID'] : 0;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>里程碑甘特圖</title>
    <link rel="stylesheet" href="css/gantt_chart.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div id="ganttApp" class="gantt-container">
        <div class="gantt-header">
            <h1 class="gantt-title">
                <i class="fa-solid fa-chart-gantt"></i>
                里程碑甘特圖
            </h1>
            <div class="gantt-controls" v-if="role_ID === 4">
                <select v-model="selectedTeam" @change="loadGanttData" class="team-select">
                    <option value="0">選擇團隊</option>
                    <option v-for="team in teams" :key="team.team_ID" :value="team.team_ID">
                        {{ team.team_name }}
                    </option>
                </select>
            </div>
            <button class="btn-back" @click="goBack">
                <i class="fa-solid fa-arrow-left"></i>
                返回
            </button>
        </div>

        <div class="gantt-content" v-if="milestones.length > 0">
            <div class="gantt-chart">
                <div class="gantt-timeline">
                    <div class="gantt-row gantt-header-row">
                        <div class="gantt-task-name">里程碑</div>
                        <div class="gantt-bars-container">
                            <div class="gantt-time-scale">
                                <div v-for="date in timeScale" :key="date" class="gantt-time-marker">
                                    {{ formatDateShort(date) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div 
                        v-for="milestone in sortedMilestones" 
                        :key="milestone.ms_ID"
                        class="gantt-row">
                        <div class="gantt-task-name">
                            <div class="task-title">{{ milestone.ms_title }}</div>
                            <div class="task-meta">
                                <span class="task-status" :class="getStatusClass(milestone.ms_status)">
                                    {{ getStatusText(milestone.ms_status) }}
                                </span>
                                <span class="task-priority" :class="getPriorityClass(milestone.ms_priority)">
                                    {{ getPriorityText(milestone.ms_priority) }}
                                </span>
                            </div>
                        </div>
                        <div class="gantt-bars-container">
                            <div class="gantt-bar-wrapper" :style="getBarStyle(milestone)">
                                <div 
                                    class="gantt-bar" 
                                    :class="getBarClass(milestone)"
                                    :style="getBarPosition(milestone)">
                                    <div class="bar-label">{{ milestone.ms_title }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="empty-state" v-else>
            <div class="empty-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="empty-text">目前沒有里程碑資料</div>
            <div class="empty-hint" v-if="role_ID === 4">請先選擇團隊</div>
        </div>
    </div>

    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        // 傳遞PHP變數到JavaScript
        window.GANTT_CONFIG = {
            role_ID: <?= $role_ID ?? 0 ?>,
            team_ID: <?= $team_ID ?? 0 ?>
        };
    </script>
    <script src="js/gantt_chart.js?v=<?= time() ?>"></script>
</body>
</html>

