function __initCheckReviewPeriods() {
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
  fetch("checkreviewperiods_data.php?cohort_list=1")
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
      });
}

/* 載入團隊：依屆別 */
function loadTeamList(cohortId, preselectTeamId) {
  const sel = document.getElementById('team_ID');
  if (!cohortId) {
    sel.innerHTML = '<option value="">請先選擇屆別</option>';
    return;
  }
  fetch(`checkreviewperiods_data.php?team_list=1&cohort_id=${encodeURIComponent(cohortId)}`)
    .then(r => r.json())
    .then(list => {
      sel.innerHTML = '<option value="">請選擇團隊</option>';
      list.forEach(t => {
        sel.innerHTML += `<option value="${t.team_ID}">${t.team_project_name}</option>`;
      });
      if (preselectTeamId) sel.value = String(preselectTeamId);
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
  fetch("checkreviewperiods_data.php?sort=" + (new URLSearchParams(window.location.search).get("sort") || "created"))
      .then(r => r.text())
      .then(html => {
          document.getElementById("periodTable").innerHTML = html;
      });
}

/* 編輯 */
function editRow(row) {
  document.getElementById('form_action').value = 'update';
  document.getElementById('submitBtn').innerText = '更新';

  document.getElementById('period_ID').value = row.period_ID;
  document.getElementById('period_start_d').value = row.period_start_d;
  document.getElementById('period_end_d').value = row.period_end_d;
  document.getElementById('period_title').value = row.period_title;
  document.getElementById('cohort_ID').value = row.cohort_ID;
  // 載入對應屆別的團隊，預選現有值
  loadTeamList(row.cohort_ID, row.pe_target_ID);
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
