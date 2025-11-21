<?php
global $conn;
$p = $_POST;
$do = $_GET['do'] ?? '';
//c9 
$normalizeDateTime = static function ($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    return $value;
};

$decodeTargetList = static function ($value): array {
    if (is_array($value)) {
        return array_values(array_unique(array_map('strval', $value)));
    }
    if ($value === null || $value === '') {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_unique(array_map('strval', $decoded)));
};

$buildTargetPayload = static function ($source, bool $defaultAll = false) use ($decodeTargetList): array {
    $all = (int) ($source['doc_target_all'] ?? $source['target_all'] ?? $source['doc_target_ALL'] ?? 0);
    $payload = [
        'doc_target_all' => $all ? 1 : 0,
        'doc_target_cohorts' => $decodeTargetList($source['doc_target_cohorts'] ?? $source['target_cohorts'] ?? []),
        'doc_target_grades' => $decodeTargetList($source['doc_target_grades'] ?? $source['target_grades'] ?? []),
        'doc_target_classes' => $decodeTargetList($source['doc_target_classes'] ?? $source['target_classes'] ?? []),
        'doc_target_groups' => $decodeTargetList($source['doc_target_groups'] ?? $source['target_groups'] ?? []),
    ];

    if (
        $defaultAll
        && !$payload['doc_target_all']
        && !$payload['doc_target_cohorts']
        && !$payload['doc_target_grades']
        && !$payload['doc_target_classes']
        && !$payload['doc_target_groups']
    ) {
        $payload['doc_target_all'] = 1;
    }

    return $payload;
};

$resolveUploadField = static function () {
    if (!empty($_FILES['doc_file']['name'])) {
        return $_FILES['doc_file'];
    }
    if (!empty($_FILES['file']['name'])) {
        return $_FILES['file'];
    }
    return null;
};

