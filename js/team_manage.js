/* 團隊管理 JavaScript */
// 全域變數（將在初始化時設置）
let cohort_ID = null;
let teamUserField = 'team_u_ID';
let userRoleUidField = 'ur_u_ID';

// 篩選狀態
let currentFilters = {
    cohort_ID: null,
    group_ID: null,
    grade: '',
    class_ID: null
};

// 篩選選項
let filterOptions = {
    cohorts: [],
    groups: [],
    grades: [],
    classes: []
};

/* ==========================================
   載入篩選選項
========================================== */
async function loadFilterOptions() {
    try {
        const apiPath = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';
        const response = await fetch(`${apiPath}?do=get_filter_options`);
        const result = await response.json();
        
        if (!result.ok || !result.success) {
            throw new Error(result.msg || '載入篩選選項失敗');
        }
        
        filterOptions = result.data;
        populateFilters();
        
    } catch (error) {
        console.error('載入篩選選項失敗:', error);
    }
}

/* ==========================================
   填充篩選器選項
========================================== */
function populateFilters() {
    // 屆別
    const cohortSelect = document.getElementById('filterCohort');
    if (cohortSelect) {
        cohortSelect.innerHTML = '<option value="">全部</option>';
        filterOptions.cohorts.forEach(cohort => {
            const option = document.createElement('option');
            option.value = cohort.cohort_ID;
            option.textContent = cohort.cohort_name;
            if (cohort.cohort_ID == cohort_ID) {
                option.selected = true;
                currentFilters.cohort_ID = cohort.cohort_ID;
            }
            cohortSelect.appendChild(option);
        });
    }
    
    // 類組
    const groupSelect = document.getElementById('filterGroup');
    if (groupSelect) {
        groupSelect.innerHTML = '<option value="">全部</option>';
        filterOptions.groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.group_ID;
            option.textContent = group.group_name;
            groupSelect.appendChild(option);
        });
    }
    
    // 年級
    const gradeSelect = document.getElementById('filterGrade');
    if (gradeSelect) {
        gradeSelect.innerHTML = '<option value="">全部</option>';
        filterOptions.grades.forEach(grade => {
            const option = document.createElement('option');
            option.value = grade.enroll_grade;
            option.textContent = grade.enroll_grade;
            gradeSelect.appendChild(option);
        });
    }
    
    // 班級
    const classSelect = document.getElementById('filterClass');
    if (classSelect) {
        classSelect.innerHTML = '<option value="">全部</option>';
        filterOptions.classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.c_ID;
            option.textContent = cls.c_name;
            classSelect.appendChild(option);
        });
    }
}

