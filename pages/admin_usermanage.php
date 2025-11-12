  <h1 class="mb-4">帳號管理</h1>

<?php
require '../includes/pdo.php';


$sql = "SELECT u.*, r.*,s.*,c.*
        FROM userdata u
        LEFT JOIN userrolesdata ur ON u.u_ID = ur.u_ID 
        LEFT JOIN roledata r ON ur.role_ID = r.role_ID
        LEFT JOIN statusdata s ON s.status_ID = u.u_status
        LEFT JOIN classdata c ON c.class_ID = u.c_ID
        ORDER BY u.u_ID ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 頭像的相對路徑（本檔在 /pages/，headshot 在專案根下一層）
function headshot_url(?string $fn): string {
  if (!$fn) return "https://cdn-icons-png.flaticon.com/512/1144/1144760.png";
  return "../headshot/" . rawurlencode($fn);
}
?>



<div class="table-responsive">
  <table class="table table-hover table-striped align-middle text-center table-bordered">
    <thead class="table-light">
      <tr>
        <th>頭像</th>
        <th>帳號</th>
        <th>姓名</th>
        <th>密碼</th>
        <th>信箱</th>
        <th>自介</th>
        <th>班級</th>
        <th>角色</th>
        <th>狀態</th>
        <th colspan="2">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td>
            <?php if (!empty($user['u_img'])): ?>
              <img src="headshot/<?= htmlspecialchars($user['u_img']) ?>" width="70" height="70" class="rounded-circle shadow-sm" style="object-fit:cover;">
            <?php else: ?>
              <span class="text-muted"> </span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($user['u_ID']) ?></td>
          <td><?= htmlspecialchars($user['u_name']) ?></td>
          <td><span class="text-muted">●●●●●●</span></td>
          <td><?= htmlspecialchars($user['u_gmail']) ?></td>
          <td><?= htmlspecialchars($user['u_profile']) ?></td>
          <td><?= htmlspecialchars($user['class_name']) ?></td>
          <td><?= htmlspecialchars($user['role_name'] ?? '無角色') ?></td>
          <td><?= htmlspecialchars($user['status_name']) ?></td>
          <!-- <td>
                  <span class="badge <?= $user['u_status'] == 1 ? 'bg-success' : 'bg-secondary' ?>">
                    <?= htmlspecialchars($user['status_name']) ?>
                  </span>
                </td> -->
          <td>
            <a href="main.php#pages/admin_edituser.php?u_ID=<?= $user['u_ID'] ?>" class="btn btn-success">編輯</a>

          </td>
          <td>
            <button
              class="btn <?= $user['u_status'] == 1 ? 'btn-danger' : 'btn-info' ?> toggle-btn"
              data-acc="<?= htmlspecialchars($user['u_ID']) ?>"
              data-status="<?= $user['u_status'] == 1 ? '0' : '1' ?>"
              data-action="<?= $user['u_status'] == 1 ? '停用' : '啟用' ?>">
              <?= $user['u_status'] == 1 ? '停用' : '啟用' ?>
            </button>

          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>