$saveUploadedPdf = static function (array $fileField): array {
    $ext = strtolower(pathinfo($fileField['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        json_err('僅允許 PDF');
    }

    $dir = __DIR__ . '/../uploads/doc/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        json_err('無法建立檔案目錄');
    }

    $saveName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . '.pdf';
    $savePath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $saveName;

    if (!move_uploaded_file($fileField['tmp_name'], $savePath)) {
        json_err('PDF 儲存失敗');
    }

    return [
        'relative' => 'uploads/doc/' . $saveName,
        'absolute' => $savePath,
    ];
};

$deletePhysicalFile = static function (?string $relativePath): void {
    if (!$relativePath) {
        return;
    }
    $root = realpath(__DIR__ . '/..');
    $fullPath = $root ? $root . '/' . ltrim($relativePath, '/') : __DIR__ . '/../' . ltrim($relativePath, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
};

$ensureTargetTable = static function () use ($conn): void {
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS doctargetdata (
                doc_ID INT NOT NULL,
                doc_target_type ENUM('ALL','COHORT','GRADE','CLASS','TEAM','USER','GROUP') NOT NULL,
                doc_target_ID VARCHAR(50) NOT NULL,
                PRIMARY KEY (doc_ID, doc_target_type, doc_target_ID),
                FOREIGN KEY (doc_ID) REFERENCES docdata(doc_ID) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    } catch (Throwable $e) {
        // ignore
    }
};

$hydrateTargets = static function (array &$rows) use ($conn, $ensureTargetTable): void {
    if (!$rows) {
        return;
    }
    $docIds = array_column($rows, 'doc_ID');
    if (!$docIds) {
        return;
    }

    $ensureTargetTable();
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $stmt = $conn->prepare("
        SELECT doc_ID, doc_target_type, doc_target_ID
        FROM doctargetdata
        WHERE doc_ID IN ($placeholders)
    ");
    try {
        $stmt->execute($docIds);
    } catch (Throwable $e) {
        return;
    }

    $map = [];
    while ($target = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[$target['doc_ID']][] = $target;
    }

    foreach ($rows as &$row) {
        $row['doc_target_all'] = false;
        $row['doc_target_cohorts'] = [];
        $row['doc_target_grades'] = [];
        $row['doc_target_classes'] = [];
        $row['doc_target_groups'] = [];

        $targets = $map[$row['doc_ID']] ?? [];
        foreach ($targets as $target) {
            switch ($target['doc_target_type']) {
                case 'ALL':
                    $row['doc_target_all'] = true;
                    break;
                case 'COHORT':
                    $row['doc_target_cohorts'][] = $target['doc_target_ID'];
                    break;
                case 'GRADE':
                    $row['doc_target_grades'][] = $target['doc_target_ID'];
                    break;
                case 'CLASS':
                    $row['doc_target_classes'][] = $target['doc_target_ID'];
                    break;
                case 'GROUP':
                    $row['doc_target_groups'][] = $target['doc_target_ID'];
                    break;
            }
        }
    }
    unset($row);
};

$fetchDocs = static function (bool $onlyActive = false, bool $withTargets = false) use ($conn, $hydrateTargets): array {
    $sql = "
        SELECT
            doc_ID,
            doc_name,
            doc_des,
            doc_type,
            doc_example,
            is_top,
            is_required,
            doc_start_d,
            doc_end_d,
            doc_status,
            doc_u_ID,
            doc_created_d
        FROM docdata
    ";
    if ($onlyActive) {
        $sql .= " WHERE doc_status = 1";
    }
    $sql .= " ORDER BY is_top DESC, doc_ID DESC";

    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['doc_ID'] = (int) $row['doc_ID'];
        $row['is_top'] = (int) ($row['is_top'] ?? 0);
        $row['is_required'] = (int) ($row['is_required'] ?? 0);
        $row['doc_status'] = (int) ($row['doc_status'] ?? 0);
    }
    unset($row);

    if ($withTargets) {
        $hydrateTargets($rows);
    }

    return $rows;
};

$syncTargets = static function (int $docId, array $targets) use ($conn): void {
    $stmt = $conn->prepare("DELETE FROM doctargetdata WHERE doc_ID = ?");
    $stmt->execute([$docId]);

    if ($targets['doc_target_all']) {
        $insert = $conn->prepare("
            INSERT INTO doctargetdata (doc_ID, doc_target_type, doc_target_ID)
            VALUES (?, 'ALL', '1')
        ");
        $insert->execute([$docId]);
        return;
    }

    $insert = $conn->prepare("
        INSERT INTO doctargetdata (doc_ID, doc_target_type, doc_target_ID)
        VALUES (?, ?, ?)
    ");

    $map = [
        'COHORT' => $targets['doc_target_cohorts'] ?? [],
        'GRADE' => $targets['doc_target_grades'] ?? [],
        'CLASS' => $targets['doc_target_classes'] ?? [],
        'GROUP' => $targets['doc_target_groups'] ?? [],
    ];

    foreach ($map as $type => $values) {
        foreach ($values as $value) {
            try {
                $insert->execute([$docId, $type, (string) $value]);
            } catch (Throwable $e) {
                // ignore duplicates
            }
        }
    }
};

$readStudentInfo = static function (?string $u_ID) use ($conn): ?array {
    if (!$u_ID) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT cohort_ID, class_ID, enroll_grade
        FROM enrollmentdata
        WHERE enroll_u_ID = ? AND enroll_status = 1
        ORDER BY enroll_created_d DESC
        LIMIT 1
    ");
    $stmt->execute([$u_ID]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
};

$handleUploadDoc = static function (bool $defaultAllTargets = false) use ($conn, $normalizeDateTime, $buildTargetPayload, $resolveUploadField, $saveUploadedPdf, $deletePhysicalFile, $syncTargets, $ensureTargetTable) {
    $p = $_POST;
    $doc_name = trim($p['doc_name'] ?? $p['file_name'] ?? $p['f_name'] ?? '');
    if ($doc_name === '') {
        json_err('缺少表單名稱');
    }

    $fileField = $resolveUploadField();
    if (!$fileField || empty($fileField['name'])) {
        json_err('請選擇 PDF');
    }
    $saved = $saveUploadedPdf($fileField);
    $doc_example = $saved['relative'];

    $doc_des = trim($p['doc_des'] ?? $p['file_des'] ?? '');
    $is_required = (int) ($p['is_required'] ?? $p['doc_is_required'] ?? 0) ? 1 : 0;
    $doc_start_d = $normalizeDateTime($p['doc_start_d'] ?? $p['file_start_d'] ?? null);
    $doc_end_d = $normalizeDateTime($p['doc_end_d'] ?? $p['file_end_d'] ?? null);
    $targets = $buildTargetPayload($p, $defaultAllTargets);

    try {
        $ensureTargetTable();
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO docdata
            (doc_name, doc_des, doc_type, doc_example, is_top, is_required,
             doc_start_d, doc_end_d, doc_status, doc_u_ID, doc_created_d)
            VALUES (?, ?, 'pdf', ?, 0, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([
            $doc_name,
            $doc_des,
            $doc_example,
            $is_required,
            $doc_start_d,
            $doc_end_d,
            $_SESSION['u_ID'] ?? null,
        ]);
        $docId = (int) $conn->lastInsertId();

        $syncTargets($docId, $targets);
        $conn->commit();

        json_ok([
            'doc_ID' => $docId,
            'doc_example' => $doc_example,
        ]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $deletePhysicalFile($doc_example);
        json_err('資料寫入失敗：' . $e->getMessage());
    }
};

$handleUpdateDoc = static function (int $docId) use ($conn, $normalizeDateTime, $buildTargetPayload, $resolveUploadField, $saveUploadedPdf, $deletePhysicalFile, $syncTargets, $ensureTargetTable) {
    $p = $_POST;
    $doc_name = trim($p['doc_name'] ?? $p['file_name'] ?? '');
    if ($doc_name === '') {
        json_err('缺少表單名稱');
    }

    $doc_des = trim($p['doc_des'] ?? $p['file_des'] ?? '');
    $is_required = (int) ($p['is_required'] ?? $p['doc_is_required'] ?? 0) ? 1 : 0;
    $doc_start_d = $normalizeDateTime($p['doc_start_d'] ?? $p['file_start_d'] ?? null);
    $doc_end_d = $normalizeDateTime($p['doc_end_d'] ?? $p['file_end_d'] ?? null);
    $targets = $buildTargetPayload($p);

    $fileField = $resolveUploadField();
    $newFile = null;
    $oldFile = null;

    try {
        $ensureTargetTable();
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT doc_example FROM docdata WHERE doc_ID = ?");
        $stmt->execute([$docId]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$found) {
            $conn->rollBack();
            json_err('找不到文件');
        }
        $oldFile = $found['doc_example'] ?? null;

        $update = [
            'doc_name = ?',
            'doc_des = ?',
            'is_required = ?',
            'doc_start_d = ?',
            'doc_end_d = ?',
            'doc_status = 1',
        ];
        $values = [
            $doc_name,
            $doc_des,
            $is_required,
            $doc_start_d,
            $doc_end_d,
        ];

        if ($fileField && !empty($fileField['name'])) {
            $saved = $saveUploadedPdf($fileField);
            $newFile = $saved['relative'];
            $update[] = 'doc_example = ?';
            $values[] = $newFile;
        }

        $values[] = $docId;
        $stmt = $conn->prepare("
            UPDATE docdata
            SET " . implode(', ', $update) . "
            WHERE doc_ID = ?
        ");
        $stmt->execute($values);

        $syncTargets($docId, $targets);
        $conn->commit();

        if ($newFile && $oldFile && $newFile !== $oldFile) {
            $deletePhysicalFile($oldFile);
        }
        json_ok();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($newFile) {
            $deletePhysicalFile($newFile);
        }
        json_err('更新失敗：' . $e->getMessage());
    }
};

switch ($do) {
    case 'get_all_TemplatesFile':
        $rows = $fetchDocs(true, false);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;

    case 'get_files':
        $rows = $fetchDocs(false, false);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;

    case 'get_files_with_targets':
        $rows = $fetchDocs(false, true);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;

    case 'listActiveFiles':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $docs = $fetchDocs(true, true);
        $studentInfo = $readStudentInfo($_SESSION['u_ID'] ?? null);
        $now = date('Y-m-d H:i:s');
        $visible = [];

        foreach ($docs as $doc) {
            if (!empty($doc['doc_start_d']) && $doc['doc_start_d'] > $now) {
                continue;
            }
            if (!empty($doc['doc_end_d']) && $doc['doc_end_d'] < $now) {
                continue;
            }

            $show = false;
            if (!empty($doc['doc_target_all'])) {
                $show = true;
            } elseif (
                empty($doc['doc_target_cohorts']) &&
                empty($doc['doc_target_grades']) &&
                empty($doc['doc_target_classes']) &&
                empty($doc['doc_target_groups'])
            ) {
                $show = true;
            } elseif ($studentInfo) {
                if ($doc['doc_target_cohorts'] && in_array((string) $studentInfo['cohort_ID'], $doc['doc_target_cohorts'], true)) {
                    $show = true;
                } elseif ($doc['doc_target_grades'] && in_array((string) $studentInfo['enroll_grade'], $doc['doc_target_grades'], true)) {
                    $show = true;
                } elseif ($doc['doc_target_classes'] && in_array((string) $studentInfo['class_ID'], $doc['doc_target_classes'], true)) {
                    $show = true;
                }
            }

            if ($show) {
                $visible[] = $doc;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($visible, JSON_UNESCAPED_UNICODE);
        exit;

    case 'upload_template':
        $handleUploadDoc(true);
        break;

    case 'upload_file_with_targets':
        $handleUploadDoc(false);
        break;

    case 'update_file_with_targets':
        $doc_ID = (int) ($p['doc_ID'] ?? $p['file_ID'] ?? 0);
        if ($doc_ID <= 0) {
            json_err('doc_ID 無效');
        }
        $handleUpdateDoc($doc_ID);
        break;

    case 'update_template':
        $req = read_json_body();
        $doc_ID = (int) ($req['doc_ID'] ?? $req['file_ID'] ?? 0);
        if ($doc_ID <= 0) {
            json_err('doc_ID 無效');
        }
        $doc_status = $req['doc_status'] ?? $req['file_status'] ?? null;
        $is_top = $req['is_top'] ?? null;

        if ($doc_status === null && $is_top === null) {
            json_err('缺少更新欄位');
        }

        try {
            $stmt = $conn->prepare("
                UPDATE docdata
                SET doc_status = COALESCE(?, doc_status),
                    is_top = COALESCE(?, is_top)
                WHERE doc_ID = ?
            ");
            $stmt->execute([
                $doc_status !== null ? (int) $doc_status : null,
                $is_top !== null ? (int) $is_top : null,
                $doc_ID,
            ]);
            json_ok();
        } catch (Throwable $e) {
            json_err('更新失敗：' . $e->getMessage());
        }
        break;

    case 'delete_file':
        $payload = read_json_body();
        $doc_ID = (int) ($payload['doc_ID'] ?? $payload['file_ID'] ?? 0);
        if ($doc_ID <= 0) {
            json_err('doc_ID 無效');
        }

        $ensureTargetTable();
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT doc_example FROM docdata WHERE doc_ID = ?");
            $stmt->execute([$doc_ID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // 先刪目標資料避免 FK 擋
            try {
                $stmt = $conn->prepare("DELETE FROM doctargetdata WHERE doc_ID = ?");
                $stmt->execute([$doc_ID]);
            } catch (Throwable $e) {
                // 若表不存在則忽略
            }

            $stmt = $conn->prepare("DELETE FROM docdata WHERE doc_ID = ?");
            $stmt->execute([$doc_ID]);
            $conn->commit();

            if ($row) {
                $deletePhysicalFile($row['doc_example'] ?? null);
            }
            json_ok();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('刪除失敗：' . $e->getMessage());
        }
        break;

    case 'batch_delete_files':
        $payload = read_json_body();
        $ids = $payload['doc_IDs'] ?? $payload['file_IDs'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn($id) => $id > 0);
        if (!$ids) {
            json_err('沒有指定文件');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $ensureTargetTable();
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT doc_example FROM docdata WHERE doc_ID IN ($placeholders)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            try {
                $stmt = $conn->prepare("DELETE FROM doctargetdata WHERE doc_ID IN ($placeholders)");
                $stmt->execute($ids);
            } catch (Throwable $e) {
                // ignore when table missing
            }

            $stmt = $conn->prepare("DELETE FROM docdata WHERE doc_ID IN ($placeholders)");
            $stmt->execute($ids);
            $conn->commit();

            foreach ($rows as $row) {
                $deletePhysicalFile($row['doc_example'] ?? null);
            }
            json_ok(['deleted' => $ids]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            json_err('刪除失敗：' . $e->getMessage());
        }
        break;

    default:
        json_err('Unknown action: ' . ($do ?: ''));
}

