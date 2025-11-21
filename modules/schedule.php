<?php
/**
 * 時程表管理後端 API 模組
 */

global $conn;
$p = $_POST;
$do = $_GET['do'] ?? '';
$u_ID = $_SESSION['u_ID'] ?? null;

// 檢查是否為科辦或主任 (role_ID=1, 2)
function checkOfficePermission() {
    global $conn;
    $u_ID = $_SESSION['u_ID'] ?? null;
    if (!$u_ID) {
        json_err('請先登入', 'NOT_LOGGED_IN', 401);
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM userrolesdata 
        WHERE ur_u_ID = ? AND role_ID IN (1, 2) AND user_role_status = 1
    ");
    $stmt->execute([$u_ID]);
    if (!$stmt->fetchColumn()) {
        json_err('此功能僅限主任和科辦使用', 'NO_PERMISSION', 403);
    }
    return $u_ID;
}

switch ($do) {
    // 獲取所有團隊資料（包含成員和指導老師）
    case 'get_teams_schedule':
        try {
            checkOfficePermission();
            
            $cohort_ID = isset($_GET['cohort_ID']) && $_GET['cohort_ID'] !== '' ? (int)$_GET['cohort_ID'] : null;
            $group_ID = isset($_GET['group_ID']) && $_GET['group_ID'] !== '' ? (int)$_GET['group_ID'] : null;
            
            // 構建查詢條件
            $sql = "
                SELECT 
                    t.team_ID,
                    t.team_project_name,
                    t.cohort_ID,
                    t.group_ID,
                    g.group_name
                FROM teamdata t
                LEFT JOIN groupdata g ON t.group_ID = g.group_ID
                WHERE t.team_status = 1
            ";
            $params = [];
            
            if ($cohort_ID !== null) {
                $sql .= " AND t.cohort_ID = ?";
                $params[] = $cohort_ID;
            }
            
            if ($group_ID !== null) {
                $sql .= " AND t.group_ID = ?";
                $params[] = $group_ID;
            }
            
            // 預設排序：按照團隊ID順序
            $sql .= " ORDER BY t.team_ID ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 為每個團隊獲取成員和指導老師
            foreach ($teams as &$team) {
                $team_ID = $team['team_ID'];
                
                // 檢查 teammember 表結構（兼容兩種版本）
                $teamUserField = 'team_u_ID';
                $stmt = $conn->prepare("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
                $stmt->execute();
                if (!$stmt->fetch()) {
                    $teamUserField = 'u_ID';
                }
                
                // 獲取團隊成員（包含所有成員）
                $sql = "
                    SELECT DISTINCT
                        tm.{$teamUserField} as u_ID,
                        u.u_name
                    FROM teammember tm
                    INNER JOIN userdata u ON tm.{$teamUserField} = u.u_ID
                    WHERE tm.team_ID = ? AND tm.tm_status = 1
                    ORDER BY u.u_ID
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$team_ID]);
                $allMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 分離學生和指導老師
                $students = [];
                $teacher = null;
                
                foreach ($allMembers as $member) {
                    $u_ID = $member['u_ID'];
                    
                    // 檢查該用戶的角色
                    $roleSql = "
                        SELECT role_ID 
                        FROM userrolesdata 
                        WHERE ur_u_ID = ? AND user_role_status = 1
                    ";
                    $roleStmt = $conn->prepare($roleSql);
                    $roleStmt->execute([$u_ID]);
                    $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array(4, $roles)) {
                        // 指導老師
                        if (!$teacher) {
                            $teacher = [
                                'u_ID' => $member['u_ID'],
                                'u_name' => $member['u_name']
                            ];
                        }
                    } elseif (in_array(6, $roles)) {
                        // 學生
                        $students[] = [
                            'u_ID' => $member['u_ID'],
                            'u_name' => $member['u_name']
                        ];
                    }
                }
                
                $team['students'] = $students;
                $team['teacher'] = $teacher;
                
                // 確保 students 是陣列（即使為空）
                if (!isset($team['students']) || !is_array($team['students'])) {
                    $team['students'] = [];
                }
                // 確保 teacher 是物件或 null
                if (!isset($team['teacher'])) {
                    $team['teacher'] = null;
                }
            }
            
            // 取消引用（避免後續問題）
            unset($team);
            
            json_ok(['teams' => $teams]);
        } catch (Throwable $e) {
            json_err('獲取團隊資料失敗：' . $e->getMessage());
        }
        break;

    // 獲取類組列表（根據屆別）
    case 'get_groups':
        try {
            checkOfficePermission();
            
            $cohort_ID = isset($_GET['cohort_ID']) && $_GET['cohort_ID'] !== '' ? (int)$_GET['cohort_ID'] : null;
            
            if ($cohort_ID) {
                // 獲取該屆別下的類組
                $sql = "
                    SELECT DISTINCT g.group_ID, g.group_name
                    FROM groupdata g
                    INNER JOIN teamdata t ON g.group_ID = t.group_ID
                    WHERE g.group_status = 1 AND t.team_status = 1 AND t.cohort_ID = ?
                    ORDER BY g.group_ID
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$cohort_ID]);
            } else {
                // 獲取所有啟用的類組
                $sql = "
                    SELECT group_ID, group_name
                    FROM groupdata
                    WHERE group_status = 1
                    ORDER BY group_ID
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            }
            
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['groups' => $groups]);
        } catch (Throwable $e) {
            json_err('獲取類組列表失敗：' . $e->getMessage());
        }
        break;

    // 獲取時程表資訊
    case 'get_schedule_info':
        try {
            checkOfficePermission();
            
            $tinforma_ID = isset($_GET['tinforma_ID']) ? (int)$_GET['tinforma_ID'] : null;
            
            if ($tinforma_ID) {
                // 獲取指定的時程表資訊
                $stmt = $conn->prepare("SELECT * FROM timeinformadata WHERE tinforma_ID = ?");
                $stmt->execute([$tinforma_ID]);
                $info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$info) {
                    json_err('找不到時程表資訊');
                }
                
                // 獲取該時程表的所有團隊時程
                $stmt = $conn->prepare("
                    SELECT 
                        td.*,
                        t.team_project_name
                    FROM timedata td
                    LEFT JOIN teamdata t ON td.team_ID = t.team_ID
                    WHERE td.tinforma_ID = ?
                    ORDER BY td.sort_no ASC, td.time_start_d ASC
                ");
                $stmt->execute([$tinforma_ID]);
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                json_ok([
                    'info' => $info,
                    'schedules' => $schedules
                ]);
            } else {
                // 獲取最新的時程表資訊
                $stmt = $conn->prepare("
                    SELECT * FROM timeinformadata 
                    ORDER BY tinforma_create_d DESC 
                    LIMIT 1
                ");
                $stmt->execute();
                $info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($info) {
                    $tinforma_ID = $info['tinforma_ID'];
                    
                    // 獲取該時程表的所有團隊時程
                    $stmt = $conn->prepare("
                        SELECT 
                            td.*,
                            t.team_project_name
                        FROM timedata td
                        LEFT JOIN teamdata t ON td.team_ID = t.team_ID
                        WHERE td.tinforma_ID = ?
                        ORDER BY td.sort_no ASC, td.time_start_d ASC
                    ");
                    $stmt->execute([$tinforma_ID]);
                    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    json_ok([
                        'info' => $info,
                        'schedules' => $schedules
                    ]);
                } else {
                    json_ok([
                        'info' => null,
                        'schedules' => []
                    ]);
                }
            }
        } catch (Throwable $e) {
            json_err('獲取時程表資訊失敗：' . $e->getMessage());
        }
        break;

    // 保存時程表資訊
    case 'save_schedule_info':
        try {
            checkOfficePermission();
            
            $tinforma_ID = isset($p['tinforma_ID']) ? (int)$p['tinforma_ID'] : null;
            $tinforma_content = trim($p['tinforma_content'] ?? '');
            
            $conn->beginTransaction();
            
            if ($tinforma_ID) {
                // 更新現有的時程表資訊
                $stmt = $conn->prepare("
                    UPDATE timeinformadata 
                    SET tinforma_content = ? 
                    WHERE tinforma_ID = ?
                ");
                $stmt->execute([$tinforma_content, $tinforma_ID]);
            } else {
                // 創建新的時程表資訊
                $stmt = $conn->prepare("
                    INSERT INTO timeinformadata (tinforma_content) 
                    VALUES (?)
                ");
                $stmt->execute([$tinforma_content]);
                $tinforma_ID = $conn->lastInsertId();
            }
            
            $conn->commit();
            
            json_ok([
                'tinforma_ID' => $tinforma_ID,
                'message' => '時程表資訊已保存'
            ]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('保存時程表資訊失敗：' . $e->getMessage());
        }
        break;

    // 保存團隊時程
    case 'save_team_schedules':
        try {
            checkOfficePermission();
            
            $tinforma_ID = isset($p['tinforma_ID']) ? (int)$p['tinforma_ID'] : null;
            $schedules = json_decode($p['schedules'] ?? '[]', true);
            
            if (!$tinforma_ID) {
                json_err('缺少時程表資訊ID');
            }
            
            if (!is_array($schedules)) {
                json_err('時程資料格式錯誤');
            }
            
            $conn->beginTransaction();
            
            // 刪除該時程表的所有現有時程
            $stmt = $conn->prepare("DELETE FROM timedata WHERE tinforma_ID = ?");
            $stmt->execute([$tinforma_ID]);
            
            // 插入新的時程
            $stmt = $conn->prepare("
                INSERT INTO timedata (
                    tinforma_ID, team_ID, time_start_d, time_end_d, sort_no
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($schedules as $schedule) {
                $team_ID = (int)($schedule['team_ID'] ?? 0);
                $time_start_d = $schedule['time_start_d'] ?? null;
                $time_end_d = $schedule['time_end_d'] ?? null;
                $sort_no = isset($schedule['sort_no']) ? (int)$schedule['sort_no'] : null;
                
                if ($team_ID > 0 && $time_start_d && $time_end_d) {
                    $stmt->execute([
                        $tinforma_ID,
                        $team_ID,
                        $time_start_d,
                        $time_end_d,
                        $sort_no
                    ]);
                }
            }
            
            $conn->commit();
            
            json_ok(['message' => '團隊時程已保存']);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('保存團隊時程失敗：' . $e->getMessage());
        }
        break;

    default:
        json_err('Unknown action: ' . $do);
}

