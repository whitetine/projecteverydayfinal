// 時程表管理 JavaScript

let teams = [];
let schedules = [];
let specialTimeRows = []; // 儲存特殊時間段行
let currentTinformaID = null;
let startTime = null;
let reportDuration = 20; // 預設20分鐘
let preparationTime = 10; // 預設10分鐘
let specialTimes = {
    lunch: { start: null, end: null },
    break: { start: null, end: null }
};

// 初始化函數（支援 AJAX 載入）
function initScheduleManage() {
    console.log('初始化時程表管理頁面');
    
    // 直接綁定事件（如果元素已經存在）
    const cohortSelect = document.getElementById('cohortSelect');
    if (cohortSelect) {
        console.log('找到屆別選擇器，綁定事件');
        
        // 移除舊的事件監聽器（避免重複綁定）
        const newSelect = cohortSelect.cloneNode(true);
        cohortSelect.parentNode.replaceChild(newSelect, cohortSelect);
        
        // 綁定新的事件監聽器
        newSelect.addEventListener('change', function() {
            const cohort_ID = this.value;
            console.log('屆別選擇變化:', cohort_ID);
            if (cohort_ID) {
                loadTeams(cohort_ID);
            } else {
                const tbody = document.getElementById('scheduleTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">請選擇屆別</td></tr>';
                }
            }
        });
        
        // 如果已經有選擇的值，自動載入
        if (newSelect.value) {
            console.log('已有選擇的屆別，自動載入:', newSelect.value);
            loadTeams(newSelect.value);
        }
    } else {
        console.warn('找不到屆別選擇器元素，將在 500ms 後重試');
        setTimeout(initScheduleManage, 500);
    }
    
    // 綁定時間輸入欄位的驗證
    const startTimeInput = document.getElementById('startTime');
    const endTimeInput = document.getElementById('endTime');
    
    if (startTimeInput) {
        startTimeInput.addEventListener('change', function() {
            if (this.value) {
                updateStartTime(this.value);
                // 驗證結束時間（如果已設定）
                if (endTimeInput && endTimeInput.value) {
                    validateTimeRange();
                }
            }
        });
    }
    
    // 結束時間是自動計算的，不需要手動輸入
    // 但保留驗證功能以防未來需要
    if (endTimeInput) {
        endTimeInput.addEventListener('change', function() {
            validateTimeRange();
        });
    }
}

// 初始化函數（立即執行，也支援延遲執行）
(function() {
    // 如果頁面已經載入，立即執行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initScheduleManage, 200);
        });
    } else {
        // 如果 DOM 已經載入完成，延遲執行以確保元素存在
        setTimeout(initScheduleManage, 200);
    }
})();

// 也支援 initPageScript 模式（AJAX 載入時使用）
window.initPageScript = function() {
    console.log('initPageScript 被調用（時程表管理）');
    setTimeout(initScheduleManage, 200);
};


