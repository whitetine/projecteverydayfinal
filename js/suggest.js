/* ================================
   suggest.js
   科辦多筆建議系統（自動編號 + 多筆新增）
================================ */

/* ===== API 路徑解析（支援動態載入） ===== */
function resolveSuggestApiUrl() {
    const path = window.location.pathname || '';
    if (path.includes('/pages/')) {
        return 'suggest_data.php';
    }
    return 'pages/suggest_data.php';
}

/* ===== SweetAlert Toast ===== */
const Toast = Swal.mixin({
    toast: true,
    position: "bottom-end",
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
});

/* ==========================================
   1. 初始化 – 載入屆別
========================================== */
function initSuggest() {
    const cohortSelect = document.getElementById("sg-cohort");
    const groupSelect = document.getElementById("sg-group");
    if (!cohortSelect || !groupSelect) {
        return false;
    }
    
    // 如果已經初始化過，先重置標記
    if (cohortSelect.dataset.initialized === 'true') {
        cohortSelect.dataset.initialized = 'false';
    }
    
    // 標記為已初始化
    cohortSelect.dataset.initialized = 'true';
    
    // 重新載入屆別資料（每次初始化都會重新載入）
    loadCohorts();

    // 移除舊的事件監聽器，然後添加新的
    const newCohortSelect = cohortSelect.cloneNode(true);
    cohortSelect.parentNode.replaceChild(newCohortSelect, cohortSelect);
    const freshCohortSelect = document.getElementById("sg-cohort");
    
    const newGroupSelect = groupSelect.cloneNode(true);
    groupSelect.parentNode.replaceChild(newGroupSelect, groupSelect);
    const freshGroupSelect = document.getElementById("sg-group");
    
    // 載入類型列表
    loadTypes();
    
    // 當屆別改變 → 載入類組，然後載入團隊
    freshCohortSelect.addEventListener("change", () => {
        const cohortId = freshCohortSelect.value;
        const exportBtn = document.getElementById("sg-export-btn");
        const typeSelect = document.getElementById("sg-type");
        const titleInput = document.getElementById("sg-title");
        
        if (cohortId) {
            loadGroups(cohortId);
        } else {
            freshGroupSelect.innerHTML = '<option value="">請先選擇屆別</option>';
            freshGroupSelect.disabled = true;
            if (typeSelect) {
                typeSelect.innerHTML = '<option value="">請先選擇屆別和類組</option>';
                typeSelect.disabled = true;
            }
            if (titleInput) titleInput.disabled = true;
            document.getElementById("sg-team-list").innerHTML = "";
            if (exportBtn) exportBtn.disabled = true;
        }
        updateTitle();
    });
    
    // 當類組改變 → 載入團隊
    freshGroupSelect.addEventListener("change", () => {
        const cohortId = freshCohortSelect.value;
        const groupId = freshGroupSelect.value;
        const exportBtn = document.getElementById("sg-export-btn");
        const typeSelect = document.getElementById("sg-type");
        const titleInput = document.getElementById("sg-title");
        
        if (cohortId && groupId) {
            if (typeSelect) {
                typeSelect.disabled = false;
            }
            if (titleInput) titleInput.disabled = false;
            loadTeams(cohortId, groupId);
            if (exportBtn) exportBtn.disabled = false;
        } else {
            if (typeSelect) {
                typeSelect.innerHTML = '<option value="">請先選擇屆別和類組</option>';
                typeSelect.disabled = true;
            }
            if (titleInput) titleInput.disabled = true;
            document.getElementById("sg-team-list").innerHTML = "";
            if (exportBtn) exportBtn.disabled = true;
        }
        updateTitle();
    });
    
    // 當類型改變 → 更新標題
    const typeSelect = document.getElementById("sg-type");
    if (typeSelect) {
        typeSelect.addEventListener("change", updateTitle);
    }
    
    // 匯出按鈕事件
    const exportBtn = document.getElementById("sg-export-btn");
    if (exportBtn) {
        exportBtn.addEventListener("click", () => {
            const cohortId = freshCohortSelect.value;
            const groupId = freshGroupSelect.value;
            if (cohortId && groupId) {
                exportSuggestions(cohortId, groupId);
            }
        });
    }
    
    return true;
}