/* ==========================================
   載入團隊資料
========================================== */
async function loadTeamData() {
    const container = document.getElementById('teamGroupsContainer');
    if (!container) return;
    
    try {
        container.innerHTML = '<div class="loading-indicator"><i class="fa-solid fa-spinner fa-spin"></i> 載入中...</div>';
        
        // 構建查詢參數
        const apiPath = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';
        const params = new URLSearchParams();
        
        if (currentFilters.cohort_ID) {
            params.append('cohort_ID', currentFilters.cohort_ID);
        } else if (cohort_ID) {
            params.append('cohort_ID', cohort_ID);
        }
        
        if (currentFilters.group_ID) {
            params.append('group_ID', currentFilters.group_ID);
        }
        
        if (currentFilters.grade) {
            params.append('grade', currentFilters.grade);
        }
        
        if (currentFilters.class_ID) {
            params.append('class_ID', currentFilters.class_ID);
        }
        
        const response = await fetch(`${apiPath}?do=get_team_management_data&${params.toString()}`);
        const result = await response.json();
        
        if (!result.ok || !result.success) {
            throw new Error(result.msg || '載入失敗');
        }
        
        renderTeamGroups(result.data);
        
    } catch (error) {
        console.error('載入團隊資料失敗:', error);
        const container = document.getElementById('teamGroupsContainer');
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p>載入失敗：${error.message}</p>
                </div>
            `;
        }
    }
}

/* ==========================================
   渲染團隊分組
========================================== */
function renderTeamGroups(data) {
    const container = document.getElementById('teamGroupsContainer');
    if (!container) return;
    
    let html = '';
    
    // 渲染每個類組
    if (data.groups && data.groups.length > 0) {
        data.groups.forEach(group => {
            html += `
                <div class="team-group-section">
                    <h3 class="group-title">${escapeHtml(group.group_name)}</h3>
                    <div class="team-list">
                        ${group.teams.map(team => createTeamCard(team)).join('')}
                    </div>
                </div>
            `;
        });
    }
    
    // 渲染未加入團隊的成員
    if (data.noTeamMembers && data.noTeamMembers.length > 0) {
        html += `
            <div class="no-team-section">
                <h3 class="no-team-title">未加入團隊</h3>
                <div class="no-team-list">
                    ${data.noTeamMembers.map(member => createNoTeamMember(member)).join('')}
                </div>
            </div>
        `;
    }
    
    if (!html) {
        html = '<div class="empty-state"><i class="fa-solid fa-inbox"></i><p>目前沒有符合條件的團隊資料</p></div>';
    }
    
    container.innerHTML = html;
    
    // 綁定點擊事件
    bindTeamCardEvents();
}

/* ==========================================
   創建團隊卡片 HTML
========================================== */
function createTeamCard(team) {
    const progress = team.progress || 0;
    const progressPercent = Math.round(progress);
    const progressColor = progressPercent >= 80 ? '#10b981' : progressPercent >= 50 ? '#3b82f6' : progressPercent >= 20 ? '#f59e0b' : '#ef4444';
    
    return `
        <div class="team-card" data-team-id="${team.team_ID}">
            <div class="team-card-header">
                <h4 class="team-name">${escapeHtml(team.team_project_name || '未命名專題')}</h4>
                <span class="team-progress-label" style="color: ${progressColor};">${progressPercent}%</span>
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: ${progressPercent}%; background: ${progressColor};">
                    <span class="progress-text">${progressPercent}%</span>
                </div>
            </div>
        </div>
    `;
}

/* ==========================================
   創建未加入團隊成員 HTML
========================================== */
function createNoTeamMember(member) {
    const name = member.u_name || member.u_ID;
    const initial = name.charAt(0);
    const hasAvatar = member.u_img && member.u_img.trim() !== '';
    // 處理檔名中的空格，轉換為 URL 編碼
    const cleanImgName = hasAvatar ? member.u_img.trim().replace(/\s+/g, '%20') : '';
    const avatarUrl = hasAvatar ? (member.u_img.startsWith('http') ? member.u_img : `headshot/${cleanImgName}`) : '';
    
    return `
        <div class="no-team-member">
            <div class="member-avatar" ${hasAvatar ? `style="background-image: url('${escapeHtml(avatarUrl)}'); background-size: cover; background-position: center;"` : ''}>
                ${hasAvatar ? '' : initial}
            </div>
            <div class="member-info">
                <p class="member-id">${escapeHtml(member.u_ID)}</p>
                <p class="member-name">${escapeHtml(name)}</p>
            </div>
        </div>
    `;
}

/* ==========================================
   綁定團隊卡片點擊事件
========================================== */
function bindTeamCardEvents() {
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('click', function() {
            const teamId = this.dataset.teamId;
            if (teamId) {
                showTeamDetail(teamId);
            }
        });
    });
}

/* ==========================================
   顯示團隊詳情 Modal
========================================== */
async function showTeamDetail(teamId) {
    const overlay = document.getElementById('teamModalOverlay');
    const modalBody = document.getElementById('teamModalBody');
    const modalTitle = document.getElementById('teamModalTitle');
    
    if (!overlay || !modalBody) return;
    
    try {
        overlay.classList.add('active');
        modalBody.innerHTML = '<div class="loading-indicator"><i class="fa-solid fa-spinner fa-spin"></i> 載入中...</div>';
        
        const apiPath = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';
        const response = await fetch(`${apiPath}?do=get_team_detail&team_ID=${teamId}`);
        const result = await response.json();
        
        if (!result.ok || !result.success) {
            throw new Error(result.msg || '載入失敗');
        }
        
        const team = result.data;
        renderTeamDetail(team, modalBody, modalTitle);
        
    } catch (error) {
        console.error('載入團隊詳情失敗:', error);
        modalBody.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <p>載入失敗：${error.message}</p>
            </div>
        `;
    }
}