// 載入團隊資料
async function loadTeams(cohort_ID) {
    if (!cohort_ID) {
        const tbody = document.getElementById('scheduleTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">請選擇屆別</td></tr>';
        }
        return;
    }
    
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) {
        console.error('找不到 scheduleTableBody 元素');
        return;
    }
    
    // 顯示載入中
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>載入中...</td></tr>';
    
    try {
        // 動態判斷 API 路徑
        // 從當前 URL 計算正確的 API 路徑
        const getApiPath = function() {
            const pathname = window.location.pathname || '';
            const hash = window.location.hash || '';
            
            // 如果路徑包含 /main.php，API 在根目錄
            if (pathname.includes('/main.php')) {
                // 從 pathname 提取項目根目錄
                const mainIndex = pathname.indexOf('/main.php');
                const projectRoot = pathname.substring(0, mainIndex);
                return projectRoot + (projectRoot.endsWith('/') ? '' : '/') + 'api.php';
            }
            
            // 如果 hash 包含 pages/，表示是通過 AJAX 載入的，API 在上一層
            if (hash.includes('pages/') || pathname.includes('/pages/')) {
                return '../api.php';
            }
            
            // 預設使用相對路徑
            return 'api.php';
        };
        
        const apiPath = getApiPath();
        const url = `${apiPath}?do=get_teams_schedule&cohort_ID=${cohort_ID}`;
        
        console.log('載入團隊資料，路徑判斷:', { 
            pathname: window.location.pathname, 
            hash: window.location.hash, 
            apiPath 
        });
        console.log('載入團隊資料，完整 URL:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('API 回應:', data);
        
        if (data.ok && data.teams) {
            teams = data.teams || [];
            
            console.log('API 返回的團隊資料:', teams);
            
            if (teams.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">該屆別目前沒有團隊資料</td></tr>';
                return;
            }
            
            // 預設順序：按照團隊ID排序
            teams.sort((a, b) => a.team_ID - b.team_ID);
            
            console.log('載入的團隊數量:', teams.length);
            console.log('第一個團隊的資料結構:', teams[0]);
            
            // 直接渲染表格
            renderScheduleTable();
        } else {
            const errorMsg = data.msg || '載入團隊資料失敗';
            console.error('API 返回錯誤:', errorMsg, data);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${errorMsg}</td></tr>`;
        }
    } catch (error) {
        console.error('載入團隊資料錯誤:', error);
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">無法載入團隊資料：${error.message}</td></tr>`;
    }
}

// 載入時程表資訊
async function loadScheduleInfo() {
    try {
        const response = await fetch('../api.php?do=get_schedule_info');
        const data = await response.json();
        
        if (data.ok) {
            if (data.info) {
                currentTinformaID = data.info.tinforma_ID;
                document.getElementById('scheduleInfo').value = data.info.tinforma_content || '';
                
                // 解析特殊時間段
                parseSpecialTimes(data.info.tinforma_content);
            }
            
            schedules = data.schedules || [];
            
            // 如果有已保存的時程，使用保存的時間
            if (schedules.length > 0) {
                updateScheduleTable();
            } else {
                // 否則使用預設時間計算
                renderScheduleTable();
            }
        } else {
            Swal.fire('錯誤', data.msg || '載入時程表資訊失敗', 'error');
        }
    } catch (error) {
        console.error('載入時程表資訊錯誤:', error);
        Swal.fire('錯誤', '無法載入時程表資訊', 'error');
    }
}

// 解析特殊時間段
function parseSpecialTimes(content) {
    if (!content) return;
    
    // 解析午餐時間
    const lunchMatch = content.match(/午餐時間[：:]\s*(\d{1,2}):(\d{2})\s*[-~]\s*(\d{1,2}):(\d{2})/);
    if (lunchMatch) {
        specialTimes.lunch.start = `${lunchMatch[1].padStart(2, '0')}:${lunchMatch[2]}`;
        specialTimes.lunch.end = `${lunchMatch[3].padStart(2, '0')}:${lunchMatch[4]}`;
        document.getElementById('lunchStart').value = specialTimes.lunch.start;
        document.getElementById('lunchEnd').value = specialTimes.lunch.end;
    }
    
    // 解析中場休息
    const breakMatch = content.match(/中場休息[：:]\s*(\d{1,2}):(\d{2})\s*[-~]\s*(\d{1,2}):(\d{2})/);
    if (breakMatch) {
        specialTimes.break.start = `${breakMatch[1].padStart(2, '0')}:${breakMatch[2]}`;
        specialTimes.break.end = `${breakMatch[3].padStart(2, '0')}:${breakMatch[4]}`;
        document.getElementById('breakStart').value = specialTimes.break.start;
        document.getElementById('breakEnd').value = specialTimes.break.end;
    }
}

// 渲染時程表
function renderScheduleTable() {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) {
        console.error('找不到 scheduleTableBody 元素');
        return;
    }
    
    console.log('開始渲染時程表，團隊數量:', teams.length);
    
    tbody.innerHTML = '';
    
    if (teams.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">目前沒有團隊資料</td></tr>';
        return;
    }
    
    try {
        // 計算時間（使用預設值）
        calculateTimes();
    } catch (error) {
        console.error('計算時間錯誤:', error);
        // 即使計算時間失敗，也要顯示資料
    }
    
    // 創建並插入所有團隊行
    teams.forEach((team, index) => {
        try {
            const row = createTeamRow(team, index + 1);
            tbody.appendChild(row);
        } catch (error) {
            console.error(`創建團隊 ${team.team_ID} 的行時出錯:`, error, team);
        }
    });
    
    console.log('渲染完成，已插入', tbody.querySelectorAll('tr.team-row').length, '行');
    
    // 更新結束時間顯示
    updateEndTimeDisplay();
    
    // 初始化拖放功能
    setTimeout(() => {
        initSortable();
    }, 100);
    
    // 添加行選中功能（避免重複綁定）
    const existingHandler = tbody.getAttribute('data-click-handler');
    if (!existingHandler) {
        tbody.setAttribute('data-click-handler', 'true');
        tbody.addEventListener('click', function(e) {
            const row = e.target.closest('tr');
            if (row && (row.classList.contains('team-row') || row.classList.contains('special-time-row'))) {
                tbody.querySelectorAll('tr.selected').forEach(r => r.classList.remove('selected'));
                row.classList.add('selected');
            }
        });
    }
}

