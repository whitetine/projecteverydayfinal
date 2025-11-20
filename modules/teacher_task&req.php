<?php
session_start();
require '../includes/pdo.php';
$p = $_POST;
switch ($_GET["do"]) {
    // 搜尋當前登入者的所有組員(包含老師)
    case "get_now_teammember":
        $u = $_SESSION["u_ID"];
        // 1️⃣ 找出所有 team_ID
        $teamRows = fetchAll(query("SELECT team_ID FROM teammember WHERE team_u_ID='{$u}'"));
        $team_IDs = array_unique(array_column($teamRows, "team_ID"));
        // 如果沒有 team，回傳空
        if (!$team_IDs) {
            echo json_encode([
                "team_IDs" => [],
                "team_member" => []
            ]);
            exit;
        }
        // 2️⃣ 找出屬於這些 team 的所有隊員
        $team_IDmembers = fetchAll(query("SELECT tm.team_ID,td.team_project_name FROM teammember tm JOIN userdata ud JOIN teamdata td ON tm.team_u_ID = ud.u_ID and td.team_ID=tm.team_ID  WHERE td.team_status=1 AND tm.team_ID IN (" . implode(",", $team_IDs) . ") GROUP BY team_ID"));
        // 找出該老師的所有組資料
        $team_member = fetchAll(query("SELECT tm.team_ID, tm.team_u_ID, ud.u_name, ur.role_ID,td.team_project_name FROM teammember tm JOIN userdata ud JOIN teamdata td ON tm.team_u_ID = ud.u_ID and td.team_ID=tm.team_ID JOIN userrolesdata ur ON ur.ur_u_ID = ud.u_ID WHERE  tm.team_ID IN (" . implode(",", $team_IDs) . ")"));
        echo json_encode([
            "team_IDs" => $team_IDmembers,
            "team_member" => $team_member
        ]);
        break;
    // 取得所有基本需求，where狀態、時間
    case "get_requirement":
        echo json_encode(
            fetchAll(query("SELECT r.*,u.u_name,CASE WHEN rp.rp_status IS NULL THEN 0 ELSE rp.rp_status END AS status FROM `requirementdata` AS r LEFT JOIN (SELECT rp1.* FROM `reprogressdata` AS rp1 INNER JOIN (SELECT req_ID,MAX(rp_ID) AS max_rp_ID FROM `reprogressdata` WHERE rp_team_ID='{$p["now_team_ID"]}' GROUP BY req_ID) AS t ON t.req_ID=rp1.req_ID AND t.max_rp_ID=rp1.rp_ID WHERE rp1.rp_team_ID='{$p["now_team_ID"]}') AS rp ON rp.req_ID=r.req_ID LEFT JOIN `userdata` AS u ON u.u_ID=rp.rp_u_ID WHERE r.req_status=1 AND r.req_start_d<=CURDATE() AND (r.req_end_d>=CURDATE() OR r.req_end_d IS NULL) AND r.group_ID='{$p["ID"]["ID"]}' ORDER BY `status`;"))
        );
        break;
    // 取得當下登入者的類組ID、類組名稱、團隊名稱
    case "get_now_group":
        echo json_encode(fetch(query("SELECT gd.group_ID,gd.group_name,td.team_project_name FROM `teammember` tm JOIN `teamdata` td JOIN `groupdata` gd ON tm.team_ID=td.team_ID AND td.group_ID=gd.group_ID WHERE tm.team_u_ID='{$_SESSION["u_ID"]}';")));
        break;
    // 取得該團隊的任務
    case "get_task":
        echo json_encode(fetchAll(query("SELECT td.*, creator.u_name AS creator_name, finisher.u_name AS done_name FROM taskdata td LEFT JOIN userdata creator ON td.task_u_ID = creator.u_ID LEFT JOIN userdata finisher ON td.task_done_u_ID = finisher.u_ID WHERE td.task_team_ID = {$p["team_ID"]} ORDER BY td.task_status, td.task_priority DESC, td.task_end_d;")));
        break;

    case "req_return_click":
        query("UPDATE `reprogressdata` SET `rp_status` = '{$p["status"]}' WHERE `reprogressdata`.`req_ID` = {$p["req_ID"]};");
        break;



}
?>