/* ==========================================
   渲染團隊詳情
========================================== */
function renderTeamDetail(team, modalBody, modalTitle) {
    if (!team) return;
    
    // 設置標題
    if (modalTitle) {
        modalTitle.textContent = escapeHtml(team.team_project_name || '團隊詳情');
    }
    
    // 渲染內容
    let html = '';
    
    // 屆別和類組資訊
    if (team.cohort_name || team.group_name) {
        html += `
            <div class="team-detail-group">
                <div class="team-detail-label">基本資訊</div>
                <div class="team-detail-info-row">
                    ${team.cohort_name ? `<span class="info-badge"><i class="fa-solid fa-graduation-cap"></i> ${escapeHtml(team.cohort_name)}</span>` : ''}
                    ${team.group_name ? `<span class="info-badge"><i class="fa-solid fa-layer-group"></i> ${escapeHtml(team.group_name)}</span>` : ''}
                </div>
            </div>
        `;
    }
    
    // 進度
    const progress = team.progress || 0;
    const progressPercent = Math.round(progress);
    const progressColor = progressPercent >= 80 ? '#10b981' : progressPercent >= 50 ? '#3b82f6' : progressPercent >= 20 ? '#f59e0b' : '#ef4444';
    
    html += `
        <div class="team-detail-group">
            <div class="team-detail-label">進度</div>
            <div class="progress-container" style="margin-top: 0.5rem;">
                <div class="progress-bar" style="width: ${progressPercent}%; background: ${progressColor};">
                    <span class="progress-text">${progressPercent}%</span>
                </div>
            </div>
        </div>
    `;
    
    // 團隊成員（包括指導老師）
    if (team.members && team.members.length > 0) {
        // 分組：指導老師和學生
        const teachers = team.members.filter(m => m.role_ID == 4);
        const students = team.members.filter(m => m.role_ID == 6);
        
        html += `
            <div class="team-detail-group">
                <div class="team-detail-label">團隊成員</div>
        `;
        
        // 指導老師
        if (teachers.length > 0) {
            html += `
                <div class="member-section">
                    <div class="member-section-title">
                        <i class="fa-solid fa-chalkboard-user"></i> 指導老師
                    </div>
                    <div class="team-members-grid">
                        ${teachers.map(member => {
                            const name = member.u_name || member.u_ID;
                            const initial = name.charAt(0);
                            const hasAvatar = member.u_img && member.u_img.trim() !== '';
                            // 處理檔名中的空格，轉換為 URL 編碼
                            const cleanImgName = hasAvatar ? member.u_img.trim().replace(/\s+/g, '%20') : '';
                            const avatarUrl = hasAvatar ? (member.u_img.startsWith('http') ? member.u_img : `headshot/${cleanImgName}`) : '';
                            const avatarStyle = hasAvatar ? `style="background-image: url('${escapeHtml(avatarUrl)}'); background-size: cover; background-position: center;"` : '';
                            return `
                                <div class="team-member-item">
                                    <div class="team-member-avatar teacher-avatar" ${avatarStyle}>${hasAvatar ? '' : initial}</div>
                                    <p class="team-member-name">${escapeHtml(name)}</p>
                                    <p class="team-member-id">${escapeHtml(member.u_ID)}</p>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // 學生
        if (students.length > 0) {
            html += `
                <div class="member-section">
                    <div class="member-section-title">
                        <i class="fa-solid fa-user-graduate"></i> 學生
                    </div>
                    <div class="team-members-grid">
                        ${students.map(member => {
                            const name = member.u_name || member.u_ID;
                            const initial = name.charAt(0);
                            const hasAvatar = member.u_img && member.u_img.trim() !== '';
                            // 處理檔名中的空格，轉換為 URL 編碼
                            const cleanImgName = hasAvatar ? member.u_img.trim().replace(/\s+/g, '%20') : '';
                            const avatarUrl = hasAvatar ? (member.u_img.startsWith('http') ? member.u_img : `headshot/${cleanImgName}`) : '';
                            const avatarStyle = hasAvatar ? `style="background-image: url('${escapeHtml(avatarUrl)}'); background-size: cover; background-position: center;"` : '';
                            return `
                                <div class="team-member-item">
                                    <div class="team-member-avatar" ${avatarStyle}>${hasAvatar ? '' : initial}</div>
                                    <p class="team-member-name">${escapeHtml(name)}</p>
                                    <p class="team-member-id">${escapeHtml(member.u_ID)}</p>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
    } else {
        html += `
            <div class="team-detail-group">
                <div class="team-detail-label">團隊成員</div>
                <p class="team-detail-value" style="color: #94a3b8;">尚無成員</p>
            </div>
        `;
    }
    
    modalBody.innerHTML = html;
}

/* ==========================================
   關閉 Modal
========================================== */
function closeTeamModal() {
    const overlay = document.getElementById('teamModalOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

/* ==========================================
   重置篩選器
========================================== */
function resetFilters() {
    currentFilters = {
        cohort_ID: cohort_ID,
        group_ID: null,
        grade: '',
        class_ID: null
    };
    
    const cohortSelect = document.getElementById('filterCohort');
    const groupSelect = document.getElementById('filterGroup');
    const gradeSelect = document.getElementById('filterGrade');
    const classSelect = document.getElementById('filterClass');
    
    if (cohortSelect) cohortSelect.value = cohort_ID || '';
    if (groupSelect) groupSelect.value = '';
    if (gradeSelect) gradeSelect.value = '';
    if (classSelect) classSelect.value = '';
    
    loadTeamData();
}

/* ==========================================
   工具函數
========================================== */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* ==========================================
   初始化函數（供頁面調用）
========================================== */
function initTeamManagePage() {
    // 取得配置
    const config = window.TEAM_MANAGE_CONFIG || {};
    cohort_ID = config.cohort_ID;
    teamUserField = config.teamUserField || 'team_u_ID';
    userRoleUidField = config.userRoleUidField || 'ur_u_ID';
    
    // 檢查必要配置
    if (!cohort_ID) {
        console.error('TEAM_MANAGE_CONFIG 未正確設置');
        const container = document.getElementById('teamGroupsContainer');
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p>配置錯誤：無法載入團隊資料</p>
                </div>
            `;
        }
        return;
    }
    
    // 初始化篩選器
    currentFilters.cohort_ID = cohort_ID;
    
    // 載入篩選選項
    loadFilterOptions().then(() => {
        // 載入團隊資料
        loadTeamData();
    });
    
    // 綁定篩選器事件
    const cohortSelect = document.getElementById('filterCohort');
    const groupSelect = document.getElementById('filterGroup');
    const gradeSelect = document.getElementById('filterGrade');
    const classSelect = document.getElementById('filterClass');
    const resetBtn = document.getElementById('resetFilters');
    
    if (cohortSelect) {
        cohortSelect.addEventListener('change', function() {
            currentFilters.cohort_ID = this.value ? parseInt(this.value) : cohort_ID;
            loadTeamData();
        });
    }
    
    if (groupSelect) {
        groupSelect.addEventListener('change', function() {
            currentFilters.group_ID = this.value ? parseInt(this.value) : null;
            loadTeamData();
        });
    }
    
    if (gradeSelect) {
        gradeSelect.addEventListener('change', function() {
            currentFilters.grade = this.value || '';
            loadTeamData();
        });
    }
    
    if (classSelect) {
        classSelect.addEventListener('change', function() {
            currentFilters.class_ID = this.value ? parseInt(this.value) : null;
            loadTeamData();
        });
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', resetFilters);
    }
    
    // 綁定 Modal 關閉事件
    const closeBtn = document.getElementById('teamModalClose');
    const overlay = document.getElementById('teamModalOverlay');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeTeamModal);
    }
    
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeTeamModal();
            }
        });
    }
    
    // ESC 鍵關閉
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTeamModal();
        }
    });
}

// 自動初始化（如果配置已存在）
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initTeamManagePage, 100);
    });
} else {
    setTimeout(initTeamManagePage, 100);
}

// 導出初始化函數供外部調用
window.initTeamManagePage = initTeamManagePage;