// 創建團隊行
function createTeamRow(team, sequence) {
    const tr = document.createElement('tr');
    tr.className = 'team-row';
    tr.dataset.teamId = team.team_ID;
    tr.draggable = true;
    
    // 獲取該團隊的時程（如果有的話）
    const schedule = schedules.find(s => s.team_ID == team.team_ID);
    let timeRange = '-';
    if (schedule && schedule.time_start_d && schedule.time_end_d) {
        try {
            timeRange = formatTimeRange(schedule.time_start_d, schedule.time_end_d);
        } catch (error) {
            console.error('格式化時間範圍錯誤:', error);
        }
    }
    
    // 組合學號（每行一個）
    const studentIds = (team.students && Array.isArray(team.students) && team.students.length > 0) 
        ? team.students.map(s => (s.u_ID || '')).filter(Boolean).join('<br>') 
        : '-';
    
    // 組合姓名（每行一個）
    const studentNames = (team.students && Array.isArray(team.students) && team.students.length > 0)
        ? team.students.map(s => (s.u_name || '')).filter(Boolean).join('<br>')
        : '-';
    
    // 指導老師
    let teacherInfo = '未設定';
    if (team.teacher && team.teacher.u_ID) {
        const teacherId = team.teacher.u_ID || '';
        const teacherName = team.teacher.u_name || '';
        teacherInfo = teacherId + (teacherName ? '<br>' + teacherName : '');
    }
    
    // 專題題目
    const projectName = team.team_project_name || '未設定';
    
    console.log(`團隊 ${team.team_ID} 的資料:`, {
        students: team.students,
        teacher: team.teacher,
        projectName: projectName
    });
    
    tr.innerHTML = `
        <td class="time-cell">${timeRange}</td>
        <td class="sequence-cell">${sequence}</td>
        <td class="student-cell">${studentIds}</td>
        <td class="name-cell">${studentNames}</td>
        <td class="project-cell">${projectName}</td>
        <td class="teacher-cell">${teacherInfo}</td>
    `;
    
    return tr;
}

// 創建特殊時間段行
function createSpecialTimeRow(type, timeRange, sequence = null) {
    const tr = document.createElement('tr');
    tr.className = 'special-time-row';
    tr.dataset.specialType = type;
    
    let label = '';
    if (type === 'presentation_instruction') {
        label = '上台報告說明';
    } else if (type === 'lunch') {
        label = '午餐時間';
    } else if (type === 'break') {
        label = '中場休息';
    } else if (type === 'preparation') {
        label = '場次預備';
    }
    
    tr.innerHTML = `
        <td class="time-cell">${timeRange || '-'}</td>
        <td class="sequence-cell">${sequence || '-'}</td>
        <td class="student-cell">-</td>
        <td class="name-cell">-</td>
        <td class="project-cell" colspan="2" style="text-align: center; font-weight: 600; font-size: 15px;">${label}</td>
    `;
    
    return tr;
}

