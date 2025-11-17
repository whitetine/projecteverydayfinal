// 動態解析 API URL（支援動態載入）
function resolveCheckReviewPeriodsApiUrl() {
  const path = window.location.pathname || '';
  if (path.includes('/pages/')) {
    return 'checkreviewperiods_data.php';
  }
  return 'pages/checkreviewperiods_data.php';
}

let cohortMap = {};

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
  
  try { setupCohortDropdown(); } catch (e) { console.error(e); }
  try { loadCohortList(); } catch (e) { console.error(e); }
  try { loadPeriodTable(); } catch (e) { console.error(e); }
  try { setupModeSelector(); } catch (e) { console.error(e); }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', __initCheckReviewPeriods);
} else {
  __initCheckReviewPeriods();
}

function setupCohortDropdown() {
  const btn = document.getElementById('cohortBtn');
  const menu = document.getElementById('cohortMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    menu.classList.toggle('show');
  });

  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('show');
    }
  });
}

function buildCohortSummary(ids) {
  const labels = ids
    .map(id => cohortMap[id]?.label || id)
    .filter(Boolean);
  if (labels.length === 0) return '請選擇屆別';
  if (labels.length === 1) return labels[0];
  if (labels.length === 2) return labels.join('、');
  return `${labels[0]} 等 ${labels.length} 個屆別`;
}

function getSelectedCohortValues() {
  const container = document.getElementById('cohortOptions');
  if (!container) return [];
  return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'))
    .map(input => input.value)
    .filter(Boolean);
}

function handleCohortChange(triggerLoad = true) {
  const selected = getSelectedCohortValues();
  const valuesInput = document.getElementById('cohort_values');
  if (valuesInput) valuesInput.value = selected.join(',');
  const primaryInput = document.getElementById('cohort_primary');
  if (primaryInput) primaryInput.value = selected[0] || '';
  const labelEl = document.getElementById('cohortLabel');
  if (labelEl) labelEl.textContent = buildCohortSummary(selected);
  renderCohortTags(selected);
  if (triggerLoad) {
    loadTeamList(selected);
  }
}

function setSelectedCohorts(values, triggerLoad = true) {
  const container = document.getElementById('cohortOptions');
  if (!container) return;
  const arr = Array.isArray(values) ? values.map(String) : (values ? [String(values)] : []);
  container.querySelectorAll('input[type="checkbox"]').forEach(input => {
    input.checked = arr.includes(input.value);
  });
  handleCohortChange(triggerLoad);
}

function renderCohortTags(ids) {
  const tagsEl = document.getElementById('cohortTags');
  if (!tagsEl) return;
  tagsEl.innerHTML = '';
  if (!ids.length) {
    tagsEl.innerHTML = '<span class="text-muted small">尚未選擇屆別</span>';
    return;
  }
  ids.slice(0, 3).forEach(id => {
    const tag = document.createElement('span');
    tag.className = 'cohort-tag';
    tag.innerHTML = `${cohortMap[id]?.label || id}<span class="remove" data-id="${id}">&times;</span>`;
    tagsEl.appendChild(tag);
  });
  if (ids.length > 3) {
    const extra = document.createElement('span');
    extra.className = 'cohort-tag';
    extra.textContent = `+${ids.length - 3}`;
    tagsEl.appendChild(extra);
  }
  tagsEl.querySelectorAll('.remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const checkbox = document.querySelector(`#cohortOptions input[value="${id}"]`);
      if (checkbox) {
        checkbox.checked = false;
        handleCohortChange(true);
      }
    });
  });
}

function renderCohortOptions(list) {
  const container = document.getElementById('cohortOptions');
  if (!container) return;
  cohortMap = {};

  if (!Array.isArray(list) || !list.length) {
    container.innerHTML = '<div class="text-muted small px-3 py-2">尚無可選屆別</div>';
    handleCohortChange(false);
    return;
  }

  container.innerHTML = '';
  list.forEach(c => {
    const labelText = `${c.cohort_name} (${c.year_label})`;
    cohortMap[String(c.cohort_ID)] = { label: labelText };

    const label = document.createElement('label');
    label.className = 'form-check cohort-option';
    label.innerHTML = `
      <input type="checkbox" class="form-check-input" value="${c.cohort_ID}">
      <span class="form-check-label">${labelText}</span>
    `;
    container.appendChild(label);
  });

  container.querySelectorAll('input[type="checkbox"]').forEach(input => {
    input.addEventListener('change', () => handleCohortChange(true));
  });
}

