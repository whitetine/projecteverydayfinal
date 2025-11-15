function resolveCheckReviewApiUrl() {
  const path = window.location.pathname || '';
  if (path.includes('/pages/')) {
    return 'checkreviewperiods_data.php';
  }
  return 'pages/checkreviewperiods_data.php';
}

window.initCheckReviewPeriods = function () {
  const page = document.querySelector('.checkreviewperiods-page');
  if (!page) {
    window._checkReviewPeriodsInitialized = false;
    return false;
  }
  if (window._checkReviewPeriodsInitialized) return true;
  window._checkReviewPeriodsInitialized = true;

  const apiUrl = resolveCheckReviewApiUrl();
  const sortForm = document.getElementById('sortForm');
  const sortSelect = document.getElementById('sortSelect');
  const sortHidden = document.getElementById('sortHidden');
  const teamSelect = document.getElementById('pe_target_ID');
  const cohortSelect = document.getElementById('cohort_ID');
  const statusInput = document.getElementById('pe_status');
  const statusText = document.querySelector('[data-switch-text]');
  const tableContainer = document.getElementById('periodTable');

  let activeSort = page.dataset.currentSort || 'created';
  if (sortSelect) sortSelect.value = activeSort;
  if (sortHidden) sortHidden.value = activeSort;

  function updateHashSort(value) {
    const baseUrl = window.location.href.split('#')[0];
    history.replaceState(null, '', `${baseUrl}#pages/checkreviewperiods.php?sort=${value}`);
  }

  function syncSwitchText() {
    if (!statusText) return;
    statusText.textContent = statusInput?.checked ? 'ON' : 'OFF';
  }
  statusInput?.addEventListener('change', syncSwitchText);
  syncSwitchText();

  sortForm?.addEventListener('submit', e => e.preventDefault());
  sortSelect?.addEventListener('change', e => {
    const value = e.target.value || 'created';
    activeSort = value;
    page.dataset.currentSort = value;
    if (sortHidden) sortHidden.value = value;
    updateHashSort(value);
    loadPeriodTable(value);
  });

  async function loadCohortList() {
    if (!cohortSelect) return;
    cohortSelect.innerHTML = '<option value="">載入中...</option>';
    try {
      const res = await fetch(`${apiUrl}?cohort_list=1`, { credentials: 'same-origin' });
      const list = await res.json();
      cohortSelect.innerHTML = '<option value="">請選擇屆別</option>' +
        list.map(c => `<option value="${c.cohort_ID}">${c.cohort_name} (${c.year_label})</option>`).join('');
    } catch (err) {
      console.error(err);
      cohortSelect.innerHTML = '<option value="">載入失敗，請重整</option>';
    }
  }

  async function loadTeamList() {
    if (!teamSelect) return;
    teamSelect.innerHTML = '<option value="">載入中...</option>';
    try {
      const res = await fetch(`${apiUrl}?team_list=1`, { credentials: 'same-origin' });
      const list = await res.json();
      teamSelect.innerHTML = '<option value="">請選擇團隊</option>' +
        list.map(t => `<option value="${t.team_ID}">${t.team_project_name || ('團隊 ' + t.team_ID)}</option>`).join('');
    } catch (err) {
      console.error(err);
      teamSelect.innerHTML = '<option value="">載入失敗，請重整</option>';
    }
  }

  async function loadPeriodTable(sortValue = activeSort) {
    if (!tableContainer) return;
    tableContainer.innerHTML = '<div class="table-wrapper"><div class="empty-state">資料載入中...</div></div>';
    try {
      const res = await fetch(`${apiUrl}?sort=${encodeURIComponent(sortValue)}`, { credentials: 'same-origin' });
      const html = await res.text();
      tableContainer.innerHTML = `<div class="table-wrapper">${html}</div>`;
    } catch (err) {
      console.error(err);
      tableContainer.innerHTML = '<div class="empty-state">資料載入失敗，請稍後再試</div>';
    }
  }

  window.editRow = function (row) {
    document.getElementById('form_action').value = 'update';
    document.getElementById('submitBtn').innerText = '更新';

    document.getElementById('period_ID').value = row.period_ID;
    document.getElementById('period_start_d').value = row.period_start_d;
    document.getElementById('period_end_d').value = row.period_end_d;
    document.getElementById('period_title').value = row.period_title;
    document.getElementById('pe_target_ID').value = row.pe_target_ID;
    document.getElementById('cohort_ID').value = row.cohort_ID;
    document.getElementById('pe_status').checked = (row.pe_status == 1);
    syncSwitchText();

    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  window.resetForm = function () {
    document.getElementById('form_action').value = 'create';
    document.getElementById('submitBtn').innerText = '新增';

    document.getElementById('period_ID').value = '';
    document.getElementById('period_start_d').value = '';
    document.getElementById('period_end_d').value = '';
    document.getElementById('period_title').value = '';
    document.getElementById('pe_target_ID').value = '';
    document.getElementById('cohort_ID').value = '';
    document.getElementById('pe_status').checked = false;
    syncSwitchText();
  };

  loadCohortList();
  loadTeamList();
  loadPeriodTable(activeSort);

  return true;
};

function tryInitCheckReviewPeriods() {
  const page = document.querySelector('.checkreviewperiods-page');
  if (page) {
    initCheckReviewPeriods();
    return true;
  }
  return false;
}

if (!tryInitCheckReviewPeriods()) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => tryInitCheckReviewPeriods(), { once: true });
  } else {
    setTimeout(() => {
      if (!tryInitCheckReviewPeriods()) {
        $(document).on('pageLoaded scriptExecuted', function (e, path) {
          if (path && path.includes('checkreviewperiods')) {
            setTimeout(tryInitCheckReviewPeriods, 150);
          }
        });
      }
    }, 120);
  }
}

$(document).on('pageLoaded scriptExecuted', function (e, path) {
  if (path && path.includes('checkreviewperiods')) {
    setTimeout(() => {
      if (!tryInitCheckReviewPeriods()) {
        setTimeout(tryInitCheckReviewPeriods, 200);
      }
    }, 120);
  }
});