// 插入特殊時間段
function insertSpecialTime(type) {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) return;
    
    // 獲取當前選中的行（如果有的話）
    const selectedRow = tbody.querySelector('tr.selected');
    let insertIndex = -1;
    
    if (selectedRow) {
        // 在選中行之後插入
        const rows = Array.from(tbody.querySelectorAll('tr.team-row, tr.special-time-row'));
        insertIndex = rows.indexOf(selectedRow);
        if (insertIndex >= 0) insertIndex += 1;
    } else {
        // 如果沒有選中，詢問插入位置
        Swal.fire({
            title: '插入特殊時間',
            text: '請選擇插入位置',
            input: 'select',
            inputOptions: {
                'top': '表格最上方',
                'bottom': '表格最下方',
                'after_selected': '選中行之後（請先點擊要插入位置的行）'
            },
            inputPlaceholder: '選擇位置',
            showCancelButton: true,
            confirmButtonText: '確定',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                let insertPos = -1;
                if (result.value === 'top') {
                    insertPos = 0;
                } else if (result.value === 'bottom') {
                    insertPos = -1;
                } else {
                    const selected = tbody.querySelector('tr.selected');
                    if (selected) {
                        const rows = Array.from(tbody.querySelectorAll('tr.team-row, tr.special-time-row'));
                        insertPos = rows.indexOf(selected) + 1;
                    }
                }
                doInsertSpecialTime(type, insertPos);
            }
        });
        return;
    }
    
    doInsertSpecialTime(type, insertIndex);
}

