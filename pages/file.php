<?php
session_start();
require '../includes/pdo.php';
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<div id="adminFileApp" class="container my-4">
<h1 class="mb-4 d-flex justify-content-between align-items-center">
  範例檔案上傳
  <a href="#pages/apply_preview.php" 
     data-page="apply_preview" 
     class="btn btn-outline-secondary spa-link">
    查看審核列表
  </a>
</h1>



    <!-- 上傳表單 -->
    <div class="card mb-4">
      <div class="card-header"><strong>上傳新的 PDF 範例檔</strong></div>
      <div class="card-body">
        <label class="form-label">表單名稱：</label>
        <input type="text" class="form-control" v-model="form.f_name" placeholder="例如：專題指導申請表" />

        <label class="form-label mt-3">選擇 PDF 範例檔案：</label>
        <input type="file" accept=".pdf" @change="onFileChange" class="form-control" ref="fileInput">

        <button @click="submitForm" class="btn btn-secondary mt-3">送出上傳</button>
      </div>
    </div>

    <!-- 後台清單 -->
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">老師上傳範例檔案後台列表</h5>
        <small class="text-muted">共 {{ files.length }} 筆</small>
      </div>

      <div class="card-body">
        <div class="loading" v-if="loading">載入中…</div>
        <div class="no-data" v-else-if="!files.length">目前尚無資料</div>

        <template v-else>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm align-middle mb-0 bg-white text-center">
              <thead class="table-light">
                <tr>
                  <th style="min-width:160px">名稱</th>
                  <th style="width:90px">檔案</th>
                  <th style="min-width:210px">更新</th>
                  <th style="width:160px">置頂</th>
                  <th style="width:160px">狀態</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="file in files" :key="file.file_ID">
                  <td class="text-center">{{ file.file_name }}</td>
                  <td><a :href="file.file_url" target="_blank">查看</a></td>
                  <td>{{ file.file_update_d }}</td>

                  <!-- 置頂：按鈕 -->
                  <td>
                    <button
                      type="button"
                      class="btn btn-shadow w-75"
                      :class="Number(file.is_top) ? 'btn-primary' : 'btn-outline-secondary'"
                      @click="toggleTop(file)">
                      {{ Number(file.is_top) ? '已置頂' : '未置頂' }}
                    </button>
                  </td>

                  <!-- 狀態：按鈕 -->
                  <td>
                    <button
                      type="button"
                      class="btn btn-shadow w-75"
                      :class="Number(file.file_status) ? 'btn-outline-primary' : 'btn-danger'"
                      @click="toggleStatus(file)">
                      {{ Number(file.file_status) ? '啟用' : '停用' }}
                    </button>
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
  const { ref, onMounted } = Vue;

  // 這頁位於 /pages/ 下，AJAX 載入時仍保持此判斷即可
  const API_ROOT = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';

  Vue.createApp({
    setup() {
      const form = ref({ f_name: '', file: null });
      const fileInput = ref(null);

      const files = ref([]);
      const loading = ref(true);
      const error = ref('');
const sortFiles = () => {
  files.value.sort((a, b) =>
    // 置頂優先
    Number(b.is_top) - Number(a.is_top) ||
    // 其餘依 file_ID 由大到小（新在前）
    Number(b.file_ID) - Number(a.file_ID)
  );
};

      const fetchFiles = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch(`${API_ROOT}?do=get_files`, { cache: 'no-store' });
    const raw = await res.text();
    if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);

    let data;
    try { data = JSON.parse(raw); }
    catch { throw new Error('回應不是 JSON（可能 404 或 PHP 錯誤）'); }

    // ✅ 同時支援 [ ... ] 或 { rows:[ ... ] } 或 { data:[ ... ] }
    const list = Array.isArray(data) ? data
               : (data && Array.isArray(data.rows)) ? data.rows
               : (data && Array.isArray(data.data)) ? data.data
               : null;

    if (!list) throw new Error('資料格式錯誤（非陣列）');

    files.value = list;
     sortFiles();   
  } catch (e) {
    console.error('fetchFiles error:', e);
    error.value = '載入失敗（' + e.message + '）';
  } finally {
    loading.value = false;
  }
};


      const updateFile = async (file) => {
        const body = {
          file_ID: file.file_ID,
          file_status: Number(file.file_status),
          is_top: Number(file.is_top)
        };
        const res = await fetch(`${API_ROOT}?do=update_template`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json().catch(() => ({}));
        if (data.status && data.status !== 'success') {
          throw new Error(data.message || '後端錯誤');
        }
      };

      const onFileChange = (e) => {
        const f = e.target.files[0];
        if (f && f.type === 'application/pdf') {
          form.value.file = f;
        } else {
          form.value.file = null;
          if (fileInput.value) fileInput.value.value = '';
          Swal.fire({ icon: 'error', title: '請選擇 PDF 檔案' });
        }
      };

      const submitForm = async () => {
        if (!form.value.f_name || !form.value.file) {
          Swal.fire({ icon: 'error', title: '請填寫表單名稱並選擇 PDF 檔案' });
          return;
        }
        const fd = new FormData();
        fd.append('f_name', form.value.f_name);
        fd.append('file', form.value.file);

        try {
          const res = await fetch(`${API_ROOT}?do=upload_template`, { method: 'POST', body: fd });
          //1014update

          console.log('Http status:',res.status,'ok:',res.ok);
          const raw = await res.text();
          console.log('Raw response:',raw);
          // const data = await res.json();
          if(!res.ok) throw new Error(`http ${res.status}: ${res.statusText}`);
          const data = JSON.parse(raw);
          
          // if (data.status === 'success') {
          if (data.ok) {
            Swal.fire({ icon: 'success', title: '上傳成功', text: '文件ID: ' + data.file_ID });
            form.value.f_name = '';
            form.value.file = null;
            if (fileInput.value) fileInput.value.value = '';
            await fetchFiles();
          } else {
            throw new Error(data.message || '上傳失敗');
          }
        } catch (err) {
          Swal.fire({ icon: 'error', title: '上傳失敗', text: err.message || '請稍後再試' });
       console.error('Failed to refresh files:', err);
        }
      };
      //----------

      const toggleTop = async (file) => {
        const old = Number(file.is_top);
        file.is_top = old ? 0 : 1;
        try { await updateFile(file); sortFiles();  }
        catch (e) {
          file.is_top = old;
          Swal.fire({ icon: 'error', title: '更新失敗', text: e.message || '請稍後再試' });
        }
      };

      const toggleStatus = async (file) => {
        const old = Number(file.file_status);
        file.file_status = old ? 0 : 1;
        try { await updateFile(file); sortFiles(); }
        catch (e) {
          file.file_status = old;
          Swal.fire({ icon: 'error', title: '更新失敗', text: e.message || '請稍後再試' });
        }
      };

      onMounted(fetchFiles);

      return {
        form, fileInput, onFileChange, submitForm,
        files, loading, error, toggleTop, toggleStatus
      };
    }
  }).mount('#adminFileApp');
})();
</script>

</html>