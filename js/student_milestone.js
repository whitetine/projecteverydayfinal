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
        completedCount() {
            return this.milestones.filter(m => m.ms_status >= 1).length;
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

            // 如果狀態為 2（已通過），不允許再次提交
            if (milestone.ms_status === 2) {
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

                // 更新本地狀態為已完成（後端已經寫入資料）
                milestone.ms_status = 1;
                milestone.ms_completed_d = new Date().toISOString();
                milestone.isSubmitting = false;

                // 顯示成功提示（確定鍵在右邊）
                Swal.fire({
                    icon: 'success',
                    title: '已完成',
                    text: '里程碑已標記為完成，指導老師將收到通知',
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

        // 獲取狀態樣式類別
        getStatusClass(status) {
            if (status === 0) return 'status-pending';
            if (status === 1) return 'status-completed';
            if (status === 2) return 'status-approved';
            return '';
        },

        // 獲取狀態標籤樣式類別
        getStatusBadgeClass(status) {
            if (status === 0) return 'pending';
            if (status === 1) return 'completed';
            if (status === 2) return 'approved';
            return '';
        },

        // 獲取狀態文字
        getStatusText(status) {
            if (status === 0) return '進行中';
            if (status === 1) return '已完成';
            if (status === 2) return '已通過';
            return '未知';
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