// 立即嘗試初始化（如果元素已存在）
if (!initSuggest()) {
    // 如果元素不存在，等待 DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initSuggest();
        }, { once: true });
    } else {
        // DOM 已就緒但元素可能還沒載入（透過 AJAX 載入），延遲再試
        let attempts = 0;
        const maxAttempts = 20;
        
        const checkInterval = setInterval(() => {
            attempts++;
            if (initSuggest() || attempts >= maxAttempts) {
                clearInterval(checkInterval);
            }
        }, 100);
        
        // 同時使用 MutationObserver 監聽 DOM 變化（更即時）
        const observer = new MutationObserver(() => {
            if (initSuggest()) {
                observer.disconnect();
                clearInterval(checkInterval);
            }
        });
        observer.observe(document.body || document.documentElement, {
            childList: true,
            subtree: true
        });
        
        // 10 秒後停止觀察和檢查（避免記憶體洩漏）
        setTimeout(() => {
            observer.disconnect();
            clearInterval(checkInterval);
        }, 10000);
    }
}

// 監聽自定義事件（當頁面動態載入完成時）
$(document).on('pageLoaded scriptExecuted', function(e, path) {
    if (path && path.includes('suggest')) {
        setTimeout(() => {
            // 重置初始化標記，強制重新初始化
            const cohortSelect = document.getElementById("sg-cohort");
            if (cohortSelect) {
                cohortSelect.dataset.initialized = 'false';
            }
            if (!initSuggest()) {
                // 如果第一次失敗，再試一次
                setTimeout(initSuggest, 300);
            }
        }, 200);
    }
});

// 監聽頁面載入事件（當 loadSubpage 完成後）
// 使用 MutationObserver 監聽 #content 的變化
const contentObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.addedNodes.length > 0) {
            // 檢查是否有 suggest 相關的元素被加入
            const hasSuggest = Array.from(mutation.addedNodes).some(node => {
                if (node.nodeType === 1) { // Element node
                    return node.querySelector && (
                        node.querySelector('#sg-cohort') || 
                        node.id === 'sg-cohort' ||
                        node.classList?.contains('suggest-wrapper')
                    );
                }
                return false;
            });
            
            if (hasSuggest) {
                // 延遲一下確保 DOM 完全載入
                setTimeout(() => {
                    const cohortSelect = document.getElementById("sg-cohort");
                    if (cohortSelect && cohortSelect.dataset.initialized !== 'true') {
                        initSuggest();
                    }
                }, 100);
            }
        }
    });
});

// 開始觀察 #content 的變化
const contentEl = document.getElementById('content');
if (contentEl) {
    contentObserver.observe(contentEl, {
        childList: true,
        subtree: true
    });
}

/* ==========================================
   2. 取得屆別 (cohortdata)
========================================== */
async function loadCohorts() {
    try {
        const apiUrl = resolveSuggestApiUrl();
        console.log('載入屆別，API URL:', apiUrl);
        const r = await fetch(`${apiUrl}?action=listCohorts`, {
            credentials: 'same-origin'
        });
        
        if (!r.ok) {
            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        }
        
        const j = await r.json();
        console.log('屆別 API 回應:', j);
        
        if (!j.success) {
            throw new Error(j.msg || '未知錯誤');
        }
        
        let select = document.getElementById("sg-cohort");
        if (!select) {
            console.error('找不到屆別選單元素');
            return;
        }
        
        select.innerHTML = `<option value="">請選擇屆別</option>`;

        if (j.data && Array.isArray(j.data) && j.data.length > 0) {
            j.data.forEach(c => {
                select.innerHTML += `
                    <option value="${c.cohort_ID}">
                        ${c.cohort_name}
                    </option>`;
            });
            console.log(`已載入 ${j.data.length} 個屆別`);
        } else {
            select.innerHTML += `<option value="" disabled>查無屆別資料</option>`;
            console.warn('屆別資料為空');
        }

    } catch (err) {
        console.error('載入屆別失敗:', err);
        Toast.fire({ 
            icon: "error", 
            title: "屆別載入失敗",
            text: err.message || '請檢查網路連線或重新整理頁面'
        });
        
        // 顯示錯誤訊息在選單中
        const select = document.getElementById("sg-cohort");
        if (select) {
            select.innerHTML = `<option value="">載入失敗，請重新整理</option>`;
        }
    }
}

