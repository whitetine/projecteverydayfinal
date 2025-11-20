<?php
/**
 * 專題申請表後端 API 模組
 */

global $conn;
$p = $_POST;
$do = $_GET['do'] ?? '';
$u_ID = $_SESSION['u_ID'] ?? null;

// 發送退件郵件通知
function sendRejectionEmail($to, $studentName, $projectName, $remark) {
    // 使用 Google Apps Script API 發送郵件
    $url = "https://script.google.com/macros/s/AKfycby-KZRj7ceUxw4QadRbASpsgrj4xtz8wnzR-jARhzUchU7aUlo4U-K0ULZq-u4HGXE/exec";
    
    $subject = '專題申請退件通知';
    $message = "親愛的 {$studentName} 同學：\n\n";
    $message .= "您的專題申請「{$projectName}」已被退件。\n\n";
    if (!empty($remark)) {
        $message .= "退件原因：{$remark}\n\n";
    }
    $message .= "請重新檢查申請資料並重新提交。\n\n";
    $message .= "此為系統自動發送，請勿直接回覆。";
    
    $data = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message
    ];
    
    $options = [
        "http" => [
            "method" => "POST",
            "header" => "Content-type: application/x-www-form-urlencoded",
            "content" => http_build_query($data),
            "timeout" => 10
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
    // 不拋出錯誤，避免影響主要流程
}

// 檢查是否為學生 (role_ID=6)
function checkStudentPermission() {
    global $conn;
    $u_ID = $_SESSION['u_ID'] ?? null;
    if (!$u_ID) {
        json_err('請先登入', 'NOT_LOGGED_IN', 401);
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM userrolesdata 
        WHERE ur_u_ID = ? AND role_ID = 6 AND user_role_status = 1
    ");
    $stmt->execute([$u_ID]);
    if (!$stmt->fetchColumn()) {
        json_err('此功能僅限學生使用', 'NO_PERMISSION', 403);
    }
    return $u_ID;
}

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
    // 獲取指導老師列表
    case 'get_teachers':
        try {
            $sql = "
                SELECT DISTINCT u.u_ID, u.u_name
                FROM userdata u
                INNER JOIN userrolesdata ur ON u.u_ID = ur.ur_u_ID
                WHERE ur.role_ID = 4 
                  AND ur.user_role_status = 1
                  AND u.u_status = 1
                ORDER BY u.u_name
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($teachers)) {
                json_err('目前沒有可用的指導老師');
            } else {
                json_ok(['teachers' => $teachers]);
            }
        } catch (Throwable $e) {
            json_err('獲取指導老師列表失敗：' . $e->getMessage());
        }
        break;

    // 根據學號查詢學生資訊
    case 'get_student_info':
        try {
            $student_id = trim($p['student_id'] ?? '');
            if (empty($student_id)) {
                json_err('請輸入學號');
            }

            $sql = "
                SELECT u.u_ID, u.u_name, u.u_status
                FROM userdata u
                INNER JOIN userrolesdata ur ON u.u_ID = ur.ur_u_ID
                WHERE u.u_ID = ? 
                  AND ur.role_ID = 6 
                  AND ur.user_role_status = 1
                  AND u.u_status = 1
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                json_err('找不到該學號的學生，或該學生狀態異常');
            }

            // 檢查該學生是否已有團隊
            $teamUserField = 'team_u_ID';
            $stmt = $conn->prepare("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $teamUserField = 'u_ID';
            }

            $sql = "
                SELECT COUNT(*) 
                FROM teammember tm
                INNER JOIN teamdata t ON tm.team_ID = t.team_ID
                WHERE tm.{$teamUserField} = ? AND t.team_status = 1
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$student_id]);
            $hasTeam = $stmt->fetchColumn() > 0;

            if ($hasTeam) {
                json_err('該學生已有團隊，無法重複加入');
            }

            json_ok(['student' => $student]);
        } catch (Throwable $e) {
            json_err('查詢學生資訊失敗：' . $e->getMessage());
        }
        break;

    // 提交專題申請
    case 'submit_application':
        try {
            $u_ID = checkStudentPermission();
            
            $teacher_id = trim($p['teacher_id'] ?? '');
            $project_name = trim($p['project_name'] ?? '');
            $comment = trim($p['comment'] ?? '');
            $member_ids = json_decode($p['member_ids'] ?? '[]', true);
            
            if (empty($teacher_id)) {
                json_err('請選擇指導老師');
            }
            if (empty($project_name)) {
                json_err('請輸入專題名稱');
            }
            if (empty($member_ids) || !is_array($member_ids)) {
                json_err('請至少添加一個團隊成員');
            }

            // 檢查成員數量限制（最多3個學生，不包括申請人）
            $maxMembers = 3;
            $memberCount = count($member_ids);
            if ($memberCount > $maxMembers) {
                json_err("團隊成員數量超過限制，最多只能有 {$maxMembers} 個成員（不包括申請人）");
            }

            // 檢查申請者是否已在成員列表中
            if (!in_array($u_ID, $member_ids)) {
                $member_ids[] = $u_ID;
            }

            // 檢查所有成員是否都沒有團隊
            $teamUserField = 'team_u_ID';
            $stmt = $conn->prepare("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $teamUserField = 'u_ID';
            }

            foreach ($member_ids as $member_id) {
                $sql = "
                    SELECT COUNT(*) 
                    FROM teammember tm
                    INNER JOIN teamdata t ON tm.team_ID = t.team_ID
                    WHERE tm.{$teamUserField} = ? AND t.team_status = 1
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$member_id]);
                if ($stmt->fetchColumn() > 0) {
                    json_err("成員 {$member_id} 已有團隊，無法重複申請");
                }
            }

            // 處理圖片上傳
            $imageUrl = null;
            if (!empty($_FILES['apply_image']['name']) && $_FILES['apply_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['apply_image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    json_err('圖片格式只接受 JPG、PNG、WebP');
                }

                // 驗證 MIME 類型
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $allowedMime)) {
                    json_err('檔案格式不正確');
                }

                // 建立上傳資料夾
                $uploadDir = dirname(__DIR__) . '/uploads/team_apply/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                // 生成檔名
                $safeUid = preg_replace('/[^A-Za-z0-9_\-]/', '', $u_ID);
                $newName = 'apply_' . $safeUid . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $newName;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    json_err('圖片上傳失敗');
                }

                $imageUrl = 'uploads/team_apply/' . $newName;
            } else {
                json_err('請上傳專題申請表照片');
            }

            // 將成員列表存入 JSON 格式
            $memberJson = json_encode($member_ids, JSON_UNESCAPED_UNICODE);

            // 插入申請記錄到 teamapply
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("
                INSERT INTO teamapply (
                    tap_name, tap_member, tap_teacher, tap_url, tap_des, 
                    tap_status, tap_u_ID, tap_update_d
                ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
            ");
            $stmt->execute([
                $project_name,
                $memberJson,
                $teacher_id,
                $imageUrl,
                $comment,
                $u_ID
            ]);
            $tap_ID = $conn->lastInsertId();
            
            // 提交後狀態設為 1（待審核）
            $stmt = $conn->prepare("UPDATE teamapply SET tap_status = 1 WHERE tap_ID = ?");
            $stmt->execute([$tap_ID]);

            // 創建通知給科辦（role_ID=2）
            $stmt = $conn->prepare("
                SELECT ur_u_ID 
                FROM userrolesdata 
                WHERE role_ID = 2 AND user_role_status = 1
            ");
            $stmt->execute();
            $officeUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($officeUsers)) {
                // 創建通知
                $stmt = $conn->prepare("
                    INSERT INTO msgdata (
                        msg_title, msg_content, msg_type, msg_a_u_ID, 
                        msg_status, msg_start_d, msg_created_d
                    ) VALUES (
                        '專題申請通知', 
                        CONCAT('學生 ', (SELECT u_name FROM userdata WHERE u_ID = ?), ' 提交了專題申請表，請前往審核。'),
                        'SYSTEM_NOTICE',
                        'system',
                        1,
                        NOW(),
                        NOW()
                    )
                ");
                $stmt->execute([$u_ID]);
                $msg_ID = $conn->lastInsertId();

                // 為每個科辦用戶創建通知目標
                foreach ($officeUsers as $officeUID) {
                    $stmt = $conn->prepare("
                        INSERT INTO msgtargetdata (msg_ID, msg_target_type, msg_target_ID)
                        VALUES (?, 'USER', ?)
                    ");
                    $stmt->execute([$msg_ID, $officeUID]);
                }
            }

            $conn->commit();
            
            json_ok(['message' => '申請已提交，請等待科辦審核', 'tap_ID' => $tap_ID]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('提交申請失敗：' . $e->getMessage());
        }
        break;

    // 獲取待審核申請列表（科辦）
    case 'get_pending_applications':
        try {
            checkOfficePermission();
            
            $sql = "
                SELECT 
                    ta.tap_ID,
                    ta.tap_name,
                    ta.tap_member,
                    ta.tap_teacher,
                    ta.tap_url,
                    ta.tap_des,
                    ta.tap_status,
                    ta.tap_u_ID,
                    ta.tap_update_d,
                    u.u_name as submitter_name
                FROM teamapply ta
                INNER JOIN userdata u ON ta.tap_u_ID = u.u_ID
                WHERE ta.tap_status = 1
                ORDER BY ta.tap_update_d DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 解析每個申請的 JSON 資料
            foreach ($applications as &$app) {
                // 解析成員列表 JSON
                $member_ids = json_decode($app['tap_member'], true);
                if (!is_array($member_ids)) {
                    $member_ids = [];
                }
                $app['member_ids'] = $member_ids;
                $app['project_name'] = $app['tap_name'];
                $app['teacher_id'] = $app['tap_teacher'];
                $app['user_comment'] = $app['tap_des'] ?? '';
                $app['dcsub_url'] = $app['tap_url'] ?? '';
                $app['dcsub_u_ID'] = $app['tap_u_ID'];
                $app['dcsub_sub_d'] = $app['tap_update_d'];
                $app['sub_ID'] = $app['tap_ID']; // 為了兼容前端
                
                // 獲取成員姓名
                if (!empty($member_ids)) {
                    $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
                    $sql = "SELECT u_ID, u_name FROM userdata WHERE u_ID IN ($placeholders)";
                    $stmt2 = $conn->prepare($sql);
                    $stmt2->execute($member_ids);
                    $members = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    $app['members'] = $members;
                } else {
                    $app['members'] = [];
                }
                
                // 獲取指導老師姓名
                if (!empty($app['teacher_id'])) {
                    $stmt2 = $conn->prepare("SELECT u_name FROM userdata WHERE u_ID = ?");
                    $stmt2->execute([$app['teacher_id']]);
                    $teacher = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $app['teacher_name'] = $teacher['u_name'] ?? $app['teacher_id'];
                }
            }

            json_ok(['applications' => $applications]);
        } catch (Throwable $e) {
            json_err('獲取申請列表失敗：' . $e->getMessage());
        }
        break;

    // 審核申請（通過/退件）
    case 'review_application':
        try {
            $reviewer_ID = checkOfficePermission();
            
            $tap_ID = isset($p['tap_ID']) ? (int)$p['tap_ID'] : (isset($p['sub_ID']) ? (int)$p['sub_ID'] : 0); // 兼容兩種參數名稱
            $action = trim($p['action'] ?? ''); // 'approve' 或 'reject'
            $remark = trim($p['remark'] ?? '');

            if ($tap_ID <= 0) {
                json_err('申請ID無效');
            }
            if (!in_array($action, ['approve', 'reject'])) {
                json_err('操作無效');
            }

            // 獲取申請資料
            $stmt = $conn->prepare("
                SELECT * FROM teamapply WHERE tap_ID = ?
            ");
            $stmt->execute([$tap_ID]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                json_err('找不到該申請');
            }

            if ($application['tap_status'] != 1) {
                json_err('該申請已被處理');
            }

            // 解析成員列表
            $member_ids = json_decode($application['tap_member'], true);
            if (!is_array($member_ids)) {
                $member_ids = [];
            }

            $teacher_id = $application['tap_teacher'];
            $project_name = $application['tap_name'];
            $submitter_ID = $application['tap_u_ID'];
            $imageUrl = $application['tap_url'];

            $conn->beginTransaction();

            if ($action === 'approve') {
                // 通過：建立團隊
                if (empty($teacher_id) || empty($member_ids) || empty($project_name)) {
                    throw new Exception('申請資料不完整');
                }

                // 獲取當前屆別
                $stmt = $conn->prepare("
                    SELECT cohort_ID 
                    FROM cohortdata 
                    WHERE cohort_status = 1 
                    ORDER BY cohort_ID DESC 
                    LIMIT 1
                ");
                $stmt->execute();
                $cohort = $stmt->fetch(PDO::FETCH_ASSOC);
                $cohort_ID = $cohort['cohort_ID'] ?? null;

                // 獲取類組（預設第一個啟用的類組）
                $stmt = $conn->prepare("
                    SELECT group_ID 
                    FROM groupdata 
                    WHERE group_status = 1 
                    ORDER BY group_ID 
                    LIMIT 1
                ");
                $stmt->execute();
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                $group_ID = $group['group_ID'] ?? null;

                if (!$group_ID) {
                    throw new Exception('沒有可用的類組');
                }

                // 建立團隊到 teamdata
                $stmt = $conn->prepare("
                    INSERT INTO teamdata (
                        group_ID, team_project_name, cohort_ID, team_status, team_update_d, team_url
                    ) VALUES (?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([$group_ID, $project_name, $cohort_ID, $imageUrl]);
                $team_ID = $conn->lastInsertId();

                // 添加團隊成員（學生）到 teammember
                $teamUserField = 'team_u_ID';
                $stmt = $conn->prepare("SHOW COLUMNS FROM teammember LIKE 'team_u_ID'");
                $stmt->execute();
                if (!$stmt->fetch()) {
                    $teamUserField = 'u_ID';
                }

                foreach ($member_ids as $member_id) {
                    $stmt = $conn->prepare("
                        INSERT INTO teammember (team_ID, {$teamUserField}, tm_status, tm_updated_d, tm_url)
                        VALUES (?, ?, 1, NOW(), ?)
                    ");
                    $stmt->execute([$team_ID, $member_id, $imageUrl]);
                }

                // 添加指導老師到 teammember
                $stmt = $conn->prepare("
                    INSERT INTO teammember (team_ID, {$teamUserField}, tm_status, tm_updated_d, tm_url)
                    VALUES (?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([$team_ID, $teacher_id, $imageUrl]);

                // 更新申請狀態為已通過，記錄審核人和審核時間
                $stmt = $conn->prepare("
                    UPDATE teamapply 
                    SET tap_status = 3,
                        tap_rp_u_ID = ?,
                        tap_rp_d = NOW(),
                        tap_update_d = NOW()
                    WHERE tap_ID = ?
                ");
                $stmt->execute([$reviewer_ID, $tap_ID]);

                // 通知所有團隊成員
                $allMembers = array_merge($member_ids, [$teacher_id]);
                $stmt = $conn->prepare("
                    INSERT INTO msgdata (
                        msg_title, msg_content, msg_type, msg_a_u_ID, 
                        msg_status, msg_start_d, msg_created_d
                    ) VALUES (
                        '專題申請通過通知', 
                        CONCAT('您的專題申請「', ?, '」已通過審核，團隊已成功建立。'),
                        'SYSTEM_NOTICE',
                        'system',
                        1,
                        NOW(),
                        NOW()
                    )
                ");
                $stmt->execute([$project_name]);
                $msg_ID = $conn->lastInsertId();

                foreach ($allMembers as $memberUID) {
                    $stmt = $conn->prepare("
                        INSERT INTO msgtargetdata (msg_ID, msg_target_type, msg_target_ID)
                        VALUES (?, 'USER', ?)
                    ");
                    $stmt->execute([$msg_ID, $memberUID]);
                }

            } else {
                // 退件
                $stmt = $conn->prepare("
                    UPDATE teamapply 
                    SET tap_status = 2,
                        tap_rp_u_ID = ?,
                        tap_rp_d = NOW(),
                        tap_des = ?,
                        tap_update_d = NOW()
                    WHERE tap_ID = ?
                ");
                // 如果原本有說明文字，保留並加上退件原因
                $newDes = $application['tap_des'] ?? '';
                if ($remark) {
                    $newDes = ($newDes ? $newDes . "\n\n" : '') . '退件原因：' . $remark;
                }
                $stmt->execute([$reviewer_ID, $newDes, $tap_ID]);

                // 獲取提交者資訊（包含 email）
                $submitter_ID = $application['tap_u_ID'];
                $stmt = $conn->prepare("SELECT u_name, u_gmail FROM userdata WHERE u_ID = ?");
                $stmt->execute([$submitter_ID]);
                $submitter = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 發送 Gmail 通知給提交者
                if ($submitter && !empty($submitter['u_gmail'])) {
                    sendRejectionEmail($submitter['u_gmail'], $submitter['u_name'], $project_name, $remark);
                }
                $stmt = $conn->prepare("
                    INSERT INTO msgdata (
                        msg_title, msg_content, msg_type, msg_a_u_ID, 
                        msg_status, msg_start_d, msg_created_d
                    ) VALUES (
                        '專題申請退件通知', 
                        CONCAT('您的專題申請已被退件。', IF(? != '', CONCAT('退件原因：', ?), '')),
                        'SYSTEM_NOTICE',
                        'system',
                        1,
                        NOW(),
                        NOW()
                    )
                ");
                $stmt->execute([$remark, $remark]);
                $msg_ID = $conn->lastInsertId();

                $stmt = $conn->prepare("
                    INSERT INTO msgtargetdata (msg_ID, msg_target_type, msg_target_ID)
                    VALUES (?, 'USER', ?)
                ");
                $stmt->execute([$msg_ID, $submitter_ID]);
            }

            $conn->commit();
            
            json_ok(['message' => $action === 'approve' ? '申請已通過，團隊已建立' : '申請已退件']);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('審核失敗：' . $e->getMessage());
        }
        break;

    case 'get_my_application':
        try {
            $u_ID = checkStudentPermission();
            
            // 查詢該學生的申請記錄（按時間倒序，取最新的）
            // 包括：申請者本人 或 被申請的成員
            $stmt = $conn->prepare("
                SELECT 
                    tap_ID,
                    tap_name,
                    tap_member,
                    tap_teacher,
                    tap_url,
                    tap_des,
                    tap_status,
                    tap_update_d,
                    tap_rp_u_ID,
                    tap_rp_d,
                    tap_u_ID
                FROM teamapply
                WHERE tap_u_ID = ? AND tap_status IN (1, 2)
                ORDER BY tap_update_d DESC
                LIMIT 1
            ");
            $stmt->execute([$u_ID]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果沒有找到，檢查是否在 tap_member 中（被申請的成員）
            if (!$application) {
                $stmt = $conn->prepare("
                    SELECT 
                        tap_ID,
                        tap_name,
                        tap_member,
                        tap_teacher,
                        tap_url,
                        tap_des,
                        tap_status,
                        tap_update_d,
                        tap_rp_u_ID,
                        tap_rp_d,
                        tap_u_ID
                    FROM teamapply
                    WHERE tap_status IN (1, 2)
                    ORDER BY tap_update_d DESC
                ");
                $stmt->execute();
                $allApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($allApplications as $app) {
                    $member_ids = json_decode($app['tap_member'] ?? '[]', true);
                    if (is_array($member_ids) && in_array($u_ID, $member_ids)) {
                        $application = $app;
                        break;
                    }
                }
            }
            
            if (!$application) {
                json_ok(['application' => null]);
            }
            
            // 解析成員列表
            $member_ids = json_decode($application['tap_member'] ?? '[]', true);
            $members = [];
            if (is_array($member_ids)) {
                $placeholders = str_repeat('?,', count($member_ids) - 1) . '?';
                $stmt = $conn->prepare("SELECT u_ID, u_name FROM userdata WHERE u_ID IN ($placeholders)");
                $stmt->execute($member_ids);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // 獲取指導老師資訊
            $teacher = null;
            if (!empty($application['tap_teacher'])) {
                $stmt = $conn->prepare("SELECT u_ID, u_name FROM userdata WHERE u_ID = ?");
                $stmt->execute([$application['tap_teacher']]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // 獲取審核人資訊
            $reviewer = null;
            if (!empty($application['tap_rp_u_ID'])) {
                $stmt = $conn->prepare("SELECT u_ID, u_name FROM userdata WHERE u_ID = ?");
                $stmt->execute([$application['tap_rp_u_ID']]);
                $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            json_ok([
                'application' => [
                    'tap_ID' => $application['tap_ID'],
                    'tap_name' => $application['tap_name'],
                    'tap_url' => $application['tap_url'],
                    'tap_des' => $application['tap_des'],
                    'tap_status' => (int)$application['tap_status'],
                    'tap_update_d' => $application['tap_update_d'],
                    'tap_rp_d' => $application['tap_rp_d'],
                    'members' => $members,
                    'teacher' => $teacher,
                    'reviewer' => $reviewer
                ]
            ]);
        } catch (Throwable $e) {
            json_err('查詢申請資料失敗：' . $e->getMessage());
        }
        break;
}

