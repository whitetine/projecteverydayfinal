/**
 * 甘特圖頁面 - Vue.js 應用
 * 
 * 修改記錄：
 * 2025-11-18 - 創建甘特圖頁面邏輯
 *   改動內容：顯示里程碑甘特圖，支持學生和老師查看
 *   相關功能：里程碑甘特圖視覺化
 *   方式：Vue.js 3 API
 */

if (!window._ganttAppInitialized) {
    window._ganttAppInitialized = true;
    
    function cleanupGanttApp() {
        if (window.ganttApp && typeof window.ganttApp.unmount === 'function') {
            try {
                window.ganttApp.unmount();
                window.ganttApp = null;
            } catch (e) {
                console.warn('卸載 gantt app 時出錯:', e);
            }
        }
        window._ganttAppInitialized = false;
    }

    cleanupGanttApp();

    const oldHandler = window._ganttCleanupHandler;
    if (oldHandler) {
        window.removeEventListener('pageBeforeUnload', oldHandler);
    }
    window._ganttCleanupHandler = cleanupGanttApp;
    window.addEventListener('pageBeforeUnload', cleanupGanttApp);

    const mountEl = document.querySelector('#ganttApp');
    if (!mountEl) {
        console.warn('找不到 #ganttApp 元素');
        window._ganttAppInitialized = false;
    } else {
        const { createApp } = Vue;

        const ganttApp = createApp({
            data() {
                return {
                    milestones: [],
                    teams: [],
                    selectedTeam: 0,
                    role_ID: window.GANTT_CONFIG?.role_ID || 0,
                    team_ID: window.GANTT_CONFIG?.team_ID || 0,
                    timeScale: [],
                    startDate: null,
                    endDate: null
                };
            },
            computed: {
                sortedMilestones() {
                    return [...this.milestones].sort((a, b) => {
                        const startA = new Date(a.ms_start_d || a.ms_created_d);
                        const startB = new Date(b.ms_start_d || b.ms_created_d);
                        return startA - startB;
                    });
                }
            },
            mounted() {
                if (this.role_ID === 4) {
                    this.loadTeams();
                } else {
                    this.loadGanttData();
                }
            },
            methods: {
                async loadTeams() {
                    try {
                        const response = await fetch('api.php?do=get_teams');
                        if (!response.ok) throw new Error('載入失敗');
                        const data = await response.json();
                        // 處理API返回格式
                        if (Array.isArray(data)) {
                            this.teams = data;
                        } else if (data.ok && Array.isArray(data.teams)) {
                            this.teams = data.teams;
                        } else if (Array.isArray(data.data)) {
                            this.teams = data.data;
                        } else {
                            this.teams = [];
                        }
                    } catch (error) {
                        console.error('載入團隊失敗:', error);
                        this.teams = [];
                    }
                },
                
                async loadGanttData() {
                    try {
                        let url = 'api.php?do=get_gantt_data';
                        if (this.role_ID === 4 && this.selectedTeam > 0) {
                            url += `&team_ID=${this.selectedTeam}`;
                        } else if (this.role_ID === 6 && this.team_ID > 0) {
                            url += `&team_ID=${this.team_ID}`;
                        }
                        
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('載入失敗');
                        const data = await response.json();
                        
                        // 處理API返回格式：可能是 {ok: true, milestones: [...]} 或直接是 {milestones: [...]}
                        if (data.ok && data.milestones) {
                            this.milestones = data.milestones || [];
                            this.startDate = data.startDate;
                            this.endDate = data.endDate;
                        } else if (data.milestones) {
                            this.milestones = data.milestones || [];
                            this.startDate = data.startDate;
                            this.endDate = data.endDate;
                        } else {
                            this.milestones = [];
                        }
                        this.generateTimeScale();
                    } catch (error) {
                        console.error('載入甘特圖資料失敗:', error);
                        this.milestones = [];
                    }
                },
                
                generateTimeScale() {
                    if (!this.startDate || !this.endDate) {
                        // 如果沒有日期範圍，從里程碑中計算
                        const dates = this.milestones
                            .map(m => [m.ms_start_d, m.ms_end_d])
                            .flat()
                            .filter(d => d)
                            .map(d => new Date(d));
                        
                        if (dates.length === 0) {
                            this.timeScale = [];
                            return;
                        }
                        
                        this.startDate = new Date(Math.min(...dates));
                        this.endDate = new Date(Math.max(...dates));
                    }
                    
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    const scaleDays = Math.max(30, Math.min(90, days));
                    
                    this.timeScale = [];
                    const current = new Date(start);
                    for (let i = 0; i <= scaleDays; i += 7) {
                        const date = new Date(current);
                        date.setDate(date.getDate() + i);
                        if (date <= end) {
                            this.timeScale.push(date.toISOString());
                        }
                    }
                },
                
                getBarStyle(milestone) {
                    if (!this.startDate || !this.endDate || this.timeScale.length === 0) {
                        return {};
                    }
                    
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    const containerWidth = this.timeScale.length * 100; // 假設每個時間標記100px寬
                    
                    return {
                        width: `${containerWidth}px`
                    };
                },
                
                getBarPosition(milestone) {
                    if (!this.startDate || !this.endDate) {
                        return { left: '0%', width: '0%' };
                    }
                    
                    // 如果沒有開始時間，使用結束時間或創建時間
                    let barStart = milestone.ms_start_d;
                    if (!barStart) {
                        barStart = milestone.ms_end_d || milestone.ms_created_d;
                    }
                    
                    // 如果沒有結束時間，使用開始時間或創建時間
                    let barEnd = milestone.ms_end_d;
                    if (!barEnd) {
                        barEnd = milestone.ms_start_d || milestone.ms_created_d;
                    }
                    
                    if (!barStart || !barEnd) {
                        return { left: '0%', width: '0%' };
                    }
                    
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const barStartDate = new Date(barStart);
                    const barEndDate = new Date(barEnd);
                    
                    const totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    const daysFromStart = Math.max(0, Math.ceil((barStartDate - start) / (1000 * 60 * 60 * 24)));
                    const barDuration = Math.max(1, Math.ceil((barEndDate - barStartDate) / (1000 * 60 * 60 * 24)));
                    
                    const leftPercent = (daysFromStart / totalDays) * 100;
                    const widthPercent = (barDuration / totalDays) * 100;
                    
                    return {
                        left: `${Math.max(0, Math.min(100, leftPercent))}%`,
                        width: `${Math.max(2, Math.min(100, widthPercent))}%`
                    };
                },
                
                getBarClass(milestone) {
                    const status = Number(milestone.ms_status);
                    if (status === 0) return 'not-started';
                    if (status === 1) return 'in-progress';
                    if (status === 2) return 'rejected';
                    if (status === 3) return 'completed';
                    if (status === 4) return 'review';
                    return 'not-started';
                },
                
                getStatusClass(status) {
                    const s = Number(status);
                    if (s === 0) return 'not-started';
                    if (s === 1) return 'in-progress';
                    if (s === 2) return 'rejected';
                    if (s === 3) return 'completed';
                    if (s === 4) return 'review';
                    return '';
                },
                
                getStatusText(status) {
                    const s = Number(status);
                    if (s === 0) return '還未開始';
                    if (s === 1) return '進行中';
                    if (s === 2) return '退回';
                    if (s === 3) return '已完成';
                    if (s === 4) return '待審核';
                    return '未知';
                },
                
                getPriorityClass(priority) {
                    const p = Number(priority);
                    if (p === 0) return 'normal';
                    if (p === 1) return 'important';
                    if (p === 2) return 'urgent';
                    if (p === 3) return 'super-urgent';
                    return 'normal';
                },
                
                getPriorityText(priority) {
                    const p = Number(priority);
                    if (p === 0) return '一般';
                    if (p === 1) return '重要';
                    if (p === 2) return '緊急';
                    if (p === 3) return '超級緊急';
                    return '一般';
                },
                
                formatDateShort(dateString) {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    return `${date.getMonth() + 1}/${date.getDate()}`;
                },
                
                goBack() {
                    if (this.role_ID === 6) {
                        window.location.hash = '#pages/student_milestone.php';
                    } else {
                        window.location.hash = '#pages/milestone.php';
                    }
                }
            }
        });

        ganttApp.mount('#ganttApp');
        window.ganttApp = ganttApp;
    }
}

