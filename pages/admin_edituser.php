<?php
require '../includes/pdo.php';

$u_ID = $_GET['u_ID'] ?? '';
if (!$u_ID) {
  echo '<div class="alert alert-danger">缺少參數</div>';
  exit;
}

$sql = "SELECT u.*, r.role_ID, r.role_name, s.status_ID, s.status_name, c.class_ID, c.class_name
        FROM userdata u
        LEFT JOIN userrolesdata ur ON u.u_ID = ur.u_ID 
        LEFT JOIN roledata r ON ur.role_ID = r.role_ID
        LEFT JOIN statusdata s ON s.status_ID = u.u_status
        LEFT JOIN classdata c ON c.class_ID = u.c_ID
        WHERE u.u_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$u_ID]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  echo '<div class="alert alert-warning">找不到該使用者</div>';
  exit;
}

$roles    = $conn->query("SELECT * FROM roledata")->fetchAll(PDO::FETCH_ASSOC);
$statuses = $conn->query("SELECT * FROM statusdata")->fetchAll(PDO::FETCH_ASSOC);
$classes  = $conn->query("SELECT * FROM classdata")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4">
  <h1 class="mt-4">編輯使用者</h1>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form id="editForm" enctype="multipart/form-data">
        <input type="hidden" name="u_ID" value="<?= htmlspecialchars($user['u_ID']) ?>">

        <div class="row g-4">
          <!-- 頭像 -->
          <div class="col-12 col-md-4 text-center">
            <img id="avatarPreview"
              src="../headshot/<?= htmlspecialchars($user['u_img'] ?: 'default.jpg') ?>"
              alt="" style="width:160px;height:160px;object-fit:cover;border-radius:50%;border:4px solid rgba(0,0,0,.08);" class="mb-3 shadow-sm">
            <input type="file" name="avatar" id="avatarInput" accept="image/*" class="form-control">
            <small class="text-secondary">建議 1:1 圖片，JPG/PNG/WebP</small>
            <input type="hidden" name="clear_avatar" id="clear_avatar" value="0">
            <button type="button" class="btn btn-outline-danger" id="btnClearAvatar">清除頭貼</button>
          </div>


          <!-- 基本資料 -->
          <div class="col-12 col-md-8">
            <div class="row g-3">
              <div class="col-12 col-sm-6">
                <label class="form-label">學號/ID（唯讀）</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['u_ID']) ?>" readonly>
              </div>

              <div class="col-12 col-sm-6">
                <label class="form-label">姓名</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['u_name']) ?>">
              </div>

              <div class="col-12 col-sm-6">
                <label class="form-label">密碼（留空表示不更改）</label>
                <input type="password" name="password" class="form-control" id="pwd" placeholder="不修改就留空">
              </div>

              <div class="col-12 col-sm-6">
                <label class="form-label">信箱</label>
                <input type="email" name="gmail" class="form-control" value="<?= htmlspecialchars($user['u_gmail']) ?>">
              </div>

              <div class="col-12">
                <label class="form-label">自我介紹</label>
                <textarea name="profile" rows="3" class="form-control"><?= htmlspecialchars($user['u_profile']) ?></textarea>
              </div>

              <div class="col-12 col-sm-4">
                <label class="form-label">班級</label>
                <select name="class_id" class="form-select">
                  <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['class_ID'] ?>" <?= $c['class_ID'] == $user['c_ID'] ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($c['class_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-sm-4">
                <label class="form-label">角色</label>
                <select name="role_id" class="form-select">
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['role_ID'] ?>" <?= $r['role_ID'] == $user['role_ID'] ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($r['role_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-sm-4">
                <label class="form-label">狀態</label>
                <select name="status_id" class="form-select">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s['status_ID'] ?>" <?= $s['status_ID'] == $user['u_status'] ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($s['status_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="text-end mt-4">
          <button type="button" class="btn btn-outline-secondary me-2" id="btnCancel">取消</button>
          <button type="submit" class="btn btn-success" id="btnSave">完成修改</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // 頭像預覽
  document.getElementById('avatarInput').addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    if (f) document.getElementById('avatarPreview').src = URL.createObjectURL(f);
    // 只要重新選檔，清除旗標歸 0（以上傳為主）
    document.getElementById('clear_avatar').value = '0';
  });

  // 取消回清單（保持 SPA）
  document.getElementById('btnCancel').addEventListener('click', () => {
    location.hash = 'pages/admin_usermanage.php';
    $('#content').load('pages/admin_usermanage.php', initPageScript);
  });

  // 送出（AJAX）
  document.getElementById('editForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnSave');
    const fd = new FormData(e.target);

    try {
      btn.disabled = true;
      if (window.Swal) Swal.showLoading();

      const res = await fetch('pages/admin_updateuser.php', { method: 'POST', body: fd });
      const json = await res.json(); // 後端必須回 JSON

      if (json.ok) {
        if (window.Swal) await Swal.fire('已更新', json.msg || '資料已更新', 'success');
        location.hash = 'pages/admin_usermanage.php';
        $('#content').load('pages/admin_usermanage.php', initPageScript);
      } else {
        if (window.Swal) Swal.fire('更新失敗', json.msg || '請稍後再試', 'error');
      }
    } catch (err) {
      console.error(err);
      if (window.Swal) Swal.fire('錯誤', '伺服器回應不是 JSON 或連線錯誤', 'error');
    } finally {
      btn.disabled = false;
    }
  });

  // ✅ 清除頭貼：用正確的 input id 與預設圖路徑
  document.getElementById('btnClearAvatar').addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('clear_avatar').value = '1';
    const fi = document.getElementById('avatarInput');
    if (fi) fi.value = '';
    const img = document.getElementById('avatarPreview');
    if (img) img.src = '../headshot/default.jpg'; // 用和上面預覽一致的相對路徑
  });
</script>