/* 載入屆別 */
function loadCohortList() {
  const apiUrl = resolveCheckReviewPeriodsApiUrl();
  fetch(`${apiUrl}?cohort_list=1`)
      .then(r => r.json())
      .then(list => {
          renderCohortOptions(list);
          const presetValues = (document.getElementById('cohort_values')?.value || '')
            .split(',')
            .filter(Boolean);
          const primary = document.getElementById('cohort_primary')?.value || '';
          const initial = presetValues.length ? presetValues : (primary ? [primary] : []);
          setSelectedCohorts(initial, false);
          handleCohortChange(true);
      })
      .catch(err => {
          console.error('載入屆別失敗:', err);
          const container = document.getElementById("cohortOptions");
          if (container) container.innerHTML = '<div class="text-danger small px-3 py-2">屆別載入失敗</div>';
      });
}

/* 載入團隊：依屆別 */
function loadTeamList(cohortId, preselectTeamId) {
  const sel = document.getElementById('team_ID');
  if (!sel) return;
  const ids = Array.isArray(cohortId)
    ? cohortId.filter(Boolean)
    : (cohortId ? [cohortId] : []);
  if (!ids.length) {
    sel.innerHTML = '<option value="">請先選擇屆別</option>';
    return;
  }
  const apiUrl = resolveCheckReviewPeriodsApiUrl();
  fetch(`${apiUrl}?team_list=1&cohort_id=${encodeURIComponent(ids.join(','))}`)
    .then(r => r.json())
    .then(list => {
      // 先加入「全部」選項
      sel.innerHTML = '<option value="ALL">全部 (ALL)</option>';
      // 再加入各團隊選項
      list.forEach(t => {
        sel.innerHTML += `<option value="${t.team_ID}">${t.team_project_name}</option>`;
      });
      if (preselectTeamId) {
        sel.value = String(preselectTeamId);
      }
    })
    .catch(err => {
        console.error('載入團隊失敗:', err);
        if (sel) sel.innerHTML = '<option value="">載入失敗</option>';
    });
}

/* 模式選擇 */
function setupModeSelector() {
  const labelEl = document.getElementById('modeLabel');
  const hiddenEl = document.getElementById('mode_value');
  const dropdownEl = document.getElementById('modeDropdown');
  const hintEl = document.getElementById('modeHint');
  const dropdownInstance = (dropdownEl && window.bootstrap)
    ? bootstrap.Dropdown.getOrCreateInstance(dropdownEl)
    : null;

  const applyMode = (btn) => {
    if (!btn) return;
    const mode = btn.dataset.mode || '';
    const text = btn.textContent.trim() || '請選擇模式';
    const hint = btn.dataset.hint || '';
    if (labelEl) labelEl.textContent = text;
    if (hiddenEl) hiddenEl.value = mode;
    if (hintEl) hintEl.textContent = hint || ' ';
    dropdownInstance?.hide();
  };

  document.querySelectorAll('.mode-option').forEach(btn => {
    btn.addEventListener('click', () => applyMode(btn));
  });

  const initialValue = hiddenEl?.value || 'in';
  const initialBtn =
    document.querySelector(`.mode-option[data-mode="${initialValue}"]`) ||
    document.querySelector('.mode-option');
  applyMode(initialBtn);
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
  const selectedCohort = row.cohort_ID ? [String(row.cohort_ID)] : [];
  setSelectedCohorts(selectedCohort, false);
  // 載入對應屆別的團隊，預選現有值
  loadTeamList(selectedCohort, row.pe_target_ID || '');
  const statusInput = document.getElementById('pe_status');
  if (statusInput) statusInput.value = row.pe_status == 1 ? '1' : '0';

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
  setSelectedCohorts([], true);
  const statusInput = document.getElementById('pe_status');
  if (statusInput) statusInput.value = '1';
}
