// 账号管理页面脚本
function initPageScript() {
    // 切换用户状态
    $(document).off("click", ".toggle-btn").on("click", ".toggle-btn", function () {
        const btn = $(this);
        const acc = btn.data('acc');
        const status = btn.data('status');
        const action = btn.data('action');

        if (window.Swal) {
            Swal.fire({
                title: '確認操作',
                text: `確定要${action}此帳號嗎？`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '確定',
                cancelButtonText: '取消',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    location.href = `pages/somefunction/toggle_user.php?acc=${acc}&status=${status}`;
                }
            });
        } else {
            if (confirm(`確定要${action}此帳號嗎？`)) {
                location.href = `pages/somefunction/toggle_user.php?acc=${acc}&status=${status}`;
            }
        }
    });

    // 筛选表单提交（使用 AJAX 避免页面刷新）
    $('#filterForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const params = new URLSearchParams(new FormData(this));
        // 移除 page 参数（如果有）
        params.delete('page');
        const queryString = params.toString();
        const newPath = `pages/admin_usermanage.php${queryString ? '?' + queryString : ''}`;
        
        // 更新 hash 并触发页面重新加载
        location.hash = '#' + newPath;
        
        // 如果 loadSubpage 函数存在，使用它；否则使用 location.reload
        if (typeof loadSubpage === 'function') {
            loadSubpage(newPath);
        } else {
            // 延迟一下确保 hash 更新
            setTimeout(() => {
                window.location.reload();
            }, 100);
        }
    });
}

// 页面加载时初始化
if (typeof initPageScript === 'function') {
    initPageScript();
} else {
    // 如果 initPageScript 还没定义，等待 DOM 加载完成
    $(document).ready(function() {
        if (typeof initPageScript === 'function') {
            initPageScript();
        }
    });
}

