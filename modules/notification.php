<?php
/**
 * 通知系統 API 模組
 * 
 * 修改記錄：
 * 2025-11-16 - 創建通知系統API
 *   改動內容：獲取用戶通知列表，更新通知數量
 *   相關功能：通知中心顯示
 *   方式：從 msgdata 和 msgtargetdata 表查詢通知
 */

global $conn;
$p = $_POST;
$do = $_GET['do'] ?? '';
$u_ID = $_SESSION['u_ID'] ?? null;

switch ($do) {
    // 獲取用戶通知列表
    case 'get_notifications':
        if (!$u_ID) {
            json_err('請先登入', 'NOT_LOGGED_IN', 401);
        }
        
        try {
            // 獲取發送給當前用戶的未讀通知
            // 包括：目標為該用戶的通知，或目標為ALL的通知
            // 只返回未讀的通知（點擊後會消失，未點擊的會一直顯示）
            $sql = "
                SELECT DISTINCT
                    m.msg_ID,
                    m.msg_title,
                    m.msg_content,
                    m.msg_type,
                    m.msg_start_d,
                    m.msg_created_d,
                    m.msg_a_u_ID,
                    0 as is_read
                FROM msgdata m
                INNER JOIN msgtargetdata mt ON m.msg_ID = mt.msg_ID
                LEFT JOIN msgreaddata mr ON m.msg_ID = mr.msg_ID AND mr.read_u_ID = ?
                WHERE m.msg_status = 1
                  AND (m.msg_start_d IS NULL OR m.msg_start_d <= NOW())
                  AND (m.msg_end_d IS NULL OR m.msg_end_d >= NOW())
                  AND (
                      mt.msg_target_type = 'ALL' 
                      OR (mt.msg_target_type = 'USER' AND mt.msg_target_ID = ?)
                  )
                  AND mr.msg_ID IS NULL
                ORDER BY m.msg_created_d DESC
                LIMIT 50
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$u_ID, $u_ID]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($notifications, JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'載入通知失敗：'.$e->getMessage()]);
            exit;
        }
        break;
    
    // 獲取未讀通知數量
    case 'get_notification_count':
        if (!$u_ID) {
            json_ok(['count' => 0]);
            exit;
        }
        
        try {
            $sql = "
                SELECT COUNT(DISTINCT m.msg_ID) as unread_count
                FROM msgdata m
                INNER JOIN msgtargetdata mt ON m.msg_ID = mt.msg_ID
                LEFT JOIN msgreaddata mr ON m.msg_ID = mr.msg_ID AND mr.read_u_ID = ?
                WHERE m.msg_status = 1
                  AND (m.msg_start_d IS NULL OR m.msg_start_d <= NOW())
                  AND (m.msg_end_d IS NULL OR m.msg_end_d >= NOW())
                  AND (
                      mt.msg_target_type = 'ALL' 
                      OR (mt.msg_target_type = 'USER' AND mt.msg_target_ID = ?)
                  )
                  AND mr.msg_ID IS NULL
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$u_ID, $u_ID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['unread_count'] ?? 0);
            
            json_ok(['count' => $count]);
            exit;
        } catch (Throwable $e) {
            json_ok(['count' => 0]);
            exit;
        }
        break;
    
    // 標記通知為已讀
    case 'mark_notification_read':
        if (!$u_ID) {
            json_err('請先登入', 'NOT_LOGGED_IN', 401);
        }
        
        $msg_ID = isset($p['msg_ID']) ? (int)$p['msg_ID'] : 0;
        if ($msg_ID <= 0) {
            json_err('通知ID無效');
        }
        
        try {
            // 檢查是否已讀
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM msgreaddata 
                WHERE msg_ID = ? AND read_u_ID = ?
            ");
            $stmt->execute([$msg_ID, $u_ID]);
            
            if (!$stmt->fetchColumn()) {
                // 如果未讀，則插入已讀記錄
                $stmt = $conn->prepare("
                    INSERT INTO msgreaddata (msg_ID, read_u_ID, msg_read_d)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$msg_ID, $u_ID]);
            }
            
            json_ok(['message' => '已標記為已讀']);
        } catch (Throwable $e) {
            json_err('操作失敗：'.$e->getMessage());
        }
        break;
}

