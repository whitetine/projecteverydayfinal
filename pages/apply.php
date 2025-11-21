<?php
session_start();
require '../includes/pdo.php'; // 取得 $conn (PDO)

$submitError = '';
$isAjaxRequest = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = isset($_POST['file_ID']) ? trim((string)$_POST['file_ID']) : '';
    $comment = trim((string)($_POST['apply_other'] ?? ''));
    $userId = (string)($_SESSION['u_ID'] ?? '');

    if ($userId === '') {
        $submitError = '登入逾時，請重新登入。';
    } elseif ($docId === '') {
        $submitError = '請選擇表單類型。';
    } elseif (!isset($_FILES['apply_image']) || $_FILES['apply_image']['error'] !== UPLOAD_ERR_OK) {
        $submitError = '請選擇要上傳的圖片。';
    } else {
        $file = $_FILES['apply_image'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedExt, true)) {
            $submitError = '僅接受 JPG 或 PNG 圖片。';
        } else {
            $uploadDir = dirname(__DIR__) . '/uploads/docsub/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $submitError = '無法建立上傳資料夾。';
            } else {
                $newName = 'apply_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $absolute = $uploadDir . $newName;
                $relative = 'uploads/docsub/' . $newName;

                if (!move_uploaded_file($file['tmp_name'], $absolute)) {
                    $submitError = '檔案儲存失敗。';
                } else {
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO docsubdata (
                                doc_ID,
                                dcsub_team_ID,
                                dcsub_u_ID,
                                dcsub_comment,
                                dcsub_url,
                                dcsub_sub_d,
                                dc_approved_u_ID,
                                dcsub_approved_d,
                                dcsub_remark,
                                dcsub_status
                            ) VALUES (?, NULL, ?, ?, ?, NOW(), NULL, NULL, NULL, 0)
                        ");
                        $stmt->execute([$docId, $userId, $comment, $relative]);

                        if ($isAjaxRequest) {
                            echo json_encode(['ok' => true, 'message' => '申請已送出！'], JSON_UNESCAPED_UNICODE);
                            exit;
                        }

                        header('Location: apply_preview.php');
                        exit;
                    } catch (Throwable $e) {
                        $submitError = '寫入資料庫失敗：' . $e->getMessage();
                        @unlink($absolute);
                    }
                }
            }
        }
    }

    if ($isAjaxRequest) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $submitError ?: '送出失敗'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 🔹 查詢申請人姓名（從資料庫 userdata 表）
$currentUser = [
    'u_ID' => (string)($_SESSION['u_ID'] ?? ''),
    'u_name' => '',
];

if ($currentUser['u_ID'] !== '') {
    try {
        $stmt = $conn->prepare("SELECT u_ID, u_name FROM userdata WHERE u_ID = ?");
        $stmt->execute([$currentUser['u_ID']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $currentUser['u_name'] = (string)($row['u_name'] ?? '');
        }
    } catch (Throwable $e) {
        // 若查詢失敗則退回 session 內的名稱（若有）
    }
}

// 如果資料庫查不到，嘗試從 session 取得
if ($currentUser['u_name'] === '' && isset($_SESSION['u_name'])) {
    $currentUser['u_name'] = (string)$_SESSION['u_name'];
}
?>
<style>
    .apply-preview-stage {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 300px;
        padding: 1.5rem;
        overflow: visible;
    }

    .apply-preview-img {
        width: 100%;
        max-width: 520px;
        height: auto;
        transform-origin: center center;
        transition: transform 0.2s ease, filter 0.2s ease;
        filter: drop-shadow(0 6px 24px rgba(19, 23, 34, 0.15));
    }
</style>
<header>
    <h2 class="mb-4">申請文件上傳</h2>
</header>

<div id="app" class="main container">
    <div id="apply-uploader">

        <!-- 上傳區卡片 -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <strong>上傳區</strong>
            </div>
            <div class="card-body">
                <form method="post"
                    action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES) ?>"
                    enctype="multipart/form-data"
                    id="applyForm"
                    @submit.prevent="submitForm">

                    <!-- 選擇表單類型與申請人姓名 -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="file_ID">選擇表單類型：</label>
                            <select v-model="selectedFileID" name="file_ID" id="file_ID" class="form-select" required>
                                <option disabled value="">請選擇表單</option>
                                <option v-for="file in files" :key="file.doc_ID" :value="file.doc_ID">
                                    {{ file.doc_name }}{{ file.is_required == 1 ? '（必填）' : '' }}
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="apply_user">申請人姓名：</label>
                            <input type="text" class="form-control" id="apply_user" v-model="applyUser" readonly>

                            <!-- 🔹隱藏欄位：確保表單送出時有帶值 -->
                            <input type="hidden" name="apply_user" :value="applyUser">
                        </div>

                        <!-- 檔案名稱/其他備註 -->
                        <div class="mb-4">
                            <label for="apply_other" class="form-label">檔案名稱/其他備註：</label>
                            <textarea v-model="applyOther" class="form-control" id="apply_other" name="apply_other"
                                rows="3" placeholder="請輸入檔案名稱或附加說明..."></textarea>
                        </div>

                        <!-- 上傳圖片 -->
                        <div class="mb-4">
                            <label for="apply_image" class="form-label">上傳圖片（PNG/JPG）：</label>
                            <input type="file" ref="applyImage" class="form-control" name="apply_image" id="apply_image"
                                accept="image/png, image/jpeg" @change="previewImage" />
                        </div>

                        <!-- 圖片預覽區塊 -->
                        <div v-if="imagePreview" class="card mb-4 shadow-sm">
                            <div class="card-header bg-light">
                                <strong>圖片預覽</strong>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <label class="form-label mb-0">預覽大小：</label>
                                    <span class="text-muted"><strong>{{ previewPercent }}%</strong></span>
                                    <input type="range" class="form-range flex-grow-1" min="50" max="200" step="5"
                                        v-model.number="previewPercent" aria-label="調整預覽大小">
                                </div>
                                <div class="apply-preview-stage">
                                    <img :src="imagePreview" class="apply-preview-img" alt="圖片預覽"
                                        :style="{
                                            transform: 'scale(' + (previewPercent / 100).toFixed(2) + ')'
                                        }">
                                </div>
                            </div>
                        </div>

                        <!-- 提交按鈕 -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-4">送出申請</button>
                        </div>
                </form>
            </div>
        </div>

        <!-- 範例檔案預覽區塊 -->
        <div class="card shadow-sm" v-if="selectedFileUrl">
            <div class="card-header bg-secondary text-white">
                <strong>範例檔案預覽</strong>
            </div>
            <div class="card-body p-0">
                <iframe :src="selectedFileUrl" class="w-100"
                    style="height: 400px; border: none; border-radius: 0 0 0.375rem 0.375rem;" title="範例檔案"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    window.CURRENT_USER = <?= json_encode($currentUser, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../js/apply-uploader.js?v=<?= time() ?>"></script>