/* ==========================================
   2-1. 取得類型列表 (typedata)
========================================== */
async function loadTypes() {
    const typeSelect = document.getElementById("sg-type");
    if (!typeSelect) return;
    
    try {
        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(`${apiUrl}?action=listTypes`, {
            credentials: 'same-origin'
        });
        
        if (!r.ok) {
            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        }
        
        const j = await r.json();
        
        if (!j.success) {
            throw new Error(j.msg || '未知錯誤');
        }
        
        typeSelect.innerHTML = '<option value="">請選擇類型</option>';
        
        if (j.data && Array.isArray(j.data) && j.data.length > 0) {
            j.data.forEach(t => {
                typeSelect.innerHTML += `
                    <option value="${t.type_ID}">
                        ${t.type_value}
                    </option>`;
            });
        }
        
    } catch (err) {
        console.error('載入類型失敗:', err);
        const typeSelect = document.getElementById("sg-type");
        if (typeSelect) {
            typeSelect.innerHTML = '<option value="">載入失敗</option>';
        }
    }
}

/* ==========================================
   2-2. 自動生成標題
========================================== */
function updateTitle() {
    const cohortSelect = document.getElementById("sg-cohort");
    const groupSelect = document.getElementById("sg-group");
    const typeSelect = document.getElementById("sg-type");
    const titleInput = document.getElementById("sg-title");
    
    if (!cohortSelect || !groupSelect || !typeSelect || !titleInput) return;
    
    const cohortId = cohortSelect.value;
    const groupId = groupSelect.value;
    const typeId = typeSelect.value;
    
    if (!cohortId || !groupId || !typeId) {
        return;
    }
    
    const cohortName = cohortSelect.options[cohortSelect.selectedIndex]?.text || '';
    const groupName = groupSelect.options[groupSelect.selectedIndex]?.text || '';
    const typeName = typeSelect.options[typeSelect.selectedIndex]?.text || '';
    
    if (cohortName && groupName && typeName) {
        titleInput.value = `${cohortName}${groupName}${typeName}建議`;
    }
}

/* ==========================================
   2-3. 取得類組 (groupdata) - 依屆別
========================================== */
async function loadGroups(cohortId) {
    const groupSelect = document.getElementById("sg-group");
    if (!groupSelect) return;
    
    try {
        groupSelect.innerHTML = '<option value="">載入中...</option>';
        groupSelect.disabled = true;
        
        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(`${apiUrl}?action=listGroups&cohort_ID=${cohortId}`, {
            credentials: 'same-origin'
        });
        
        if (!r.ok) {
            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        }
        
        const j = await r.json();
        
        if (!j.success) {
            throw new Error(j.msg || '未知錯誤');
        }
        
        groupSelect.innerHTML = '<option value="">請選擇類組</option>';
        
        if (j.data && Array.isArray(j.data) && j.data.length > 0) {
            j.data.forEach(g => {
                groupSelect.innerHTML += `
                    <option value="${g.group_ID}">
                        ${g.group_name}
                    </option>`;
            });
            groupSelect.disabled = false;
        } else {
            groupSelect.innerHTML = '<option value="">該屆別無類組資料</option>';
            groupSelect.disabled = true;
        }
        
        // 清空團隊列表
        document.getElementById("sg-team-list").innerHTML = "";
        
    } catch (err) {
        console.error('載入類組失敗:', err);
        groupSelect.innerHTML = '<option value="">載入失敗</option>';
        groupSelect.disabled = true;
        Toast.fire({ 
            icon: "error", 
            title: "類組載入失敗",
            text: err.message || '請檢查網路連線'
        });
    }
}

/* ==========================================
   3. 取得團隊列表 (teamdata + groupdata)
========================================== */
async function loadTeams(cohortId, groupId) {
    const box = document.getElementById("sg-team-list");
    box.innerHTML = "<p>載入中...</p>";

    if (!cohortId || !groupId) {
        box.innerHTML = "";
        return;
    }

    try {
        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(`${apiUrl}?action=listTeams&cohort_ID=${cohortId}&group_ID=${groupId}`);
        const j = await r.json();

        if (!j.success) throw j.msg;

        if (j.data.length === 0) {
            box.innerHTML = "<p>該屆別和類組沒有團隊</p>";
            return;
        }

        // 建立團隊卡片
        box.innerHTML = "";
        j.data.forEach(team => {
            box.innerHTML += createTeamCard(team);
        });

        // 對每個 textarea 綁定自動編號事件
        bindAutoNumberEvent();

        // 載入每個團隊既有建議（載入到 textarea）
        j.data.forEach(team => {
            loadTeamSuggest(team.team_ID);
        });

    } catch (err) {
        console.log(err);
        box.innerHTML = "<p>載入團隊失敗</p>";
    }
}

