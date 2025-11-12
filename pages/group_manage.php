<?php
require '../includes/pdo.php';
$groups = $conn->query("SELECT * FROM `groupdata`")->fetchAll(PDO::FETCH_ASSOC);
?>

    <meta charset="UTF-8">
    <title>類組管理</title>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">-->
    <!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  -->


    <div class="page">
        <!-- 新增區塊 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">新增類組</h5>
            </div>
            <div class="card-body">
                <form id="addForm" method="post" action="api.php?do=add_group">
                    <div class="input-group">
                        <input type="text" name="group_name" id="group_name" class="form-control" placeholder="類組名稱" required>
                        <button type="button" class="btn btn-success btn-shadow" onclick="confirmAdd()">新增</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 清單區塊 -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">類組清單</h5>
                <small class="text-muted">共 <?= count($groups) ?> 筆</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm align-middle mb-0 bg-white text-center">
                        <thead class="table-light">
                            <tr>
                                <th style="width:72px"></th>
                                <th>類組</th>
                                <th style="width:120px">目前狀態</th>
                                <th style="width:140px">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $i => $g): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= $g['group_name'] ?></td>
                                    <td style="font-weight:bold;color:chocolate;"><?= $g['group_status'] ? '啟用' : '停用' ?></td>
                                    <td>
                                        <form method="post" action="../api.php?do=toggle_group" class="d-inline">
                                            <input type="hidden" name="group_ID" value="<?= (int)$g['group_ID'] ?>">
                                            <button type="submit" class="btn btn-<?= $g['group_status'] ? 'danger' : 'primary' ?>">
                                                <?= $g['group_status'] ? '停用' : '啟用' ?>
                                            </button>
                                        </form>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($groups)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">目前沒有資料</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        //新增類組
        function confirmAdd() {
            const name = document.getElementById('group_name').value.trim();
            if (!name) {
                Swal.fire({
                    icon: 'warning',
                    title: '請輸入類組名稱',
                    confirmButtonText: '好'
                });
                return;
            }
            //二次確認
            Swal.fire({
                title: '確定要新增這個類組？',
                text: `名稱：${name}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '確定新增',
                cancelButtonText: '取消'
            }).then(result => {
                if (result.isConfirmed) {
                    document.getElementById('addForm').submit();
                }
            });
        }

        // 停用/啟用 — 送出前跳出確認
        document.querySelectorAll('form[action*="toggle_group"]').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                const act = btn.textContent.trim(); // 停用 / 啟用
                const name = form.closest('tr').children[1].textContent.trim(); // 第二欄是類組名

                Swal.fire({
                    title: `${act}「${name}」？`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: `確定${act}`,
                    cancelButtonText: '取消'
                }).then(r => {
                    if (r.isConfirmed) form.submit();
                });
            });
        });

        function showToastFromHash() {
            const hash = window.location.hash || '';
            const query = hash.split('?')[1];
            if (!query) return;

            const p = new URLSearchParams(query);
            const toast = p.get('toast');
            const name = p.get('name') ? decodeURIComponent(p.get('name')) : '';
            if (!toast) return;

            let title = '操作完成',
                icon = 'success',
                text = '';
            if (toast === 'enabled') {
                title = '已啟用';
                text = name ? `類組：${name}` : '';
            } else if (toast === 'disabled') {
                title = '已停用';
                text = name ? `類組：${name}` : '';
            } else {
                title = '操作失敗';
                icon = 'error';
            }

            Swal.fire({
                icon,
                title,
                text,
                timer: 1600,
                showConfirmButton: false
            });

            // 清掉 hash 參數，避免重整又跳出
            const base = hash.split('?')[0];
            history.replaceState(null, '', location.pathname + location.search + base);
        }

        // 若有全域 initPageScript，放這裡呼叫；否則直接呼叫一次
        if (typeof initPageScript === 'function') {
            const _old = initPageScript;
            window.initPageScript = function() {
                _old();
                showToastFromHash();
            };
        } else {
            showToastFromHash();
        }
    </script>