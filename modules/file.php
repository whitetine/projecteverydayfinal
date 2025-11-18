<?php
global $conn;
$p = $_POST ?? [];
$do = $_GET['do'] ?? '';

/* -----------------------------------------------------------
   JSON 輸出
------------------------------------------------------------ */
function json_ok($extra = [])
{
    echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err($msg)
{
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/* -----------------------------------------------------------
   取得文件、目標
------------------------------------------------------------ */
if ($do === 'get_files_with_targets') {

    $rows = $conn->query("
        SELECT 
            doc_ID,
            doc_name,
            doc_des,
            doc_example,
            is_required,
            doc_start_d,
            doc_end_d,
            doc_status,
            is_top,
            doc_created_d
        FROM docdata
        ORDER BY is_top DESC, doc_ID DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $doc_ID = $row['doc_ID'];

        $t = $conn->prepare("
            SELECT doc_target_type, doc_target_ID
            FROM doctargetdata
            WHERE doc_ID=?
        ");
        $t->execute([$doc_ID]);
        $targets = $t->fetchAll(PDO::FETCH_ASSOC);

        $row['target_all'] = false;
        $row['target_cohorts'] = [];
        $row['target_grades'] = [];
        $row['target_classes'] = [];

        foreach ($targets as $tg) {
            if ($tg['doc_target_type'] === 'COHORT') {
                $row['target_cohorts'][] = $tg['doc_target_ID'];
            } elseif ($tg['doc_target_type'] === 'GRADE') {
                $row['target_grades'][] = $tg['doc_target_ID'];
            } elseif ($tg['doc_target_type'] === 'CLASS') {
                $row['target_classes'][] = $tg['doc_target_ID'];
            } elseif ($tg['doc_target_type'] === 'ALL') {
                $row['target_all'] = true;
            }
        }
    }

    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($do === 'upload_file_with_targets') {

    $doc_name = trim($p['doc_name'] ?? '');
    $doc_des = trim($p['doc_des'] ?? '');
    $is_required = intval($p['is_required'] ?? 0);
    $doc_start_d = $p['doc_start_d'] ?: null;
    $doc_end_d = $p['doc_end_d'] ?: null;

    if ($doc_name === '')
        json_err("缺少文件名稱");

    if (empty($_FILES['doc_example']['name']))
        json_err("請選擇 PDF 檔案");

    // 取得 targets
    $cohorts = json_decode($p['target_cohorts'] ?? '[]', true);
    $grades = json_decode($p['target_grades'] ?? '[]', true);
    $classes = json_decode($p['target_classes'] ?? '[]', true);

    /* 上傳 PDF */
    $ext = strtolower(pathinfo($_FILES['doc_example']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf')
        json_err("只允許上傳 PDF");

    $dir = __DIR__ . '/../templates';
    if (!is_dir($dir))
        mkdir($dir, 0775, true);

    $saveName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
    $savePath = $dir . '/' . $saveName;

    if (!move_uploaded_file($_FILES['doc_example']['tmp_name'], $savePath))
        json_err("PDF 儲存失敗");

    $file_url = 'templates/' . $saveName;

    try {
        $conn->beginTransaction();

        // 建立 doc
        $stmt = $conn->prepare("
            INSERT INTO docdata
            (doc_name, doc_des, doc_example, is_required, doc_start_d, doc_end_d,
             doc_status, is_top, doc_u_ID, doc_created_d)
            VALUES (?, ?, ?, ?, ?, ?, 1, 0, 0, NOW())
        ");
        $stmt->execute([
            $doc_name,
            $doc_des,
            $file_url,
            $is_required,
            $doc_start_d,
            $doc_end_d
        ]);

        $doc_ID = intval($conn->lastInsertId());

        // 插入目標
        $insert = $conn->prepare("
            INSERT INTO doctargetdata (doc_ID, doc_target_type, doc_target_ID)
            VALUES (?, ?, ?)
        ");

        foreach ($cohorts as $id)
            $insert->execute([$doc_ID, 'COHORT', $id]);

        foreach ($grades as $id)
            $insert->execute([$doc_ID, 'GRADE', $id]);

        foreach ($classes as $id)
            $insert->execute([$doc_ID, 'CLASS', $id]);

        $conn->commit();
        json_ok(['doc_ID' => $doc_ID]);

    } catch (Throwable $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        json_err("新增失敗：" . $e->getMessage());
    }
}

/* -----------------------------------------------------------
   編輯（update_file_with_targets）
------------------------------------------------------------ */
if ($do === 'update_file_with_targets') {

    $doc_ID = intval($p['file_ID'] ?? 0);
    if ($doc_ID <= 0)
        json_err("doc_ID 無效");

    $doc_name = trim($p['doc_name'] ?? '');
    $doc_des = trim($p['doc_des'] ?? '');
    $is_required = intval($p['is_required'] ?? 0);
    $doc_start_d = $p['doc_start_d'] ?: null;
    $doc_end_d = $p['doc_end_d'] ?: null;

    if ($doc_name === '')
        json_err("缺少文件名稱");

    $cohorts = json_decode($p['target_cohorts'] ?? '[]', true);
    $grades = json_decode($p['target_grades'] ?? '[]', true);
    $classes = json_decode($p['target_classes'] ?? '[]', true);

    $updatePDF = !empty($_FILES['doc_example']['name']);

    if ($updatePDF) {
        $ext = strtolower(pathinfo($_FILES['doc_example']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf')
            json_err("只允許 PDF");

        $dir = __DIR__ . '/../templates';
        if (!is_dir($dir))
            mkdir($dir, 0775, true);

        $saveName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $savePath = $dir . '/' . $saveName;
        if (!move_uploaded_file($_FILES['doc_example']['tmp_name'], $savePath))
            json_err("PDF 儲存失敗");

        $file_url = 'templates/' . $saveName;
    }

    try {
        $conn->beginTransaction();

        /* 更新 docdata */
        $sql = "
            UPDATE docdata
            SET doc_name=?, doc_des=?, is_required=?, doc_start_d=?, doc_end_d=?
        ";
        $params = [$doc_name, $doc_des, $is_required, $doc_start_d, $doc_end_d];

        if ($updatePDF) {
            $sql .= ", doc_example=?";
            $params[] = $file_url;
        }

        $sql .= " WHERE doc_ID=?";
        $params[] = $doc_ID;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        /* 重建目標 */
        $conn->prepare("DELETE FROM doctargetdata WHERE doc_ID=?")->execute([$doc_ID]);

        $insert = $conn->prepare("
            INSERT INTO doctargetdata (doc_ID, doc_target_type, doc_target_ID)
            VALUES (?, ?, ?)
        ");

        foreach ($cohorts as $id)
            $insert->execute([$doc_ID, 'COHORT', $id]);
        foreach ($grades as $id)
            $insert->execute([$doc_ID, 'GRADE', $id]);
        foreach ($classes as $id)
            $insert->execute([$doc_ID, 'CLASS', $id]);

        $conn->commit();
        json_ok();

    } catch (Throwable $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        json_err("更新失敗：" . $e->getMessage());
    }
}

/* -----------------------------------------------------------
   更新狀態 / 置頂
------------------------------------------------------------ */
if ($do === 'update_template') {

    $req = json_decode(file_get_contents("php://input"), true) ?? [];

    $doc_ID = intval($req['file_ID'] ?? 0);
    $doc_status = intval($req['file_status'] ?? 0);
    $is_top = intval($req['is_top'] ?? 0);

    if ($doc_ID <= 0)
        json_err("doc_ID 無效");

    $stmt = $conn->prepare("
        UPDATE docdata
        SET doc_status=?, is_top=?
        WHERE doc_ID=?
    ");
    $stmt->execute([$doc_status, $is_top, $doc_ID]);

    json_ok();
}

/* -----------------------------------------------------------
   刪除
------------------------------------------------------------ */
if ($do === 'delete_file') {

    $req = json_decode(file_get_contents("php://input"), true) ?? [];
    $doc_ID = intval($req['file_ID'] ?? 0);

    if ($doc_ID <= 0)
        json_err("doc_ID 無效");

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT doc_example FROM docdata WHERE doc_ID=?");
        $stmt->execute([$doc_ID]);
        $row = $stmt->fetch();

        // 刪 docdata
        $conn->prepare("DELETE FROM docdata WHERE doc_ID=?")->execute([$doc_ID]);

        // 刪 PDF
        if ($row && $row['doc_example']) {
            $path = __DIR__ . '/../' . $row['doc_example'];
            if (file_exists($path))
                @unlink($path);
        }

        // 刪目標
        $conn->prepare("DELETE FROM doctargetdata WHERE doc_ID=?")
            ->execute([$doc_ID]);

        $conn->commit();
        json_ok();

    } catch (Throwable $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        json_err("刪除失敗：" . $e->getMessage());
    }
}
?>