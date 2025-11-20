<?php
global $conn;
$p = $_POST;
$do = $_GET['do'] ?? '';

// 檢查權限（主任 role_ID = 1 和 科辦 role_ID = 2）
$role_ID = $_SESSION['role_ID'] ?? null;
if (!isset($role_ID) || !in_array($role_ID, [1, 2])) {
    json_err('無權限訪問');
}

// 檢查欄位名稱（兼容不同版本的資料表結構）
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

switch ($do) {
    // 取得篩選選項（屆別、年級、類組、班級）
    case 'get_filter_options':
        try {
            // 取得所有有專題的屆別
            $stmt = $conn->prepare("
                SELECT DISTINCT 
                    c.cohort_ID,
                    c.cohort_name
                FROM cohortdata c
                INNER JOIN teamdata t ON t.cohort_ID = c.cohort_ID
                WHERE c.cohort_status = 1 
                  AND t.team_status = 1
                ORDER BY c.cohort_ID DESC
            ");
            $stmt->execute();
            $cohorts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 取得所有有專題的年級
            $stmt = $conn->prepare("
                SELECT DISTINCT 
                    e.enroll_grade
                FROM enrollmentdata e
                INNER JOIN teammember tm ON tm.{$teamUserField} = e.enroll_u_ID
                INNER JOIN teamdata t ON t.team_ID = tm.team_ID
                WHERE e.enroll_status = 1 
                  AND tm.tm_status = 1
                  AND t.team_status = 1
                  AND e.enroll_grade IS NOT NULL
                  AND e.enroll_grade != ''
                ORDER BY e.enroll_grade ASC
            ");
            $stmt->execute();
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 取得所有有專題的類組
            $stmt = $conn->prepare("
                SELECT DISTINCT 
                    g.group_ID,
                    g.group_name
                FROM groupdata g
                INNER JOIN teamdata t ON t.group_ID = g.group_ID
                WHERE g.group_status = 1 
                  AND t.team_status = 1
                ORDER BY g.group_ID ASC
            ");
            $stmt->execute();
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 取得所有有專題的班級
            $stmt = $conn->prepare("
                SELECT DISTINCT 
                    c.c_ID,
                    c.c_name
                FROM classdata c
                INNER JOIN enrollmentdata e ON e.class_ID = c.c_ID
                INNER JOIN teammember tm ON tm.{$teamUserField} = e.enroll_u_ID
                INNER JOIN teamdata t ON t.team_ID = tm.team_ID
                WHERE e.enroll_status = 1 
                  AND tm.tm_status = 1
                  AND t.team_status = 1
                ORDER BY c.c_ID ASC
            ");
            $stmt->execute();
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            json_ok([
                'success' => true,
                'data' => [
                    'cohorts' => $cohorts,
                    'grades' => $grades,
                    'groups' => $groups,
                    'classes' => $classes
                ]
            ]);
            
        } catch (Exception $e) {
            json_err('取得篩選選項失敗：' . $e->getMessage());
        }
        break;
    
    // 取得團隊管理資料（按類組分組）
    case 'get_team_management_data':
        try {
            $cohort_ID = isset($_GET['cohort_ID']) ? (int)$_GET['cohort_ID'] : 0;
            $grade = isset($_GET['grade']) ? trim($_GET['grade']) : '';
            $group_ID = isset($_GET['group_ID']) ? (int)$_GET['group_ID'] : 0;
            $class_ID = isset($_GET['class_ID']) ? (int)$_GET['class_ID'] : 0;
            
            // 構建查詢條件
            $whereConditions = ['t.team_status = 1'];
            $params = [];
            
            if ($cohort_ID > 0) {
                $whereConditions[] = 't.cohort_ID = ?';
                $params[] = $cohort_ID;
            }
            
            if ($group_ID > 0) {
                $whereConditions[] = 't.group_ID = ?';
                $params[] = $group_ID;
            }
            
            // 如果有年級或班級篩選，需要 JOIN enrollmentdata
            $needsEnrollmentJoin = !empty($grade) || $class_ID > 0;
            
            if ($needsEnrollmentJoin) {
                $enrollmentConditions = [];
                if (!empty($grade)) {
                    $enrollmentConditions[] = 'e.enroll_grade = ?';
                    $params[] = $grade;
                }
                if ($class_ID > 0) {
                    $enrollmentConditions[] = 'e.class_ID = ?';
                    $params[] = $class_ID;
                }
            }
            
            // 取得所有符合條件的類組
            $groupSql = "
                SELECT DISTINCT 
                    g.group_ID,
                    g.group_name
                FROM groupdata g
                INNER JOIN teamdata t ON t.group_ID = g.group_ID
            ";
            
            if ($needsEnrollmentJoin) {
                $groupSql .= "
                    INNER JOIN teammember tm ON tm.team_ID = t.team_ID AND tm.tm_status = 1
                    INNER JOIN enrollmentdata e ON e.enroll_u_ID = tm.{$teamUserField} AND e.enroll_status = 1
                ";
            }
            
            $groupSql .= " WHERE " . implode(' AND ', $whereConditions);
            
            if ($needsEnrollmentJoin && !empty($enrollmentConditions)) {
                $groupSql .= " AND " . implode(' AND ', $enrollmentConditions);
            }
            
            $groupSql .= " ORDER BY g.group_ID ASC";
            
            $stmt = $conn->prepare($groupSql);
            $stmt->execute($params);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [
                'groups' => [],
                'noTeamMembers' => []
            ];
            
            // 對每個類組取得團隊資料
            foreach ($groups as $group) {
                $group_ID = $group['group_ID'];
                
                // 取得該類組的所有團隊
                $teamSql = "
                    SELECT DISTINCT
                        t.team_ID,
                        t.team_project_name,
                        t.group_ID,
                        t.cohort_ID,
                        t.team_status
                    FROM teamdata t
                ";
                
                $teamParams = [];
                $teamWhere = ['t.group_ID = ?', 't.team_status = 1'];
                $teamParams[] = $group_ID;
                
                if ($cohort_ID > 0) {
                    $teamWhere[] = 't.cohort_ID = ?';
                    $teamParams[] = $cohort_ID;
                }
                
                if ($needsEnrollmentJoin) {
                    $teamSql .= "
                        INNER JOIN teammember tm ON tm.team_ID = t.team_ID AND tm.tm_status = 1
                        INNER JOIN enrollmentdata e ON e.enroll_u_ID = tm.{$teamUserField} AND e.enroll_status = 1
                    ";
                    
                    if (!empty($grade)) {
                        $teamWhere[] = 'e.enroll_grade = ?';
                        $teamParams[] = $grade;
                    }
                    if ($class_ID > 0) {
                        $teamWhere[] = 'e.class_ID = ?';
                        $teamParams[] = $class_ID;
                    }
                }
                
                $teamSql .= " WHERE " . implode(' AND ', $teamWhere);
                $teamSql .= " ORDER BY t.team_project_name ASC";
                
                $stmt = $conn->prepare($teamSql);
                $stmt->execute($teamParams);
                $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 計算每個團隊的進度（根據基本需求）
                $teamsWithProgress = [];
                foreach ($teams as $team) {
                    $team_ID = $team['team_ID'];
                    
                    // 計算進度：已完成基本需求 / 總基本需求數
                    // 總基本需求：該團隊所屬類組和屆別的所有基本需求
                    $progressStmt = $conn->prepare("
                        SELECT 
                            COUNT(DISTINCT r.req_ID) as total,
                            COUNT(DISTINCT CASE WHEN rp.rp_status = 1 AND rp.rp_team_ID = ? THEN rp.req_ID END) as completed
                        FROM requirementdata r
                        LEFT JOIN reprogressdata rp ON rp.req_ID = r.req_ID
                        WHERE r.req_status = 1
                          AND (r.cohort_ID = ? OR r.cohort_ID IS NULL)
                          AND (r.group_ID = ? OR r.group_ID IS NULL)
                    ");
                    $progressStmt->execute([$team_ID, $team['cohort_ID'], $group_ID]);
                    $progressData = $progressStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $total = (int)($progressData['total'] ?? 0);
                    $completed = (int)($progressData['completed'] ?? 0);
                    $progress = $total > 0 ? ($completed / $total) * 100 : 0;
                    
                    $team['progress'] = round($progress, 1);
                    $teamsWithProgress[] = $team;
                }
                
                // 按進度排序（降序）
                usort($teamsWithProgress, function($a, $b) {
                    return $b['progress'] <=> $a['progress'];
                });
                
                if (count($teamsWithProgress) > 0) {
                    $result['groups'][] = [
                        'group_ID' => $group_ID,
                        'group_name' => $group['group_name'],
                        'teams' => $teamsWithProgress
                    ];
                }
            }
            
            // 取得未加入團隊的學生（根據篩選條件）
            $noTeamSql = "
                SELECT DISTINCT
                    u.u_ID,
                    u.u_name,
                    u.u_img
                FROM userdata u
                INNER JOIN enrollmentdata e ON e.enroll_u_ID = u.u_ID 
                    AND e.enroll_status = 1
                INNER JOIN userrolesdata ur ON ur.{$userRoleUidField} = u.u_ID 
                    AND ur.role_ID = 6 
                    AND ur.user_role_status = 1
                LEFT JOIN teammember tm ON tm.{$teamUserField} = u.u_ID 
                    AND tm.tm_status = 1
                WHERE tm.team_ID IS NULL
            ";
            
            $noTeamParams = [];
            $noTeamWhere = [];
            
            if ($cohort_ID > 0) {
                $noTeamWhere[] = 'e.cohort_ID = ?';
                $noTeamParams[] = $cohort_ID;
            }
            if (!empty($grade)) {
                $noTeamWhere[] = 'e.enroll_grade = ?';
                $noTeamParams[] = $grade;
            }
            if ($class_ID > 0) {
                $noTeamWhere[] = 'e.class_ID = ?';
                $noTeamParams[] = $class_ID;
            }
            
            if (!empty($noTeamWhere)) {
                $noTeamSql .= " AND " . implode(' AND ', $noTeamWhere);
            }
            
            $noTeamSql .= " ORDER BY u.u_ID ASC";
            
            $stmt = $conn->prepare($noTeamSql);
            $stmt->execute($noTeamParams);
            $noTeamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result['noTeamMembers'] = $noTeamMembers;
            
            json_ok(['success' => true, 'data' => $result]);
            
        } catch (Exception $e) {
            json_err('取得團隊資料失敗：' . $e->getMessage());
        }
        break;
    
    // 取得團隊詳情
    case 'get_team_detail':
        try {
            $team_ID = isset($_GET['team_ID']) ? (int)$_GET['team_ID'] : 0;
            
            if (!$team_ID) {
                json_err('缺少團隊ID');
            }
            
            // 取得團隊基本資訊
            $stmt = $conn->prepare("
                SELECT 
                    t.team_ID,
                    t.team_project_name,
                    t.group_ID,
                    t.cohort_ID,
                    t.team_status,
                    g.group_name,
                    c.cohort_name
                FROM teamdata t
                LEFT JOIN groupdata g ON t.group_ID = g.group_ID
                LEFT JOIN cohortdata c ON t.cohort_ID = c.cohort_ID
                WHERE t.team_ID = ?
            ");
            $stmt->execute([$team_ID]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$team) {
                json_err('找不到團隊');
            }
            
            // 取得團隊成員（學生 role_ID = 6 和指導老師 role_ID = 4）
            $stmt = $conn->prepare("
                SELECT 
                    tm.{$teamUserField} AS u_ID,
                    COALESCE(ud.u_name, tm.{$teamUserField}) AS u_name,
                    ud.u_img,
                    ur.role_ID,
                    r.role_name
                FROM teammember tm
                JOIN userrolesdata ur 
                      ON ur.{$userRoleUidField} = tm.{$teamUserField}
                     AND ur.user_role_status = 1
                     AND ur.role_ID IN (4, 6)
                LEFT JOIN userdata ud ON ud.u_ID = tm.{$teamUserField}
                LEFT JOIN roledata r ON ur.role_ID = r.role_ID
                WHERE tm.team_ID = ?
                  AND tm.tm_status = 1
                GROUP BY tm.{$teamUserField}, ud.u_name, ur.role_ID, r.role_name
                ORDER BY ur.role_ID ASC, tm.{$teamUserField}
            ");
            $stmt->execute([$team_ID]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 計算進度（根據基本需求）
            $progressStmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT r.req_ID) as total,
                    COUNT(DISTINCT CASE WHEN rp.rp_status = 1 AND rp.rp_team_ID = ? THEN rp.req_ID END) as completed
                FROM requirementdata r
                LEFT JOIN reprogressdata rp ON rp.req_ID = r.req_ID
                WHERE r.req_status = 1
                  AND (r.cohort_ID = ? OR r.cohort_ID IS NULL)
                  AND (r.group_ID = ? OR r.group_ID IS NULL)
            ");
            $progressStmt->execute([$team_ID, $team['cohort_ID'], $team['group_ID']]);
            $progressData = $progressStmt->fetch(PDO::FETCH_ASSOC);
            
            $total = (int)($progressData['total'] ?? 0);
            $completed = (int)($progressData['completed'] ?? 0);
            $progress = $total > 0 ? ($completed / $total) * 100 : 0;
            
            $team['members'] = $members;
            $team['progress'] = round($progress, 1);
            
            json_ok(['success' => true, 'data' => $team]);
            
        } catch (Exception $e) {
            json_err('取得團隊詳情失敗：' . $e->getMessage());
        }
        break;
    
    default:
        json_err('未知的操作');
}