/* ==========================================
   4. 團隊卡片 HTML（純 JS 產生）
========================================== */
function createTeamCard(team) {
    return `
        <div class="sg-team-card" data-team="${team.team_ID}">
            <div class="sg-team-header">
                <div class="sg-team-group">${team.group_name}</div>
                <div class="sg-team-title">${team.team_project_name}</div>
            </div>

            <textarea 
                class="sg-textarea" 
                id="sg-textarea-${team.team_ID}"
                data-team="${team.team_ID}"
                placeholder="點此輸入建議..."
            ></textarea>

            <div class="sg-btn-group" id="sg-btns-${team.team_ID}">
                <button class="sg-btn-save" onclick="saveSuggest(${team.team_ID})">儲存建議</button>
            </div>
        </div>
    `;
}

/* ==========================================
   5. 自動編號（滑鼠點入就出現 1.）
   Enter 自動跳下一行「n.」
========================================== */
function bindAutoNumberEvent() {
    document.querySelectorAll(".sg-textarea").forEach(area => {
        
        // 點進來自動補 1.
        area.addEventListener("focus", function () {
            if (this.value.trim() === "") {
                this.value = "1. ";
            }
        });

        // Enter → 自動下一行
        area.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();

                const lines = this.value.split("\n");
                const lastLine = lines[lines.length - 1];
                
                let nextNumber = lines.length + 1;

                // 追加下一行編號
                this.value += `\n${nextNumber}. `;
            }
        });
    });
}

/* ==========================================
   6. 儲存建議（新增或更新）
========================================== */
async function saveSuggest(teamId, suggestId = null) {
    const area = document.getElementById(`sg-textarea-${teamId}`);
    const titleInput = document.getElementById("sg-title");
    const typeSelect = document.getElementById("sg-type");
    
    let text = area.value.trim();
    if (!text) {
        Toast.fire({ icon: "warning", title: "請輸入建議內容" });
        return;
    }
    
    const suggestName = titleInput ? titleInput.value.trim() : "";
    const typeId = typeSelect ? typeSelect.value : "";

    try {
        const formData = new FormData();
        if (suggestId) {
            // 更新
            formData.append("action", "updateSuggest");
            formData.append("suggest_ID", suggestId);
        } else {
            // 新增
            formData.append("action", "addSuggest");
        }
        formData.append("team_ID", teamId);
        formData.append("content", text);
        formData.append("suggest_name", suggestName);
        if (typeId) {
            formData.append("type_ID", typeId);
        }

        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(apiUrl, { method: "POST", body: formData });
        const j = await r.json();

        if (!j.success) throw j.msg;

        Toast.fire({ icon: "success", title: suggestId ? "已更新建議" : "已新增建議" });

        // 儲存後重新載入過濾後的內容，然後設為唯讀
        await loadTeamSuggest(teamId);

    } catch (err) {
        console.log(err);
        Toast.fire({ icon: "error", title: "儲存失敗" });
    }
}

/* ==========================================
   7. 載入某團隊建議（載入到 textarea）
========================================== */
async function loadTeamSuggest(teamId) {
    const area = document.getElementById(`sg-textarea-${teamId}`);
    if (!area) return;

    try {
        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(`${apiUrl}?action=listSuggests&team_ID=${teamId}`);
        const j = await r.json();

        if (!j.success) throw j.msg;

        if (j.data.length === 0) {
            // 沒有建議，保持可編輯狀態
            area.value = "";
            setEditableMode(teamId);
            return;
        }

        // 載入最新的建議（第一筆）
        const latest = j.data[0];
        area.value = latest.suggest_comment || "";
        
        // 設為唯讀模式，顯示編輯和刪除按鈕
        setReadonlyMode(teamId, latest.suggest_ID);

    } catch (err) {
        console.log(err);
        Toast.fire({ icon: "error", title: "載入失敗" });
    }
}

