
<div id="work-form-page" data-page-id="work_form" class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">每日工作日誌</h3>
<a href="#pages/work_draft.php" data-page="work_draft" class="btn btn-outline-secondary spa-link">
  查看日誌
</a>


 <div class="card">
    <div class="card-body">
      <form action="work_save.php" method="post" id="work-main-form">
        <input type="hidden" name="work_id" value="<?= h($today['work_ID'] ?? '') ?>">

        <div class="mb-3">
          <label class="form-label">標題</label>
          <input type="text" name="work_title" class="form-control" maxlength="2000"
                 value="<?= h($today['work_title'] ?? '') ?>" <?= $readOnly?'readonly':'' ?> required>
        </div>

        <div class="mb-3">
          <label class="form-label">內容</label>
          <textarea name="work_content" class="form-control textarea-fixed" <?= $readOnly?'readonly':'' ?> required><?= h($today['work_content'] ?? '') ?></textarea>
          <div class="hint mt-1">每日僅一筆。暫存可重進修改；正式送出即結案。</div>
        </div>

        <div class="d-flex gap-2">
          <?php if(!$readOnly): ?>
            <button class="btn btn-secondary" type="submit" name="action" value="save">暫存</button>
            <button class="btn btn-primary" type="submit" name="action" value="submit">正式送出</button>
          <?php else: ?>
            <span class="badge bg-success align-self-center">今日紀錄已結案</span>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>
<!-- 掛上本頁 CSS / JS（若你的 main.js 會動態注入，可改用動態載入） -->
<link rel="stylesheet" href="css/work-form.css">
<script src="../js/work-form.js"></script>
