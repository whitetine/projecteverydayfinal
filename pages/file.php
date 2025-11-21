<?php
session_start();
require '../includes/pdo.php';
//c9 
// 檢查權限
$role_ID = $_SESSION['role_ID'] ?? null;
if (!in_array($role_ID, [1, 2])) {
  echo '<div class="alert alert-danger">您沒有權限訪問此頁面</div>';
  exit;
}

// 獲取學級、班級列表
$cohorts = $conn->query("SELECT * FROM cohortdata WHERE cohort_status = 1 ORDER BY cohort_ID DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes = $conn->query("SELECT * FROM classdata ORDER BY c_ID")->fetchAll(PDO::FETCH_ASSOC);
$groups = $conn->query("SELECT * FROM groupdata WHERE group_status = 1 ORDER BY group_ID")->fetchAll(PDO::FETCH_ASSOC);

// 確保 filedata 表存在（僅限此頁面使用，不變更版面）
try {
  $conn->exec("
        CREATE TABLE IF NOT EXISTS filedata (
            file_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_url VARCHAR(255) NOT NULL,
            file_des TEXT DEFAULT NULL,
            is_required TINYINT(1) DEFAULT 0,
            file_start_d DATETIME DEFAULT NULL,
            file_end_d DATETIME DEFAULT NULL,
            file_status TINYINT(1) DEFAULT 1,
            is_top TINYINT(1) DEFAULT 0,
            file_update_d DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (file_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

  $countStmt = $conn->query("SELECT COUNT(*) FROM filedata");
  $hasFiledataRows = (int) $countStmt->fetchColumn() > 0;

  if (!$hasFiledataRows) {
    $legacyExistsStmt = $conn->query("SHOW TABLES LIKE 'file'");
    $legacyTableExists = (bool) $legacyExistsStmt->fetchColumn();

    if ($legacyTableExists) {
      $legacyRows = $conn->query("
                SELECT file_ID, file_name, file_url, file_status, is_top, file_updated_d
                FROM file
            ")->fetchAll(PDO::FETCH_ASSOC);

      if ($legacyRows) {
        $insertStmt = $conn->prepare("
                    INSERT INTO filedata (file_ID, file_name, file_url, file_status, is_top, file_update_d)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

        foreach ($legacyRows as $row) {
          $insertStmt->execute([
            $row['file_ID'] ?? null,
            $row['file_name'] ?? '',
            $row['file_url'] ?? '',
            $row['file_status'] ?? 1,
            $row['is_top'] ?? 0,
            $row['file_updated_d'] ?? date('Y-m-d H:i:s'),
          ]);
        }
      }
    }
  }
} catch (Throwable $e) {
  // 佈署環境可能無權建立資料表，忽略錯誤以免影響版面
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<link rel="stylesheet" href="css/file_manage.css?v=<?= time() ?>">

<div id="adminFileApp" class="container my-4">
  <div class="page-header mb-4">
    <h1 class="mb-0 d-flex align-items-center">
      <i class="fa-solid fa-file-upload me-3" style="color: #ffc107;"></i>
      範例檔案管理
    </h1>
    <div class="page-header-actions mt-3">
      <a href="#pages/apply_preview.php" data-page="apply_preview" class="btn btn-outline-primary spa-link">
        <i class="fa-solid fa-list-check me-2"></i>查看審核列表
      </a>
    </div>
  </div>

  <!-- 上傳表單 -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <i class="fa-solid fa-plus-circle me-2"></i><strong>上傳新的範例檔案</strong>
    </div>
    <div class="card-body">
      <form @submit.prevent="submitForm" id="uploadForm">
        <div class="row g-3">
          <!-- 基本資訊 -->
          <div class="col-md-6">
            <label class="form-label">表單名稱 <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="form.doc_name" placeholder="例如：專題指導申請表" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">文件說明</label>
            <input type="text" class="form-control" v-model="form.doc_des" placeholder="文件說明（選）">
          </div>

          <!-- 檔案上傳 -->
          <div class="col-md-6">
            <label class="form-label">選擇 PDF 範例檔案 <span class="text-danger">*</span></label>
            <input type="file" accept=".pdf" @change="onFileChange" class="form-control" ref="fileInput" required>
            <small class="text-muted">僅支援 PDF 格式</small>
          </div>

          <!-- 必繳文件 -->
          <div class="col-md-6">
            <label class="form-label">是否為必繳文件</label>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" v-model="form.is_required" id="isRequiredSwitch">
              <label class="form-check-label" for="isRequiredSwitch">
                <span v-if="form.is_required" class="text-danger fw-bold">必繳文件</span>
                <span v-else>非必繳文件</span>
              </label>
            </div>
          </div>

          <!-- 開放時間和截止時間 -->
          <div class="col-md-6">
            <label class="form-label">開放時間</label>
            <input type="datetime-local" class="form-control" v-model="form.doc_start_d">
            <small class="text-muted">留空表示立即開放</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">截止時間</label>
            <input type="datetime-local" class="form-control" v-model="form.doc_end_d">
            <small class="text-muted">留空表示無截止時間</small>
          </div>

          <!-- 目標範圍設定 -->
          <div class="col-12">
            <label class="form-label fw-bold">
              <i class="fa-solid fa-bullseye me-2"></i>目標範圍設定
            </label>
            <div class="alert alert-info mb-3">
              <small>
                <i class="fa-solid fa-info-circle me-2"></i>
                可選擇多個條件，學生需符合任一條件即可看到此文件
              </small>
            </div>

            <div class="row g-3">
              <!-- 全部 -->
              <div class="col-md-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.doc_target_all" id="targetAll"
                    @change="onTargetAllChange">
                  <label class="form-check-label fw-bold" for="targetAll">
                    開放給所有人
                  </label>
                </div>
              </div>

              <!-- 學級（屆別） -->
              <div class="col-md-4">
                <label class="form-label">學級（屆別）</label>
                <select class="form-select" v-model="form.doc_target_cohorts" multiple size="5"
                  :disabled="form.doc_target_all">
                  <option v-for="cohort in cohorts" :key="cohort.cohort_ID" :value="cohort.cohort_ID">
                    {{ cohort.cohort_name }}
                  </option>
                </select>
                <small class="text-muted">可多選，按住 Ctrl/Cmd 鍵</small>
              </div>

              <!-- 年級 -->
              <div class="col-md-4">
                <label class="form-label">年級</label>
                <select class="form-select" v-model="form.doc_target_grades" multiple size="5"
                  :disabled="form.doc_target_all">
                  <option value="1">一年級</option>
                  <option value="2">二年級</option>
                  <option value="3">三年級</option>
                  <option value="4">四年級</option>
                  <option value="5">五年級</option>
                </select>
                <small class="text-muted">可多選，按住 Ctrl/Cmd 鍵</small>
              </div>

              <!-- 班級 -->
              <div class="col-md-4">
                <label class="form-label">班級</label>
                <select class="form-select" v-model="form.doc_target_classes" multiple size="5"
                  :disabled="form.doc_target_all">
                  <option v-for="classItem in classes" :key="classItem.c_ID" :value="classItem.c_ID">
                    {{ classItem.c_name }}班
                  </option>
                </select>
                <small class="text-muted">可多選，按住 Ctrl/Cmd 鍵</small>
              </div>
            </div>
          </div>

          <!-- 提交按鈕 -->
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg" :disabled="uploading">
              <i class="fa-solid fa-upload me-2"></i>
              <span v-if="uploading">上傳中...</span>
              <span v-else>送出上傳</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-lg ms-2" @click="resetForm">
              <i class="fa-solid fa-rotate-left me-2"></i>重置表單
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- 搜尋和篩選區 -->
  <div class="card mb-4 shadow-sm filter-card">
    <div class="card-header filter-header">
      <i class="fa-solid fa-filter me-2"></i>搜尋與篩選
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">
            <i class="fa-solid fa-magnifying-glass me-2"></i>搜尋文件名稱
          </label>
          <input type="text" class="form-control" v-model="searchText" placeholder="輸入文件名稱..." @input="filterFiles">
        </div>
        <div class="col-md-3">
          <label class="form-label">
            <i class="fa-solid fa-toggle-on me-2"></i>狀態
          </label>
          <select class="form-select" v-model="statusFilter" @change="filterFiles">
            <option value="">全部</option>
            <option value="1">啟用</option>
            <option value="0">停用</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">
            <i class="fa-solid fa-star me-2"></i>必繳文件
          </label>
          <select class="form-select" v-model="requiredFilter" @change="filterFiles">
            <option value="">全部</option>
            <option value="1">必繳</option>
            <option value="0">非必繳</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="button" class="btn btn-outline-secondary w-100" @click="clearFilters">
            <i class="fa-solid fa-xmark me-2"></i>清除
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- 文件列表 -->
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between bg-light">
      <h5 class="mb-0">
        <i class="fa-solid fa-list me-2"></i>文件列表
      </h5>
      <div class="d-flex align-items-center gap-2">
        <small class="text-muted">共 {{ filteredFiles.length }} 筆</small>
        <button type="button" class="btn btn-sm btn-outline-danger" @click="batchDelete"
          :disabled="selectedFiles.length === 0">
          <i class="fa-solid fa-trash me-2"></i>批量刪除 ({{ selectedFiles.length }})
        </button>
      </div>
    </div>

    <div class="card-body">
      <div class="loading" v-if="loading">
        <i class="fa-solid fa-spinner fa-spin me-2"></i>載入中…
      </div>
      <div class="no-data" v-else-if="!filteredFiles.length">
        <i class="fa-solid fa-inbox me-2"></i>目前尚無資料
      </div>

      <template v-else>
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-sm align-middle mb-0 bg-white text-center">
            <thead class="table-light">
              <tr>
                <th style="width: 40px;">
                  <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected">
                </th>
                <th style="min-width: 200px;">文件名稱</th>
                <th style="min-width: 150px;">目標範圍</th>
                <th style="width: 100px;">必繳</th>
                <th style="width: 120px;">檔案</th>
                <th style="min-width: 180px;">時間設定</th>
                <th style="width: 100px;">置頂</th>
                <th style="width: 100px;">狀態</th>
                <th style="min-width: 200px;">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="file in filteredFiles" :key="file.doc_ID" :class="{ 'table-warning': file.is_required }">
                <td>
                  <input type="checkbox" :value="file.doc_ID" v-model="selectedFiles">
                </td>
                <td class="text-start">
                  <div class="fw-bold">{{ file.doc_name }}</div>
                  <small class="text-muted" v-if="file.doc_des">{{ file.doc_des }}</small>
                </td>
                <td class="text-start">
                  <div v-if="file.doc_target_all" class="badge bg-primary">全部</div>
                  <div v-else>
                    <div v-if="file.doc_target_cohorts && file.doc_target_cohorts.length" class="mb-1">
                      <span class="badge bg-info me-1">學級</span>
                      <small>{{ formatTargetNames(file.doc_target_cohorts, 'cohort') }}</small>
                    </div>
                    <div v-if="file.doc_target_grades && file.doc_target_grades.length" class="mb-1">
                      <span class="badge bg-success me-1">年級</span>
                      <small>{{ formatTargetNames(file.doc_target_grades, 'grade') }}</small>
                    </div>
                    <div v-if="file.doc_target_classes && file.doc_target_classes.length">
                      <span class="badge bg-warning me-1">班級</span>
                      <small>{{ formatTargetNames(file.doc_target_classes, 'class') }}</small>
                    </div>
                    <div v-if="!file.doc_target_cohorts && !file.doc_target_grades && !file.doc_target_classes"
                      class="text-muted">
                      未設定
                    </div>
                  </div>
                </td>
                <td>
                  <span v-if="file.is_required" class="badge bg-danger">必繳</span>
                  <span v-else class="badge bg-secondary">非必繳</span>
                </td>
                <td>
                  <a :href="file.doc_example" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-eye me-1"></i>查看
                  </a>
                </td>
                <td class="text-start">
                  <div v-if="file.doc_start_d">
                    <small class="text-muted">開放：</small><br>
                    <small>{{ formatDateTime(file.doc_start_d) }}</small>
                  </div>
                  <div v-if="file.doc_end_d" class="mt-1">
                    <small class="text-muted">截止：</small><br>
                    <small>{{ formatDateTime(file.doc_end_d) }}</small>
                  </div>
                  <div v-if="!file.doc_start_d && !file.doc_end_d" class="text-muted">
                    <small>無時間限制</small>
                  </div>
                </td>
                <td>
                  <button type="button" class="btn btn-sm w-100"
                    :class="Number(file.is_top) ? 'btn-warning' : 'btn-outline-secondary'" @click="toggleTop(file)">
                    <i class="fa-solid" :class="Number(file.is_top) ? 'fa-star' : 'fa-star'"></i>
                    {{ Number(file.is_top) ? '已置頂' : '未置頂' }}
                  </button>
                </td>
                <td>
                  <button type="button" class="btn btn-sm w-100"
                    :class="Number(file.doc_status) ? 'btn-success' : 'btn-danger'" @click="toggleStatus(file)">
                    {{ Number(file.doc_status) ? '啟用' : '停用' }}
                  </button>
                </td>
                <td>
                  <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="editDoc(file)" title="編輯">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteDoc(file)" title="刪除">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div class="text-danger mt-2" v-if="error">{{ error }}</div>
    </div>
  </div>
</div>

<script>
  (() => {
    const { ref, computed, onMounted } = Vue;

    const API_ROOT = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';

    Vue.createApp({
      setup() {
        // 表單資料
        const form = ref({
          doc_name: '',
          doc_des: '',
          file: null,
          is_required: false,
          doc_start_d: '',
          doc_end_d: '',
          doc_target_all: false,
          doc_target_cohorts: [],
          doc_target_grades: [],
          doc_target_classes: []
        });

        const fileInput = ref(null);
        const uploading = ref(false);
        const files = ref([]);
        const filteredFiles = ref([]);
        const loading = ref(true);
        const error = ref('');
        const selectedFiles = ref([]);
        const editingDoc = ref(null);

        // 搜尋和篩選
        const searchText = ref('');
        const statusFilter = ref('');
        const requiredFilter = ref('');

        // 從 PHP 傳入的資料
        const cohorts = <?= json_encode($cohorts, JSON_UNESCAPED_UNICODE) ?>;
        const classes = <?= json_encode($classes, JSON_UNESCAPED_UNICODE) ?>;
        const groups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?>;

        // 計算屬性
        const isAllSelected = computed(() => {
          return filteredFiles.value.length > 0 &&
            selectedFiles.value.length === filteredFiles.value.length;
        });

        // 方法
        const sortFiles = () => {
          files.value.sort((a, b) =>
            Number(b.is_top) - Number(a.is_top) ||
            Number(b.doc_ID) - Number(a.doc_ID)
          );
        };

        const fetchFiles = async () => {
          loading.value = true;
          error.value = '';
          try {
            const res = await fetch(`${API_ROOT}?do=get_files_with_targets`, { cache: 'no-store' });
            const raw = await res.text();
            if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);

            let data;
            try { data = JSON.parse(raw); }
            catch { throw new Error('回應不是 JSON'); }

            const list = Array.isArray(data) ? data
              : (data && Array.isArray(data.rows)) ? data.rows
                : (data && Array.isArray(data.data)) ? data.data
                  : null;

            if (!list) throw new Error('資料格式錯誤');

            files.value = list;
            sortFiles();
            filterFiles();
          } catch (e) {
            console.error('fetchFiles error:', e);
            error.value = '載入失敗（' + e.message + '）';
          } finally {
            loading.value = false;
          }
        };

        const filterFiles = () => {
          let filtered = [...files.value];

          // 搜尋
          if (searchText.value) {
            const search = searchText.value.toLowerCase();
            filtered = filtered.filter(f =>
              f.doc_name.toLowerCase().includes(search) ||
              (f.doc_des && f.doc_des.toLowerCase().includes(search))
            );
          }

          // 狀態篩選
          if (statusFilter.value !== '') {
            filtered = filtered.filter(f =>
              String(f.doc_status) === statusFilter.value
            );
          }

          // 必繳篩選
          if (requiredFilter.value !== '') {
            filtered = filtered.filter(f =>
              String(f.is_required || 0) === requiredFilter.value
            );
          }

          filteredFiles.value = filtered;
        };

        const clearFilters = () => {
          searchText.value = '';
          statusFilter.value = '';
          requiredFilter.value = '';
          filterFiles();
        };

        const onTargetAllChange = () => {
          if (form.value.doc_target_all) {
            form.value.doc_target_cohorts = [];
            form.value.doc_target_grades = [];
            form.value.doc_target_classes = [];
          }
        };

        const onFileChange = (e) => {
          const f = e.target.files[0];
          if (f && f.type === 'application/pdf') {
            form.value.file = f;
          } else {
            form.value.file = null;
            if (fileInput.value) fileInput.value.value = '';
            Swal.fire({
              icon: 'error',
              title: '請選擇 PDF 檔案',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
          }
        };

        const resetForm = () => {
          form.value = {
            doc_name: '',
            doc_des: '',
            file: null,
            is_required: false,
            doc_start_d: '',
            doc_end_d: '',
            doc_target_all: false,
            doc_target_cohorts: [],
            doc_target_grades: [],
            doc_target_classes: []
          };
          if (fileInput.value) fileInput.value.value = '';
          editingDoc.value = null;
        };

        const submitForm = async () => {
          if (!form.value.doc_name) {
            Swal.fire({
              icon: 'error',
              title: '請填寫表單名稱',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
            return;
          }

          if (!form.value.file && !editingDoc.value) {
            Swal.fire({
              icon: 'error',
              title: '請選擇要上傳的檔案',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
            return;
          }

          uploading.value = true;
          const fd = new FormData();

          if (editingDoc.value) {
            fd.append('doc_ID', editingDoc.value.doc_ID);
            fd.append('file_ID', editingDoc.value.doc_ID); // 兼容舊後端
          }

          fd.append('doc_name', form.value.doc_name);
          fd.append('file_name', form.value.doc_name);
          fd.append('doc_des', form.value.doc_des || '');
          fd.append('file_des', form.value.doc_des || '');
          if (form.value.file) {
            fd.append('file', form.value.file);
          }
          fd.append('is_required', form.value.is_required ? '1' : '0');
          fd.append('doc_start_d', form.value.doc_start_d || '');
          fd.append('doc_end_d', form.value.doc_end_d || '');
          fd.append('file_start_d', form.value.doc_start_d || '');
          fd.append('file_end_d', form.value.doc_end_d || '');
          fd.append('doc_target_all', form.value.doc_target_all ? '1' : '0');
          fd.append('target_all', form.value.doc_target_all ? '1' : '0');
          fd.append('doc_target_cohorts', JSON.stringify(form.value.doc_target_cohorts));
          fd.append('doc_target_grades', JSON.stringify(form.value.doc_target_grades));
          fd.append('doc_target_classes', JSON.stringify(form.value.doc_target_classes));
          fd.append('target_cohorts', JSON.stringify(form.value.doc_target_cohorts));
          fd.append('target_grades', JSON.stringify(form.value.doc_target_grades));
          fd.append('target_classes', JSON.stringify(form.value.doc_target_classes));

          try {
            const endpoint = editingDoc.value
              ? `${API_ROOT}?do=update_file_with_targets`
              : `${API_ROOT}?do=upload_file_with_targets`;

            const res = await fetch(endpoint, { method: 'POST', body: fd });
            const raw = await res.text();
            if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);

            const data = JSON.parse(raw);
            if (data.ok || data.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: editingDoc.value ? '更新成功' : '上傳成功',
                reverseButtons: true,
                confirmButtonText: '確定',
                confirmButtonColor: '#3085d6'
              });
              resetForm();
              await fetchFiles();
            } else {
              throw new Error(data.message || '操作失敗');
            }
          } catch (err) {
            Swal.fire({
              icon: 'error',
              title: '操作失敗',
              text: err.message || '請稍後再試',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
          } finally {
            uploading.value = false;
          }
        };

        const editDoc = (doc) => {
          editingDoc.value = doc;
          form.value = {
            doc_name: doc.doc_name,
            doc_des: doc.doc_des || '',
            file: null,
            is_required: Number(doc.is_required) === 1,
            doc_start_d: doc.doc_start_d ? doc.doc_start_d.replace(' ', 'T').substring(0, 16) : '',
            doc_end_d: doc.doc_end_d ? doc.doc_end_d.replace(' ', 'T').substring(0, 16) : '',
            doc_target_all: !!doc.doc_target_all,
            doc_target_cohorts: Array.isArray(doc.doc_target_cohorts) ? [...doc.doc_target_cohorts] : [],
            doc_target_grades: Array.isArray(doc.doc_target_grades) ? [...doc.doc_target_grades] : [],
            doc_target_classes: Array.isArray(doc.doc_target_classes) ? [...doc.doc_target_classes] : []
          };

          document.querySelector('#uploadForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        const deleteDoc = async (doc) => {
          const result = await Swal.fire({
            title: '確認刪除',
            text: `確定要刪除「${doc.doc_name}」嗎？此操作無法復原。`,
            icon: 'warning',
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: '確定刪除',
            cancelButtonText: '取消',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
          });

          if (result.isConfirmed) {
            try {
              const res = await fetch(`${API_ROOT}?do=delete_file`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ doc_ID: doc.doc_ID })
              });
              const data = await res.json();
              if (data.ok || data.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: '刪除成功',
                  reverseButtons: true,
                  confirmButtonText: '確定',
                  confirmButtonColor: '#3085d6'
                });
                await fetchFiles();
              } else {
                throw new Error(data.message || '刪除失敗');
              }
            } catch (err) {
              Swal.fire({
                icon: 'error',
                title: '刪除失敗',
                text: err.message || '請稍後再試',
                reverseButtons: true,
                confirmButtonText: '確定',
                confirmButtonColor: '#3085d6'
              });
            }
          }
        };

        const batchDelete = async () => {
          if (selectedFiles.value.length === 0) return;

          const result = await Swal.fire({
            title: '確認批量刪除',
            text: `確定要刪除選中的 ${selectedFiles.value.length} 個文件嗎？此操作無法復原。`,
            icon: 'warning',
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: '確定刪除',
            cancelButtonText: '取消',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
          });

          if (result.isConfirmed) {
            try {
              const res = await fetch(`${API_ROOT}?do=batch_delete_files`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ doc_IDs: selectedFiles.value })
              });
              const data = await res.json();
              if (data.ok || data.status === 'success') {
                Swal.fire({
                  icon: 'success',
                  title: '批量刪除成功',
                  reverseButtons: true,
                  confirmButtonText: '確定',
                  confirmButtonColor: '#3085d6'
                });
                selectedFiles.value = [];
                await fetchFiles();
              } else {
                throw new Error(data.message || '刪除失敗');
              }
            } catch (err) {
              Swal.fire({
                icon: 'error',
                title: '批量刪除失敗',
                text: err.message || '請稍後再試',
                reverseButtons: true,
                confirmButtonText: '確定',
                confirmButtonColor: '#3085d6'
              });
            }
          }
        };

        const toggleSelectAll = (e) => {
          if (e.target.checked) {
            selectedFiles.value = filteredFiles.value.map(f => f.doc_ID);
          } else {
            selectedFiles.value = [];
          }
        };

        const toggleTop = async (doc) => {
          const old = Number(doc.is_top);
          doc.is_top = old ? 0 : 1;
          try {
            await updateDocState(doc);
            sortFiles();
            filterFiles();
          }
          catch (e) {
            doc.is_top = old;
            Swal.fire({
              icon: 'error',
              title: '更新失敗',
              text: e.message || '請稍後再試',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
          }
        };

        const toggleStatus = async (doc) => {
          const old = Number(doc.doc_status);
          doc.doc_status = old ? 0 : 1;
          try {
            await updateDocState(doc);
            sortFiles();
            filterFiles();
          }
          catch (e) {
            doc.doc_status = old;
            Swal.fire({
              icon: 'error',
              title: '更新失敗',
              text: e.message || '請稍後再試',
              reverseButtons: true,
              confirmButtonText: '確定',
              confirmButtonColor: '#3085d6'
            });
          }
        };

        const updateDocState = async (doc) => {
          const body = {
            doc_ID: doc.doc_ID,
            doc_status: Number(doc.doc_status),
            is_top: Number(doc.is_top)
          };
          const res = await fetch(`${API_ROOT}?do=update_template`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
          });
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const data = await res.json().catch(() => ({}));
          if (data.status && data.status !== 'success' && !data.ok) {
            throw new Error(data.message || '後端錯誤');
          }
        };

        const formatDateTime = (dt) => {
          if (!dt) return '';
          const d = new Date(dt);
          return d.toLocaleString('zh-TW', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
          });
        };

        const formatTargetNames = (ids, type) => {
          if (!ids || !ids.length) return '';
          if (type === 'cohort') {
            return ids.map(id => {
              const cohort = cohorts.find(c => c.cohort_ID == id);
              return cohort ? cohort.cohort_name : id;
            }).join(', ');
          } else if (type === 'grade') {
            return ids.map(id => `${id}年級`).join(', ');
          } else if (type === 'class') {
            return ids.map(id => {
              const classItem = classes.find(c => c.c_ID == id);
              return classItem ? `${classItem.c_name}班` : id;
            }).join(', ');
          }
          return ids.join(', ');
        };

        onMounted(fetchFiles);

        return {
          form, fileInput, onFileChange, submitForm, resetForm,
          files, filteredFiles, loading, error, toggleTop, toggleStatus,
          searchText, statusFilter, requiredFilter, filterFiles, clearFilters,
          selectedFiles, toggleSelectAll, isAllSelected, batchDelete,
          editDoc, deleteDoc, editingDoc, onTargetAllChange,
          cohorts, classes, groups, formatDateTime, formatTargetNames, uploading
        };
      }
    }).mount('#adminFileApp');
  })();
</script>

</html>