/* ==========================================
   7-1. 設為唯讀模式（顯示編輯和刪除按鈕）
========================================== */
function setReadonlyMode(teamId, suggestId) {
    const area = document.getElementById(`sg-textarea-${teamId}`);
    const btnGroup = document.getElementById(`sg-btns-${teamId}`);
    
    if (!area || !btnGroup) return;
    
    area.readOnly = true;
    area.classList.add('sg-textarea-readonly');
    
    btnGroup.innerHTML = `
        <button class="sg-btn-edit" onclick="editSuggest(${teamId}, ${suggestId})">編輯</button>
        <button class="sg-btn-del" onclick="deleteSuggest(${suggestId}, ${teamId})">刪除</button>
    `;
}

/* ==========================================
   7-2. 設為可編輯模式（顯示儲存和取消按鈕）
========================================== */
function setEditableMode(teamId, suggestId = null) {
    const area = document.getElementById(`sg-textarea-${teamId}`);
    const btnGroup = document.getElementById(`sg-btns-${teamId}`);
    
    if (!area || !btnGroup) return;
    
    area.readOnly = false;
    area.classList.remove('sg-textarea-readonly');
    
    if (suggestId) {
        // 編輯模式：顯示儲存和取消
        btnGroup.innerHTML = `
            <button class="sg-btn-save" onclick="saveSuggest(${teamId}, ${suggestId})">儲存</button>
            <button class="sg-btn-cancel" onclick="cancelEdit(${teamId})">取消</button>
        `;
    } else {
        // 新增模式：只顯示儲存
        btnGroup.innerHTML = `
            <button class="sg-btn-save" onclick="saveSuggest(${teamId})">儲存建議</button>
        `;
    }
}

/* ==========================================
   7-3. 編輯建議
========================================== */
function editSuggest(teamId, suggestId) {
    setEditableMode(teamId, suggestId);
    const area = document.getElementById(`sg-textarea-${teamId}`);
    if (area) {
        area.focus();
        // 移動游標到最後
        area.setSelectionRange(area.value.length, area.value.length);
    }
}

/* ==========================================
   7-4. 取消編輯
========================================== */
async function cancelEdit(teamId) {
    // 重新載入原始內容
    await loadTeamSuggest(teamId);
}

/* ==========================================
   8. 刪除建議
========================================== */
async function deleteSuggest(id, teamId) {
    if (!confirm("確定要刪除此建議？")) return;

    try {
        const fd = new FormData();
        fd.append("action", "deleteSuggest");
        fd.append("suggest_ID", id);

        const apiUrl = resolveSuggestApiUrl();
        const r = await fetch(apiUrl, { method: "POST", body: fd });
        const j = await r.json();

        if (!j.success) throw j.msg;

        Toast.fire({ icon: "success", title: "已刪除" });
        
        // 刪除後重新載入（會變成可編輯狀態）
        await loadTeamSuggest(teamId);

    } catch (err) {
        Toast.fire({ icon: "error", title: "刪除失敗" });
    }
}

/* ==========================================
   9. 匯出建議
========================================== */
async function exportSuggestions(cohortId, groupId) {
    const apiUrl = resolveSuggestApiUrl();
    
    // 先檢查所有團隊是否都有建議
    try {
        const checkResponse = await fetch(`${apiUrl}?action=checkAllTeamsHaveSuggest&cohort_ID=${cohortId}&group_ID=${groupId}`);
        const checkData = await checkResponse.json();
        
        if (!checkData.success) {
            // 如果有團隊沒有建議，顯示錯誤訊息
            const teamsList = checkData.teamsWithoutSuggest ? checkData.teamsWithoutSuggest.join('、') : '';
            Toast.fire({
                icon: "error",
                title: checkData.msg || "部分團隊尚未填寫建議",
                html: teamsList ? `<div>以下團隊尚未填寫建議：<br><strong>${teamsList}</strong></div>` : undefined
            });
            return;
        }
        
        // 所有團隊都有建議，繼續匯出
        const path = window.location.pathname || '';
        let exportUrl = 'pages/suggest_export.php';
        if (path.includes('/pages/')) {
            exportUrl = 'suggest_export.php';
        }
        
        const params = new URLSearchParams({
            cohort_ID: cohortId,
            group_ID: groupId
        });
        
        // 開啟新視窗下載 PDF
        window.open(`${exportUrl}?${params.toString()}`, '_blank');
        
    } catch (error) {
        console.error('檢查建議時發生錯誤:', error);
        Toast.fire({
            icon: "error",
            title: "檢查建議時發生錯誤，請稍後再試"
        });
    }
}
