document.addEventListener("DOMContentLoaded", loadData);

function loadData() {
    // 從 URL hash 或 search 中取得參數
    let pid = "";
    
    // 先嘗試從 hash 中取得
    const hash = window.location.hash || '';
    const hashQuery = hash.split('?')[1];
    if (hashQuery) {
        const hashParams = new URLSearchParams(hashQuery);
        pid = hashParams.get("period_ID") || "";
    }
    
    // 如果 hash 中沒有，嘗試從 search 中取得
    if (!pid) {
        const params = new URLSearchParams(window.location.search);
        pid = params.get("period_ID") || "";
    }

    fetch(`pages/teacher_review_status_data.php?period_ID=${pid}`)
        .then(r => {
            console.log('HTTP 狀態:', r.status, r.statusText);
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.text(); // 先取得文字，看看實際回應
        })
        .then(text => {
            console.log('原始回應:', text);
            try {
                const data = JSON.parse(text);
                console.log('API 回應:', data);
                if (data.debug) {
                    console.log('調試資訊:', data.debug);
                }
                renderPage(data);
            } catch (e) {
                console.error('JSON 解析失敗:', e);
                console.error('回應內容:', text);
                alert('資料格式錯誤，請查看控制台');
            }
        })
        .catch(err => {
            console.error('載入資料失敗:', err);
            const tbody = document.getElementById("reviewStatusBody");
            if (tbody) {
                tbody.innerHTML = `
                    <tr><td colspan="5" class="text-center text-danger">
                        載入資料失敗：${err.message}<br>
                        <small>請檢查瀏覽器控制台以獲取更多資訊</small>
                    </td></tr>
                `;
            } else {
                alert('載入資料失敗：' + err.message);
            }
        });
}

function renderPage(data) {
    if (!data.success) {
        console.error('API 返回錯誤:', data);
        const tbody = document.getElementById("reviewStatusBody");
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="text-center text-danger">
                    ${data.msg || "載入失敗"}
                </td></tr>
            `;
        } else {
            alert(data.msg || "載入失敗");
        }
        return;
    }
    
    console.log('載入的資料:', data);

    const { periods = [], period_ID, active, rows = [] } = data;

    console.log('解析資料 - periods:', periods, 'active:', active, 'rows:', rows);

    // 檢查必要資料
    if (!periods || periods.length === 0) {
        console.warn('沒有週次資料');
        const tbody = document.getElementById("reviewStatusBody");
        const sel = document.getElementById("periodSelect");
        if (sel) {
            sel.innerHTML = '<option value="">沒有可用的評分時段</option>';
        }
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="text-center text-muted">沒有找到任何評分時段資料</td></tr>
            `;
        }
        // 即使沒有 periods，也要顯示空狀態
        return;
    }

    if (!active && periods.length > 0) {
        // 如果沒有 active，使用第一個 period
        active = periods[0];
        console.warn('沒有選取的週次資料，使用第一個:', active);
    }
    
    if (!active) {
        console.error('沒有選取的週次資料且 periods 為空');
        const tbody = document.getElementById("reviewStatusBody");
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="text-center text-muted">沒有選取的週次資料</td></tr>
            `;
        }
        return;
    }

    /* --- 週次選單 --- */
    let sel = document.getElementById("periodSelect");
    if (!sel) {
        console.error('找不到週次選單元素');
        return;
    }
    
    sel.innerHTML = "";
    periods.forEach(p => {
        // 格式化日期時間顯示：標題(開始時間-結束時間)
        // 只顯示日期部分，格式：YYYY-MM-DD
        const startDate = p.period_start_d ? p.period_start_d.split(' ')[0] : '';
        const endDate = p.period_end_d ? p.period_end_d.split(' ')[0] : '';
        const displayText = `${p.period_title}(${startDate}-${endDate})`;
        sel.innerHTML += `
            <option value="${p.period_ID}" ${p.period_ID == period_ID ? "selected" : ""}>
                ${displayText}
            </option>
        `;
    });

    sel.onchange = () => {
        // 使用 hash 路由
        window.location.hash = `pages/teacher_review_status.php?period_ID=${sel.value}`;
    };

    /* --- 顯示期間 --- */
    const periodInfoEl = document.getElementById("periodInfo");
    if (periodInfoEl && active) {
        periodInfoEl.innerText = `期間：${active.period_title}（${active.period_start_d} ~ ${active.period_end_d}）`;
    }

    /* --- 表格 --- */
    let tbody = document.getElementById("reviewStatusBody");
    if (!tbody) {
        console.error('找不到表格 body 元素');
        return;
    }
    
    tbody.innerHTML = "";

    if (!rows || rows.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="5" class="text-center text-muted">（你尚未被加入任何組別）</td></tr>
        `;
        return;
    }

    rows.forEach(r => {
        tbody.innerHTML += `
            <tr>
              <td>${r.team_name}（ID:${r.team_ID}）</td>
              <td>${r.expected}</td>
              <td>${r.actual}</td>
              <td>${r.is_complete ? "✅ 完成" : "❌ 未完成"}</td>
              <td>
                <a class="btn btn-sm btn-primary ajax-link"
                  href="pages/teacher_review_detail.php?team_ID=${r.team_ID}&period_ID=${period_ID}">
                  查看結果
                </a>
              </td>
            </tr>
        `;
    });
}