// 執行插入特殊時間段
function doInsertSpecialTime(type, insertIndex) {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) return;
    
    // 獲取時間範圍（需要用戶輸入）
    Swal.fire({
        title: '設定時間範圍',
        html: `
            <div class="mb-3">
                <label>開始時間</label>
                <input type="time" id="specialStartTime" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>結束時間</label>
                <input type="time" id="specialEndTime" class="form-control" required>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '確定',
        cancelButtonText: '取消',
        didOpen: () => {
            const startInput = document.getElementById('specialStartTime');
            const endInput = document.getElementById('specialEndTime');
            if (startInput) startInput.focus();
        },
        preConfirm: () => {
            const start = document.getElementById('specialStartTime').value;
            const end = document.getElementById('specialEndTime').value;
            if (!start || !end) {
                Swal.showValidationMessage('請填寫完整的時間範圍');
                return false;
            }
            return { start, end };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const timeRange = `${result.value.start}-${result.value.end}`;
            const row = createSpecialTimeRow(type, timeRange);
            
            const rows = Array.from(tbody.querySelectorAll('tr.team-row, tr.special-time-row'));
            
            if (insertIndex === -1 || insertIndex >= rows.length) {
                // 插入到最後
                tbody.appendChild(row);
            } else {
                // 插入到指定位置
                tbody.insertBefore(row, rows[insertIndex]);
            }
            
            // 重新計算順序
            updateSequenceNumbers();
            
            // 重新初始化拖放
            setTimeout(() => {
                initSortable();
            }, 100);
        }
    });
}

// 更新順序號碼和時間
function updateSequenceNumbers() {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll('tr.team-row'));
    
    // 重新計算時間
    calculateTimes();
    
    // 更新每一行的組次和時間
    rows.forEach((row, index) => {
        const teamId = parseInt(row.dataset.teamId);
        const schedule = schedules.find(s => s.team_ID == teamId);
        
        // 更新組次
        const sequenceCell = row.querySelector('.sequence-cell');
        if (sequenceCell) {
            sequenceCell.textContent = index + 1;
        }
        
        // 更新時間
        const timeCell = row.querySelector('.time-cell');
        if (timeCell && schedule && schedule.time_start_d && schedule.time_end_d) {
            try {
                timeCell.textContent = formatTimeRange(schedule.time_start_d, schedule.time_end_d);
            } catch (error) {
                console.error('格式化時間範圍錯誤:', error);
            }
        }
    });
    
    // 更新結束時間顯示
    updateEndTimeDisplay();
}

// 驗證時間範圍
function validateTimeRange() {
    const startTimeInput = document.getElementById('startTime');
    const endTimeInput = document.getElementById('endTime');
    
    if (!startTimeInput || !endTimeInput) return true;
    
    const startValue = startTimeInput.value;
    const endValue = endTimeInput.value;
    
    if (!startValue || !endValue) return true;
    
    // 比較日期時間
    const start = new Date(startValue);
    const end = new Date(endValue);
    
    if (end < start) {
        Swal.fire({
            icon: 'error',
            title: '時間設定錯誤',
            text: '結束時間不可小於開始時間',
            confirmButtonText: '確定',
            confirmButtonColor: '#3085d6'
        });
        endTimeInput.value = '';
        return false;
    }
    
    return true;
}

// 更新開始時間（場次準備開始時間）
function updateStartTime(datetimeValue) {
    if (!datetimeValue) return;
    
    startTime = new Date(datetimeValue);
    
    // 如果有團隊資料，重新計算時間
    if (teams.length > 0) {
        calculateTimes();
        updateEndTimeDisplay();
        updateSequenceNumbers();
    }
}

// 更新結束時間顯示（最後一組報告完成時間）
function updateEndTimeDisplay() {
    const endTimeInput = document.getElementById('endTime');
    if (!endTimeInput) return;
    
    // 計算最後一組的報告完成時間
    if (schedules.length > 0) {
        // 找到最後一個時程
        const lastSchedule = schedules[schedules.length - 1];
        if (lastSchedule && lastSchedule.time_end_d) {
            const endDate = new Date(lastSchedule.time_end_d);
            // 轉換為 datetime-local 格式 (YYYY-MM-DDTHH:mm)
            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            const hours = String(endDate.getHours()).padStart(2, '0');
            const minutes = String(endDate.getMinutes()).padStart(2, '0');
            endTimeInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    } else {
        endTimeInput.value = '';
    }
}

// 計算時間
function calculateTimes() {
    // 優先使用輸入欄位的開始時間（場次準備開始時間）
    const startTimeInput = document.getElementById('startTime');
    if (startTimeInput && startTimeInput.value) {
        startTime = new Date(startTimeInput.value);
    } else if (!startTime) {
        // 如果沒有設定開始時間，使用今天的日期和預設時間（10:30）
        const today = new Date();
        today.setHours(10, 30, 0, 0);
        startTime = new Date(today);
    }
    
    // 確保有預設值
    if (!reportDuration) reportDuration = 20; // 預設20分鐘
    if (!preparationTime) preparationTime = 10; // 預設10分鐘
    
    let currentTime = new Date(startTime);
    schedules = []; // 清空現有時程，重新計算
    
    teams.forEach((team, index) => {
        // 檢查是否需要插入特殊時間段
        currentTime = checkAndInsertSpecialTime(currentTime, index);
        
        const startTimeStr = formatDateTime(currentTime);
        currentTime = addMinutes(currentTime, reportDuration);
        const endTimeStr = formatDateTime(currentTime);
        
        // 創建時程
        const schedule = {
            team_ID: team.team_ID,
            time_start_d: startTimeStr,
            time_end_d: endTimeStr,
            sort_no: index + 1
        };
        schedules.push(schedule);
        
        // 準備時間（最後一組不需要準備時間）
        if (index < teams.length - 1) {
            currentTime = addMinutes(currentTime, preparationTime);
        }
    });
    
    // 計算完成後，更新結束時間顯示
    updateEndTimeDisplay();
}

// 檢查並插入特殊時間段
function checkAndInsertSpecialTime(currentTime, teamIndex) {
    const currentHour = currentTime.getHours();
    const currentMinute = currentTime.getMinutes();
    const currentTimeStr = `${String(currentHour).padStart(2, '0')}:${String(currentMinute).padStart(2, '0')}`;
    
    // 檢查午餐時間（如果當前時間在午餐時間之前，且下一個時間會跨越午餐時間，則跳過午餐時間）
    if (specialTimes.lunch.start && specialTimes.lunch.end) {
        if (currentTimeStr < specialTimes.lunch.start) {
            // 計算下一個時間點
            const nextTime = addMinutes(new Date(currentTime), reportDuration);
            const nextHour = nextTime.getHours();
            const nextMinute = nextTime.getMinutes();
            const nextTimeStr = `${String(nextHour).padStart(2, '0')}:${String(nextMinute).padStart(2, '0')}`;
            
            // 如果下一個時間會跨越午餐時間，則跳過到午餐結束時間
            if (nextTimeStr >= specialTimes.lunch.start && nextTimeStr < specialTimes.lunch.end) {
                const [lunchHour, lunchMinute] = specialTimes.lunch.end.split(':').map(Number);
                currentTime.setHours(lunchHour, lunchMinute, 0, 0);
                return currentTime;
            }
        } else if (currentTimeStr >= specialTimes.lunch.start && currentTimeStr < specialTimes.lunch.end) {
            // 如果當前時間在午餐時間內，跳過到午餐結束時間
            const [lunchHour, lunchMinute] = specialTimes.lunch.end.split(':').map(Number);
            currentTime.setHours(lunchHour, lunchMinute, 0, 0);
            return currentTime;
        }
    }
    
    // 檢查中場休息
    if (specialTimes.break.start && specialTimes.break.end) {
        if (currentTimeStr < specialTimes.break.start) {
            // 計算下一個時間點
            const nextTime = addMinutes(new Date(currentTime), reportDuration);
            const nextHour = nextTime.getHours();
            const nextMinute = nextTime.getMinutes();
            const nextTimeStr = `${String(nextHour).padStart(2, '0')}:${String(nextMinute).padStart(2, '0')}`;
            
            // 如果下一個時間會跨越中場休息，則跳過到中場休息結束時間
            if (nextTimeStr >= specialTimes.break.start && nextTimeStr < specialTimes.break.end) {
                const [breakHour, breakMinute] = specialTimes.break.end.split(':').map(Number);
                currentTime.setHours(breakHour, breakMinute, 0, 0);
                return currentTime;
            }
        } else if (currentTimeStr >= specialTimes.break.start && currentTimeStr < specialTimes.break.end) {
            // 如果當前時間在中場休息內，跳過到中場休息結束時間
            const [breakHour, breakMinute] = specialTimes.break.end.split(':').map(Number);
            currentTime.setHours(breakHour, breakMinute, 0, 0);
            return currentTime;
        }
    }
    
    return currentTime;
}

let sortableInstance = null;

// 初始化拖放功能
function initSortable() {
    const tbody = document.getElementById('scheduleTableBody');
    
    if (!tbody) return;
    
    // 如果已經初始化，先銷毀
    if (sortableInstance) {
        sortableInstance.destroy();
    }
    
    sortableInstance = new Sortable(tbody, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        chosenClass: 'sortable-chosen',
        filter: '.special-time-row', // 允許拖動特殊時間段行
        onEnd: function(evt) {
            // 重新計算順序和時間
            const rows = Array.from(tbody.querySelectorAll('.team-row'));
            const newOrder = rows.map((row, index) => {
                const teamId = parseInt(row.dataset.teamId);
                const team = teams.find(t => t.team_ID == teamId);
                return team;
            }).filter(Boolean);
            
            teams = newOrder;
            
            // 重新計算時間和組次
            calculateTimes();
            
            // 更新所有行的時間和組次
            rows.forEach((row, index) => {
                const teamId = parseInt(row.dataset.teamId);
                const schedule = schedules.find(s => s.team_ID == teamId);
                
                // 更新時間
                const timeCell = row.querySelector('.time-cell');
                if (timeCell && schedule && schedule.time_start_d && schedule.time_end_d) {
                    try {
                        timeCell.textContent = formatTimeRange(schedule.time_start_d, schedule.time_end_d);
                    } catch (error) {
                        console.error('格式化時間範圍錯誤:', error);
                    }
                }
                
                // 更新組次
                const sequenceCell = row.querySelector('.sequence-cell');
                if (sequenceCell) {
                    sequenceCell.textContent = index + 1;
                }
            });
            
            // 更新結束時間顯示
            updateEndTimeDisplay();
        }
    });
}

// 更新時程表顯示
function updateScheduleTable() {
    const tbody = document.getElementById('scheduleTableBody');
    if (!tbody) return;
    
    // 保存特殊時間段行
    const specialRows = Array.from(tbody.querySelectorAll('.special-time-row'));
    const specialRowsData = specialRows.map(row => ({
        type: row.dataset.specialType,
        timeRange: row.querySelector('.time-cell').textContent,
        element: row.cloneNode(true)
    }));
    
    tbody.innerHTML = '';
    
    // 先插入特殊時間段行（如果有的話）
    // 這裡需要根據實際位置插入，暫時先插入所有團隊行
    teams.forEach((team, index) => {
        const schedule = schedules.find(s => s.team_ID == team.team_ID);
        const row = createTeamRow(team, index + 1);
        tbody.appendChild(row);
    });
    
    // 重新插入特殊時間段行（暫時放在最後，用戶可以手動拖動）
    specialRowsData.forEach(data => {
        tbody.appendChild(data.element);
    });
    
    // 更新順序號碼
    updateSequenceNumbers();
    
    // 重新初始化拖放功能
    setTimeout(() => {
        initSortable();
    }, 100);
}

// 套用特殊時間
function applySpecialTimes() {
    const lunchStart = document.getElementById('lunchStart').value;
    const lunchEnd = document.getElementById('lunchEnd').value;
    const breakStart = document.getElementById('breakStart').value;
    const breakEnd = document.getElementById('breakEnd').value;
    
    if (lunchStart && lunchEnd) {
        specialTimes.lunch.start = lunchStart;
        specialTimes.lunch.end = lunchEnd;
    }
    
    if (breakStart && breakEnd) {
        specialTimes.break.start = breakStart;
        specialTimes.break.end = breakEnd;
    }
    
    calculateTimes();
    updateScheduleTable();
    
    Swal.fire('成功', '特殊時間已套用', 'success');
}

// 儲存時程表資訊
async function saveScheduleInfo() {
    const content = document.getElementById('scheduleInfo').value;
    
    try {
        const formData = new FormData();
        formData.append('tinforma_content', content);
        if (currentTinformaID) {
            formData.append('tinforma_ID', currentTinformaID);
        }
        
        const response = await fetch('../api.php?do=save_schedule_info', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.ok) {
            currentTinformaID = data.tinforma_ID;
            Swal.fire('成功', '時程表資訊已保存', 'success');
        } else {
            Swal.fire('錯誤', data.msg || '保存失敗', 'error');
        }
    } catch (error) {
        console.error('保存時程表資訊錯誤:', error);
        Swal.fire('錯誤', '無法保存時程表資訊', 'error');
    }
}

// 儲存團隊時程
async function saveSchedules() {
    if (!currentTinformaID) {
        Swal.fire('提示', '請先儲存時程表設定', 'warning');
        return;
    }
    
    // 重新計算時間以確保最新
    calculateTimes();
    
    try {
        const formData = new FormData();
        formData.append('tinforma_ID', currentTinformaID);
        formData.append('schedules', JSON.stringify(schedules));
        
        const response = await fetch('../api.php?do=save_team_schedules', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.ok) {
            Swal.fire('成功', '時程表已保存', 'success');
        } else {
            Swal.fire('錯誤', data.msg || '保存失敗', 'error');
        }
    } catch (error) {
        console.error('保存團隊時程錯誤:', error);
        Swal.fire('錯誤', '無法保存團隊時程', 'error');
    }
}

// 工具函數
function formatDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function formatTimeRange(startStr, endStr) {
    if (!startStr || !endStr) return '';
    const start = new Date(startStr);
    const end = new Date(endStr);
    const startTime = `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`;
    const endTime = `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
    return `${startTime}-${endTime}`;
}

function addMinutes(date, minutes) {
    return new Date(date.getTime() + minutes * 60000);
}

