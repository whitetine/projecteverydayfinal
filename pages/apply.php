<?php
session_start();
require '../includes/pdo.php'; // 取得 $conn (PDO)

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
        // 若查詢失敗則退回 session 內的名稱
    }
}

// 如果資料庫查不到，嘗試從 session 取得
if ($currentUser['u_name'] === '' && isset($_SESSION['u_name'])) {
    $currentUser['u_name'] = (string)$_SESSION['u_name'];
}
?>
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
                <form @submit.prevent="submitForm" enctype="multipart/form-data" id="applyForm">

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
                            <input type="text" class="form-control" id="apply_user" v-model="applyUser" :value="applyUser || '<?= htmlspecialchars($currentUser['u_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>'" value="<?= htmlspecialchars($currentUser['u_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>

                            <!-- 🔹隱藏欄位：確保表單送出時有帶值 -->
                            <input type="hidden" name="apply_user" :value="applyUser || '<?= htmlspecialchars($currentUser['u_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>'">
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
                                    <input type="range" class="form-range flex-grow-1" min="10" max="100" step="5"
                                        v-model.number="previewPercent" aria-label="調整預覽大小">
                                </div>
                                <div class="preview-box text-center"
                                    :style="{ width: previewPercent + '%', maxWidth: '100%', margin: '0 auto' }">
                                    <img :src="imagePreview" class="preview-img img-fluid rounded shadow" alt="圖片預覽"
                                        style="max-height: 400px; object-fit: contain;">
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
    // 確保申請人姓名在 DOM 載入後立即設置（在 Vue 掛載前）
    (function() {
        function setUserName() {
            if (window.CURRENT_USER && window.CURRENT_USER.u_name) {
                const inputEl = document.getElementById('apply_user');
                if (inputEl) {
                    inputEl.value = window.CURRENT_USER.u_name;
                }
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setUserName);
        } else {
            setTimeout(setUserName, 0);
        }
    })();
</script>
<script src="../js/apply-uploader.js?v=<?= time() ?>"></script>
<script>
    (function () {
        const mountIfNeeded = () => {
            if (window.renderApplyPage || typeof window.mountApplyUploader !== 'function') {
                return;
            }
            window.mountApplyUploader('#app');
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mountIfNeeded, { once: true });
        } else {
            mountIfNeeded();
        }
    })();
</script>