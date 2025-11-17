// 動態解析 API URL（支援動態載入）
function resolveCheckReviewPeriodsApiUrl() {
  const path = window.location.pathname || '';
  if (path.includes('/pages/')) {
    return 'checkreviewperiods_data.php';
  }
  return 'pages/checkreviewperiods_data.php';
}

function __initCheckReviewPeriods() {
  // 動態設定表單 action
  const form = document.getElementById('periodForm');
  if (form) {
    form.action = resolveCheckReviewPeriodsApiUrl();
    // 攔截表單提交，改用 AJAX
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(form);
      const action = formData.get('action');
      
      try {
        const res = await fetch(resolveCheckReviewPeriodsApiUrl(), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });
        
        if (res.ok) {
          const result = await res.json();
          if (result.success) {
            // 成功後重新載入表格並清空表單
            await loadPeriodTable();
            resetForm();
            // 顯示成功訊息
            if (window.Swal) {
              Swal.fire({
                title: '成功',
                text: result.msg || (action === 'create' ? '已新增評分時段' : '已更新評分時段'),
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
            }
          } else {
            throw new Error(result.msg || '操作失敗');
          }
        } else {
          throw new Error('HTTP ' + res.status);
        }
      } catch (err) {
        console.error('提交失敗:', err);
        if (window.Swal) {
          Swal.fire('錯誤', '操作失敗：' + err.message, 'error');
        } else {
          alert('操作失敗：' + err.message);
        }
      }
    });
  }
  
  try { loadCohortList(); } catch (e) { console.error(e); }
  try { loadPeriodTable(); } catch (e) { console.error(e); }
  try { setupPeStatusButton(); } catch (e) { console.error(e); }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', __initCheckReviewPeriods);
} else {
  __initCheckReviewPeriods();
}

/* 載入屆別 */
function loadCohortList() {
  const apiUrl = resolveCheckReviewPeriodsApiUrl();
  fetch(`${apiUrl}?cohort_list=1`)
      .then(r => r.json())
      .then(list => {
          let sel = document.getElementById("cohort_ID");
          sel.innerHTML = '<option value="">請選擇屆別</option>';
          list.forEach(c => {
              sel.innerHTML += `<option value="${c.cohort_ID}">
                  ${c.cohort_name} (${c.year_label})
              </option>`;
          });
          // 預設清空團隊
          loadTeamList('');
          // 切換屆別時，重新載入團隊清單
          sel.addEventListener('change', function () {
            loadTeamList(this.value);
          });
      })
      .catch(err => {
          console.error('載入屆別失敗:', err);
          const sel = document.getElementById("cohort_ID");
          if (sel) sel.innerHTML = '<option value="">載入失敗</option>';
      });
}

/* 載入團隊：依屆別 */
function loadTeamList(cohortId, preselectTeamId) {
  const sel = document.getElementById('team_ID');
  if (!cohortId) {
    sel.innerHTML = '<option value="">請先選擇屆別</option>';
    return;
  }
  const apiUrl = resolveCheckReviewPeriodsApiUrl();
  fetch(`${apiUrl}?team_list=1&cohort_id=${encodeURIComponent(cohortId)}`)
    .then(r => r.json())
    .then(list => {
      sel.innerHTML = '<option value="">請選擇團隊</option>';
      list.forEach(t => {
        sel.innerHTML += `<option value="${t.team_ID}">${t.team_project_name}</option>`;
      });
      if (preselectTeamId) sel.value = String(preselectTeamId);
    })
    .catch(err => {
        console.error('載入團隊失敗:', err);
        if (sel) sel.innerHTML = '<option value="">載入失敗</option>';
    });
}

/* 啟用切換按鈕：同步 checkbox 與 btn 樣式 */
function setupPeStatusButton() {
  const cb  = document.getElementById('pe_status');
  const btn = document.getElementById('pe_status_btn');
  if (!cb || !btn) return;

  const sync = () => {
    if (cb.checked) {
      btn.className = 'btn btn-success';
      btn.textContent = '啟用';
    } else {
      btn.className = 'btn btn-danger';
      btn.textContent = '停用';
    }
  };

  btn.addEventListener('click', () => {
    cb.checked = !cb.checked;
    sync();
  });
  cb.addEventListener('change', sync);
  sync();
}

/* 載入資料表 */
function loadPeriodTable() {
  const apiUrl = resolveCheckReviewPeriodsApiUrl();
  const sort = new URLSearchParams(window.location.search).get("sort") || "created";
  fetch(`${apiUrl}?sort=${sort}`)
      .then(r => r.text())
      .then(html => {
          document.getElementById("periodTable").innerHTML = html;
      })
      .catch(err => {
          console.error('載入資料表失敗:', err);
          const tableEl = document.getElementById("periodTable");
          if (tableEl) tableEl.innerHTML = '<div class="alert alert-danger">資料載入失敗</div>';
      });
}

/* 編輯 */
function editRow(row) {
  document.getElementById('form_action').value = 'update';
  document.getElementById('submitBtn').innerText = '更新';

  document.getElementById('period_ID').value = row.period_ID || '';
  document.getElementById('period_start_d').value = row.period_start_d || '';
  document.getElementById('period_end_d').value = row.period_end_d || '';
  document.getElementById('period_title').value = row.period_title || '';
  document.getElementById('cohort_ID').value = row.cohort_ID || '';
  // 載入對應屆別的團隊，預選現有值
  loadTeamList(row.cohort_ID || '', row.pe_target_ID || '');
  document.getElementById('pe_status').checked = (row.pe_status == 1);
  // 同步 UI 按鈕
  document.getElementById('pe_status').dispatchEvent(new Event('change'));

  window.scrollTo({ top: 0, behavior: "smooth" });
}

/* 清空表單 */
function resetForm() {
  document.getElementById('form_action').value = 'create';
  document.getElementById('submitBtn').innerText = '新增';

  document.getElementById('period_ID').value = '';
  document.getElementById('period_start_d').value = '';
  document.getElementById('period_end_d').value = '';
  document.getElementById('period_title').value = '';
  document.getElementById('cohort_ID').value = '';
  loadTeamList('');
  document.getElementById('pe_status').checked = false;
  // 同步 UI 按鈕
  document.getElementById('pe_status').dispatchEvent(new Event('change'));
}
