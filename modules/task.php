<?php
session_start();
require '../includes/pdo.php';
$p = $_POST;
switch ($_GET["do"]) {
    // 取得當下登入者的類組ID、類組名稱、團隊名稱
    case "get_now_group":
        echo json_encode(fetch(query("SELECT gd.group_ID,gd.group_name,td.team_project_name FROM `teammember` tm JOIN `teamdata` td JOIN `groupdata` gd ON tm.team_ID=td.team_ID AND td.group_ID=gd.group_ID WHERE tm.team_u_ID='{$_SESSION["u_ID"]}';")));
        break;
    // 搜尋當前登入者的所有組員(包含老師)
    case "get_now_teammember":
        $team_ID = fetch(query("SELECT team_ID FROM `teammember` WHERE team_u_ID='{$_SESSION["u_ID"]}';"))["team_ID"];
        $team_member = fetchAll(query("SELECT tm.team_u_ID,ud.u_name,ur.role_ID FROM `teammember` tm JOIN `userdata` ud JOIN `userrolesdata` ur ON tm.team_u_ID=ud.u_ID AND ur.ur_u_ID=ud.u_ID WHERE tm.team_ID={$team_ID};"));
        echo json_encode([
            "team_ID" => $team_ID,
            "team_member" => $team_member
        ]);
        break;
    // 取得所有基本需求，where狀態、時間
    case "get_requirement":
        echo json_encode(
            fetchAll(query("SELECT * FROM `requirementdata` WHERE `req_status` = 1 AND `req_start_d` <= CURDATE() AND ( `req_end_d` >= CURDATE() OR `req_end_d` IS NULL ) AND `group_ID` = '{$p["ID"]}';"))
        );
        break;
    // 取得該團隊的任務
    case "get_task":
        echo json_encode(fetchAll(query("SELECT td.*, creator.u_name AS creator_name, finisher.u_name AS done_name FROM taskdata td LEFT JOIN userdata creator ON td.task_u_ID = creator.u_ID LEFT JOIN userdata finisher ON td.task_done_u_ID = finisher.u_ID WHERE td.task_team_ID = {$p["team_ID"]} ORDER BY td.task_status, td.task_priority DESC, td.task_end_d;")));
        break;

    case "new_task_submit":
        // 取得 cohort_ID
        $cohort = substr($_SESSION["u_ID"], 0, 3);
        $cohort_ID = fetch(query("SELECT cohort_ID FROM cohortdata WHERE year_label={$cohort}"))["cohort_ID"];
        // 1️⃣ 空字串處理函式
        function toNull($v)
        {
            return ($v === "" ? "NULL" : "'$v'");
        }
        // 2️⃣ 各欄位套用
        $title = toNull($p["form"]["title"]);
        $desc = toNull($p["form"]["desc"]);
        $start_d = toNull($p["form"]["start_d"]);
        $end_d = toNull($p["form"]["end_d"]);
        $select1 = toNull($p["form"]["select1"]);
        $select2 = toNull($p["form"]["select2"]);
        $selectTask = toNull($p["form"]["who_task"]);   // task_done_u_ID
        $team = toNull($p["now_team_ID"]);
        $status = ($p["form"]["who_task"] ? '1' : '0');
        // 3️⃣ SQL
        $sql = "INSERT INTO taskdata 
        (task_ID, task_team_ID, task_u_ID, task_cohort_ID, ms_ID, req_ID,
         task_title, task_desc, task_start_d, task_end_d, task_done_u_ID, 
         task_done_d, task_status, task_priority, task_created_u_ID, task_created_d)
        VALUES(
            NULL,
            $team,
            '{$_SESSION["u_ID"]}',
            '$cohort_ID',
            $select1,
            $select2,
            $title,
            $desc,
            $start_d,
            $end_d,
            $selectTask,
            NULL,
            $status,
            {$p["form"]["priority"]},
            '{$_SESSION["u_ID"]}',
            CURRENT_TIMESTAMP)";
        query($sql);
        break;

    case "edit_task_submit":
        // 取得 cohort_ID
        $cohort = substr($_SESSION["u_ID"], 0, 3);
        $cohort_ID = fetch(query("SELECT cohort_ID FROM cohortdata WHERE year_label={$cohort}"))["cohort_ID"];
        // 空字串處理函式
        function toNull($v)
        {
            return ($v === "" ? "NULL" : "'$v'");
        }
        // 各欄位套用
        $title = toNull($p["form"]["title"]);
        $desc = toNull($p["form"]["desc"]);
        $start_d = toNull($p["form"]["start_d"]);
        $end_d = toNull($p["form"]["end_d"]);
        $select1 = toNull($p["form"]["select1"]);
        $select2 = toNull($p["form"]["select2"]);
        $selectTask = toNull($p["form"]["who_task"]); // task_done_u_ID
        $team = toNull($p["now_team_ID"]);
        $status = ($p["form"]["who_task"] ? '1' : '0');
        // UPDATE
        $sql = "
    UPDATE taskdata SET
        task_team_ID    = $team,
        task_u_ID       = '{$_SESSION["u_ID"]}',
        task_cohort_ID  = '$cohort_ID',
        ms_ID           = $select1,
        req_ID          = $select2,
        task_title      = $title,
        task_desc       = $desc,
        task_start_d    = $start_d,
        task_end_d      = $end_d,
        task_done_u_ID  = $selectTask,
        task_priority   = {$p["form"]["priority"]},
        task_status     = $status
    WHERE task_ID = '{$p["id"]}'";
        query($sql);
        break;

    case "take_task":
        query("UPDATE `taskdata` SET `task_done_u_ID` = '{$_SESSION["u_ID"]}', `task_status` = '{$p["status"]}', `task_done_d` = CURRENT_TIMESTAMP() WHERE `taskdata`.`task_ID` = {$p["id"]};");
        break;
    // case "req_return_submit":
    //     query("INSERT INTO `reprogressdata` (`rp_ID`, `req_ID`, `rp_team_ID`, `rp_u_ID`, `rp_status`, `rp_completed_d`, `rp_approved_d`, `rp_approved_u_ID`, `rp_remark`) VALUES (NULL, '{$p["req_ID"]}', '{$p["now_team_ID"]}', '{$_SESSION["u_ID"]}', '1', CURRENT_TIMESTAMP(), NULL, NULL, '{$p["form"]["rp_remark"]}');");
    //     break;






}
?>