/**
 * 學生端里程碑頁面 - Vue.js 應用
 * 
 * 修改記錄：
 * 2025-11-16 - 重新設計學生端里程碑前端邏輯
 *   改動內容：添加完成按鈕功能、優先級顯示、通知功能
 *   相關功能：學生里程碑頁面互動邏輯
 *   方式：Vue.js 3 API
 */

const { createApp } = Vue;

createApp({
    data() {
        return {
            milestones: []
        };
    },
    computed: {
        // 排序後的里程碑清單
        // 規則：
        // 1) 依狀態區塊排序：進行中/退回在上，待審核其後，已完成再後，已刪除最後
        // 2) 同一區塊內，超級緊急(ms_priority=3)固定在前，其餘依優先級由高到低（3 > 2 > 1 > 0）
        // 3) 同優先級時，以截止時間(ms_end_d) 越鄰近（越早）越前；若無截止時間則用開始時間(ms_start_d)；再以 ms_ID 倒序
        sortedMilestones() {
            const copy = Array.isArray(this.milestones) ? [...this.milestones] : [];
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayMs = today.getTime();

            // 回傳「今天與期限日期」的差距（越小越鄰近）
            const diffFromToday = (end, start) => {
                const d = end || start;
                if (!d) return Number.MAX_SAFE_INTEGER;
                const t = Date.parse(d);
                if (Number.isNaN(t)) return Number.MAX_SAFE_INTEGER;
                return Math.abs(t - todayMs);
            };
            const statusRank = (s) => {
                // 0/1:進行中, 2:退回, 3:已完成, 4:待審核
                if (s === 0 || s === 1 || s === 2) return 0; // 進行區（含退回）
                if (s === 4) return 1;            // 待審核
                if (s === 3) return 2;            // 已完成
                return 3;                         // 其他/已刪除
            };
            return copy.sort((a, b) => {
                const ra = statusRank(Number(a.ms_status));
                const rb = statusRank(Number(b.ms_status));
                if (ra !== rb) return ra - rb;

                const pa = Number(a.ms_priority ?? 0);
                const pb = Number(b.ms_priority ?? 0);
                if (pa === 3 && pb !== 3) return -1;
                if (pb === 3 && pa !== 3) return 1;
                if (pb !== pa) return pb - pa; // 其餘按優先級高到低

                const da = diffFromToday(a.ms_end_d, a.ms_start_d);
                const db = diffFromToday(b.ms_end_d, b.ms_start_d);
                if (da !== db) return da - db; // 與今天越接近越前

                return Number(b.ms_ID || 0) - Number(a.ms_ID || 0);
            });
        },
        // 已完成進度：以狀態 3（已完成）計算
        completedCount() {
            return this.milestones.filter(m => Number(m.ms_status) === 3).length;
        },
        progressPercentage() {
            if (this.milestones.length === 0) return 0;
            return Math.round((this.completedCount / this.milestones.length) * 100);
        }
    },
    mounted() {
        this.loadMilestones();
    },
    methods: {
        // 載入里程碑列表
        async loadMilestones() {
            try {
                const response = await fetch('api.php?do=get_student_milestones');
                const data = await response.json();
                
                if (data.status === 'error') {
                    Swal.fire({
                        icon: 'error',
                        title: '載入失敗',
                        text: data.message || '無法載入里程碑資料',
                        confirmButtonText: '確定',
                        reverseButtons: true
                    });
                    return;
                }
                
                this.milestones = data || [];
            } catch (error) {
                console.error('載入里程碑失敗:', error);
                Swal.fire({
                    icon: 'error',
                    title: '載入失敗',
                    text: '網路連線錯誤，請稍後再試',
                    confirmButtonText: '確定',
                    reverseButtons: true
                });
            }
        },

        // 完成里程碑
        async completeMilestone(milestone) {
            // 如果正在提交中，不允許重複提交
            if (milestone.isSubmitting) {
                return;
            }

            // 如果狀態為 3（已完成），不允許再次提交
            if (milestone.ms_status === 3) {
                return;
            }

            // 設置提交中狀態
            milestone.isSubmitting = true;

            try {
                const formData = new FormData();
                formData.append('ms_ID', milestone.ms_ID);
                formData.append('action', 'complete');

                const response = await fetch('api.php?do=complete_milestone', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'error') {
                    // 恢復按鈕狀態
                    milestone.isSubmitting = false;
                    Swal.fire({
                        icon: 'error',
                        title: '操作失敗',
                        text: data.message || '無法完成里程碑',
                        confirmButtonText: '確定',
                        reverseButtons: true
                    });
                    return;
                }

                // 更新本地狀態為待審核（後端已經寫入資料）
                milestone.ms_status = 4;
                milestone.ms_completed_d = new Date().toISOString();
                milestone.isSubmitting = false;

                // 顯示成功提示（確定鍵在右邊）
                Swal.fire({
                    icon: 'success',
                    title: '已送出',
                    text: '里程碑已提交完成，等待指導老師審查',
                    confirmButtonText: '確定',
                    reverseButtons: true
                });

                // 不需要重新載入，因為已經更新本地狀態
            } catch (error) {
                console.error('完成里程碑失敗:', error);
                // 恢復按鈕狀態
                milestone.isSubmitting = false;
                Swal.fire({
                    icon: 'error',
                    title: '操作失敗',
                    text: '網路連線錯誤，請稍後再試',
                    confirmButtonText: '確定',
                    reverseButtons: true
                });
            }
        },

        // 獲取狀態樣式類別（卡片左側顏色）
        getStatusClass(status) {
            if (status === 0 || status === 1) return 'status-in-progress'; // 0 舊資料也視為進行中
            if (status === 2) return 'status-rejected';
            if (status === 3) return 'status-completed';
            if (status === 4) return 'status-review';
            return '';
        },

        // 獲取狀態標籤樣式類別（右上角 badge）
        getStatusBadgeClass(status) {
            if (status === 0 || status === 1) return 'in-progress'; // 0 舊資料也視為進行中
            if (status === 2) return 'rejected';
            if (status === 3) return 'completed';
            if (status === 4) return 'review';
            return '';
        },

        // 獲取狀態文字
        getStatusText(status) {
            if (status === 0 || status === 1) return '進行中'; // 0 舊資料也視為進行中
            if (status === 2) return '退回';
            if (status === 3) return '已完成';
            if (status === 4) return '待審核';
            return '未知狀態';
        },

        // 取得操作按鈕文字
        getActionButtonText(milestone) {
            const s = Number(milestone.ms_status);
            if (milestone.isSubmitting) return '提交中...';
            if (s === 3) return '已完成';
            if (s === 4) return '等待審查中';
            return '提交完成';
        },

        // 判斷操作按鈕是否 disabled
        isActionDisabled(milestone) {
            const s = Number(milestone.ms_status);
            if (milestone.isSubmitting) return true;
            // 已完成不可再按
            if (s === 3) return true;
            // 待審核不可再按
            if (s === 4) return true;
            // 其他狀態可以按（例如 1 進行中、2 退回）
            return false;
        },

        // 獲取優先級樣式類別
        getPriorityClass(priority) {
            if (priority === 0) return 'priority-normal';
            if (priority === 1) return 'priority-important';
            if (priority === 2) return 'priority-urgent';
            if (priority === 3) return 'priority-super-urgent';
            return 'priority-normal';
        },

        // 獲取優先級文字
        getPriorityText(priority) {
            if (priority === 0) return '一般';
            if (priority === 1) return '重要';
            if (priority === 2) return '緊急';
            if (priority === 3) return '超級緊急';
            return '一般';
        },

        // 格式化日期
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('zh-TW', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        },

        // 格式化日期時間
        formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString('zh-TW', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
}).mount('#studentMilestoneApp');
