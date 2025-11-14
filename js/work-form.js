let API_URL = 'work_form_data.php';

function resolveApiUrl() {
  const formEl = document.getElementById('work-main-form');
  if (!formEl) return API_URL;
  const base = (formEl.dataset.apiBase || '').trim();
  if (!base) return 'work_form_data.php';
  return base.endsWith('/')
    ? `${base}work_form_data.php`
    : `${base}/work_form_data.php`;
}

async function loadData() {
  try {
    const res = await fetch(`${API_URL}?action=get`, {
      credentials: 'same-origin'
    });
    if (!res.ok) throw new Error(`伺服器回應異常（${res.status}）`);
    const data = await res.json();

    if (data.success) {
      document.getElementById('work_id').value = data.work.work_ID || '';
      document.getElementById('work_title').value = data.work.work_title || '';
      document.getElementById('work_content').value = data.work.work_content || '';

      if (data.readOnly) {
        document.getElementById('work_title').setAttribute('readonly', true);
        document.getElementById('work_content').setAttribute('readonly', true);
        document.getElementById('action-buttons').classList.add('d-none');
        document.getElementById('doneBadge').classList.remove('d-none');
      }
    } else {
      Swal.fire('錯誤', data.msg || '資料載入失敗', 'error');
    }
  } catch (err) {
    console.error(err);
    Swal.fire('錯誤', err.message || '資料載入失敗', 'error');
  }
}

async function saveData(action) {
  try {
    const formEl = document.getElementById('work-main-form');
    if (!formEl) return;
    
    // 驗證表單
    if (!formEl.checkValidity()) {
      formEl.reportValidity();
      return;
    }
    
    const formData = new FormData(formEl);
    formData.append('action', action);

    const res = await fetch(API_URL, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    if (!res.ok) throw new Error(`伺服器回應異常（${res.status}）`);

    const data = await res.json();

    Swal.fire({
      icon: data.success ? 'success' : 'error',
      title: data.success ? '成功' : '失敗',
      text: data.msg,
    }).then(() => {
      if (data.reload) {
        loadData();
      }
    });
  } catch (err) {
    console.error(err);
    Swal.fire('錯誤', err.message || '資料送出失敗', 'error');
  }
}

function initWorkForm() {
  // 防止重複初始化
  if (window._workFormInitialized) {
    console.log('work-form already initialized, skipping...');
    return;
  }
  window._workFormInitialized = true;
  
  API_URL = resolveApiUrl();
  loadData();
  
  const formEl = document.getElementById('work-main-form');
  if (!formEl) return;
  
  // 防止表單提交（使用 AJAX 而非傳統提交）
  formEl.addEventListener('submit', (e) => {
    e.preventDefault();
    e.stopPropagation();
    return false;
  });
  
  // 綁定按鈕事件，明確阻止預設行為
  const saveBtn = document.getElementById('saveBtn');
  const submitBtn = document.getElementById('submitBtn');
  
  if (saveBtn) {
    saveBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      saveData('save');
      return false;
    });
  }
  
  if (submitBtn) {
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      saveData('submit');
      return false;
    });
  }
}

// 暴露到全域，讓 app.js 可以調用
window.initWorkForm = initWorkForm;

// 根據 DOM 狀態決定如何初始化
function tryInitWorkForm() {
  const formEl = document.getElementById('work-main-form');
  if (formEl) {
    initWorkForm();
    return true;
  }
  return false;
}

// 立即嘗試初始化（如果元素已存在）
if (!tryInitWorkForm()) {
  // 如果元素不存在，等待 DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      tryInitWorkForm();
    }, { once: true });
  } else {
    // DOM 已就緒但元素可能還沒載入，延遲再試
    setTimeout(() => {
      if (!tryInitWorkForm()) {
        // 如果還是沒有，監聽自定義事件（動態載入完成時觸發）
        $(document).on('pageLoaded', function(e, path) {
          if (path && path.includes('work_form')) {
            setTimeout(tryInitWorkForm, 200);
          }
        });
      }
    }, 100);
  }
}

// 監聽自定義事件（當頁面動態載入完成時）
$(document).on('pageLoaded scriptExecuted', function(e, path) {
  if (path && path.includes('work_form')) {
    setTimeout(() => {
      if (!tryInitWorkForm()) {
        // 如果第一次失敗，再試一次
        setTimeout(tryInitWorkForm, 300);
      }
    }, 200);
  }
});
