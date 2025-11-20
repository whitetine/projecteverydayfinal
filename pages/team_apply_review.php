<?php
session_start();
if (!isset($_SESSION['u_ID'])) {
    echo "<script>alert('請先登入');location.href='../index.php';</script>";
    exit;
}

$role_ID = $_SESSION['role_ID'] ?? 0;
if (!in_array($role_ID, [1, 2])) {
    echo "<script>alert('此頁面僅限主任和科辦使用');location.href='../main.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>專題申請審核</title>
    <link rel="stylesheet" href="css/team_apply_review.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // 設定 API 路徑
        // 因為頁面是通過 hash 路由載入的，需要計算正確的路徑
        (function() {
            // 獲取當前頁面的基礎路徑
            const basePath = window.location.pathname;
            // 如果是通過 main.php 載入的，API 在根目錄
            if (basePath.includes('main.php')) {
                window.REVIEW_API_PATH = 'api.php';
            } else {
                // 否則從 pages 目錄回到根目錄
                window.REVIEW_API_PATH = '../api.php';
            }
            console.log('API 路徑設定為:', window.REVIEW_API_PATH);
        })();
    </script>
</head>
<body>
    <div class="review-container" id="reviewApp">
        <div class="review-header">
            <h1><i class="fas fa-clipboard-check"></i> 專題申請審核</h1>
            <p class="header-subtitle">審核學生提交的專題申請表</p>
        </div>

        <div id="loadingIndicator" class="loading-indicator">
            <i class="fas fa-spinner fa-spin"></i> 載入中...
        </div>

        <div id="applicationsList" class="applications-list"></div>

        <div id="emptyState" class="empty-state" style="display: none;">
            <i class="fas fa-inbox"></i>
            <p>目前沒有待審核的申請</p>
        </div>
    </div>

    <!-- 審核 Modal -->
    <div id="reviewModal" class="review-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeReviewModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-clipboard-check"></i> 審核申請</h2>
                <button class="modal-close" onclick="closeReviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <!-- 動態內容 -->
            </div>
            <div class="modal-footer">
                <button class="btn-reject" onclick="rejectApplication()">
                    <i class="fas fa-times-circle"></i> 退件
                </button>
                <button class="btn-approve" onclick="approveApplication()">
                    <i class="fas fa-check-circle"></i> 通過
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            // 確保 Vue 已載入
            if (typeof Vue === 'undefined') {
                console.error('Vue.js 尚未載入，請確認 head.php 已正確包含');
                return;
            }

            // 避免重複初始化
            const mountEl = document.getElementById('reviewApp');
            if (!mountEl) {
                console.error('找不到掛載元素 #reviewApp');
                return;
            }

            // 確保元素是有效的 Node
            if (!(mountEl instanceof Node)) {
                console.error('掛載元素不是有效的 Node');
                return;
            }

            // 如果已經掛載過，先卸載
            if (window.reviewAppInstance && typeof window.reviewAppInstance.unmount === 'function') {
                try {
                    window.reviewAppInstance.unmount();
                } catch (e) {
                    console.warn('卸載舊實例時發生錯誤:', e);
                }
            }

            const { createApp } = Vue;

            let currentReviewSubId = null;

            const app = createApp({
            data() {
                return {
                    applications: [],
                    loading: true
                };
            },
            mounted() {
                // 初始化 UI
                this.updateUI();
                // 載入資料
                this.loadApplications();
            },
            methods: {
                async loadApplications() {
                    this.loading = true;
                    try {
                        // 使用絕對路徑或從根目錄開始的路徑
                        let apiPath = window.REVIEW_API_PATH;
                        if (!apiPath) {
                            // 計算從根目錄開始的路徑
                            const pathname = window.location.pathname;
                            // 移除 main.php 和後面的部分，只保留基礎路徑
                            const basePath = pathname.replace(/\/main\.php.*$/, '').replace(/\/pages\/.*$/, '');
                            apiPath = (basePath.endsWith('/') ? basePath : basePath + '/') + 'api.php';
                        }
                        const url = `${apiPath}?do=get_pending_applications`;
                        console.log('載入申請列表，API URL:', url, '當前路徑:', window.location.pathname);
                        const response = await fetch(url);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}, URL: ${url}`);
                        }
                        const data = await response.json();
                        
                        if (data.ok) {
                            // json_ok() 返回格式是 {ok: true, applications: [...]}
                            this.applications = data.applications || [];
                            console.log('載入成功，申請數量:', this.applications.length);
                        } else {
                            console.error('API 返回錯誤:', data.msg, data);
                            Swal.fire({
                                icon: 'error',
                                title: '載入失敗',
                                text: data.msg || '無法載入申請列表',
                                confirmButtonText: '確定',
                                confirmButtonColor: '#667eea'
                            });
                        }
                    } catch (error) {
                        console.error('載入申請列表錯誤:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '錯誤',
                            text: '載入申請列表時發生錯誤：' + error.message,
                            confirmButtonText: '確定',
                            confirmButtonColor: '#667eea'
                        });
                    } finally {
                        this.loading = false;
                        // 更新 UI 顯示
                        this.updateUI();
                    }
                },
                openReviewModal(subId) {
                    window.currentReviewSubId = subId;
                    const application = this.applications.find(app => app.tap_ID === subId);
                    if (!application) return;

                    const modalBody = document.getElementById('reviewModalBody');
                    modalBody.innerHTML = `
                        <div class="review-info-section">
                            <h3>申請資訊</h3>
                            <div class="info-item">
                                <label>提交者：</label>
                                <span>${this.escapeHtml(application.submitter_name)} (${this.escapeHtml(application.tap_u_ID || application.dcsub_u_ID)})</span>
                            </div>
                            <div class="info-item">
                                <label>提交時間：</label>
                                <span>${this.formatDateTime(application.tap_update_d || application.dcsub_sub_d)}</span>
                            </div>
                        </div>

                        <div class="review-info-section">
                            <h3>專題資訊</h3>
                            <div class="info-item">
                                <label>專題名稱：</label>
                                <span class="project-name">${this.escapeHtml(application.tap_name || application.project_name)}</span>
                            </div>
                            <div class="info-item">
                                <label>指導老師：</label>
                                <span>${this.escapeHtml(application.teacher_name || application.tap_teacher || application.teacher_id)}</span>
                            </div>
                        </div>

                        <div class="review-info-section">
                            <h3>團隊成員</h3>
                            <div class="members-list">
                                ${(application.members || []).map(member => `
                                    <div class="member-item">
                                        <span class="member-name">${this.escapeHtml(member.u_name || member.u_ID)}</span>
                                        <span class="member-id">${this.escapeHtml(member.u_ID)}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="review-info-section">
                            <h3>申請表照片</h3>
                            <div class="image-container">
                                <img src="${this.escapeHtml(application.tap_url)}" alt="申請表" onerror="this.src='images/placeholder.png'">
                            </div>
                        </div>

                        ${application.user_comment && application.user_comment.trim() ? `
                            <div class="review-info-section">
                                <h3>說明文字</h3>
                                <p class="comment-text">${this.escapeHtml(application.user_comment)}</p>
                            </div>
                        ` : ''}

                        <div class="review-info-section">
                            <h3>審核備註</h3>
                            <textarea id="reviewRemark" class="form-control" rows="3" placeholder="請輸入審核備註（選填）"></textarea>
                        </div>
                    `;

                    document.getElementById('reviewModal').style.display = 'flex';
                },
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
                },
                escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                },
                updateUI() {
                    // 更新載入指示器
                    const loadingIndicator = document.getElementById('loadingIndicator');
                    const applicationsList = document.getElementById('applicationsList');
                    const emptyState = document.getElementById('emptyState');
                    
                    if (this.loading) {
                        if (loadingIndicator) loadingIndicator.style.display = 'block';
                        if (applicationsList) applicationsList.style.display = 'none';
                        if (emptyState) emptyState.style.display = 'none';
                    } else {
                        if (loadingIndicator) loadingIndicator.style.display = 'none';
                        
                        if (this.applications.length === 0) {
                            if (applicationsList) applicationsList.style.display = 'none';
                            if (emptyState) emptyState.style.display = 'block';
                        } else {
                            if (applicationsList) applicationsList.style.display = 'block';
                            if (emptyState) emptyState.style.display = 'none';
                            // 渲染申請列表
                            this.renderApplications();
                        }
                    }
                },
                renderApplications() {
                    const applicationsList = document.getElementById('applicationsList');
                    if (!applicationsList) return;
                    
                    applicationsList.innerHTML = this.applications.map(app => `
                        <div class="application-card" onclick="window.reviewAppInstance.openReviewModal(${app.tap_ID || app.sub_ID})">
                            <div class="card-header">
                                <h3>${this.escapeHtml(app.tap_name || app.project_name || '未命名專題')}</h3>
                                <span class="status-badge status-pending">待審核</span>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="label">提交者：</span>
                                    <span>${this.escapeHtml(app.submitter_name || app.tap_u_ID)}</span>
                                </div>
                                <div class="info-row">
                                    <span class="label">提交時間：</span>
                                    <span>${this.formatDateTime(app.tap_update_d || app.dcsub_sub_d)}</span>
                                </div>
                                <div class="info-row">
                                    <span class="label">指導老師：</span>
                                    <span>${this.escapeHtml(app.teacher_name || app.tap_teacher || '未指定')}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            }
        });

            // 掛載應用（確保元素存在且是有效的 Node）
            try {
                const mountElement = document.getElementById('reviewApp');
                if (mountElement && mountElement instanceof Node) {
                    window.reviewAppInstance = app.mount('#reviewApp');
                } else {
                    console.error('無法掛載：掛載元素無效');
                }
            } catch (error) {
                console.error('掛載 Vue 應用時發生錯誤:', error);
            }
        })();

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            currentReviewSubId = null;
        }

            window.approveApplication = async function() {
                if (!window.currentReviewSubId) return;

            const remark = document.getElementById('reviewRemark')?.value.trim() || '';

            const result = await Swal.fire({
                icon: 'question',
                title: '確認通過',
                text: '確定要通過此申請嗎？通過後將自動建立團隊。',
                showCancelButton: true,
                confirmButtonText: '確定通過',
                cancelButtonText: '取消',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                reverseButtons: true
            });

            if (!result.isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('tap_ID', window.currentReviewSubId);
                formData.append('action', 'approve');
                formData.append('remark', remark);

                const apiPath = window.REVIEW_API_PATH || '../api.php';
                const response = await fetch(`${apiPath}?do=review_application`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: '審核成功',
                        text: '申請已通過，團隊已成功建立。',
                        confirmButtonText: '確定',
                        confirmButtonColor: '#10b981'
                    });
                    window.closeReviewModal();
                    if (window.reviewAppInstance) {
                        window.reviewAppInstance.loadApplications();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '審核失敗',
                        text: data.msg || '審核時發生錯誤',
                        confirmButtonText: '確定',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: '錯誤',
                    text: '審核時發生錯誤：' + error.message,
                    confirmButtonText: '確定',
                    confirmButtonColor: '#ef4444'
                });
            }
        }

            window.rejectApplication = async function() {
                if (!window.currentReviewSubId) return;

            const remark = document.getElementById('reviewRemark')?.value.trim() || '';

            if (!remark) {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: '請輸入退件原因',
                    text: '退件時請填寫退件原因，以便學生了解需要改進的地方。',
                    input: 'textarea',
                    inputPlaceholder: '請輸入退件原因...',
                    showCancelButton: true,
                    showCancelButton: true,
                    confirmButtonText: '確定退件',
                    cancelButtonText: '取消',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    inputValidator: (value) => {
                        if (!value || value.trim().length === 0) {
                            return '請輸入退件原因';
                        }
                    }
                });

                if (!result.isConfirmed) return;
                remark = result.value.trim();
            } else {
                const result = await Swal.fire({
                    icon: 'question',
                    title: '確認退件',
                    text: '確定要退件此申請嗎？學生將收到退件通知。',
                    showCancelButton: true,
                    confirmButtonText: '確定退件',
                    cancelButtonText: '取消',
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true
                });

                if (!result.isConfirmed) return;
            }

            try {
                const formData = new FormData();
                formData.append('tap_ID', window.currentReviewSubId);
                formData.append('action', 'reject');
                formData.append('remark', remark);

                const apiPath = window.REVIEW_API_PATH || '../api.php';
                const response = await fetch(`${apiPath}?do=review_application`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: '退件成功',
                        text: '申請已退件，學生已收到通知。',
                        confirmButtonText: '確定',
                        confirmButtonColor: '#10b981'
                    });
                    window.closeReviewModal();
                    if (window.reviewAppInstance) {
                        window.reviewAppInstance.loadApplications();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '退件失敗',
                        text: data.msg || '退件時發生錯誤',
                        confirmButtonText: '確定',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: '錯誤',
                    text: '退件時發生錯誤：' + error.message,
                    confirmButtonText: '確定',
                    confirmButtonColor: '#ef4444'
                });
            }
        }
    </script>
</body>
</html>

