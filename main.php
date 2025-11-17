    <?php include "head.php" ?>

    <?php
    session_start();
    include("includes/pdo.php");

    if (!isset($_SESSION['u_ID'])) {
      echo "<script>alert('請先登入!');location.href='index.php';</script>";
      exit;
    }
    
    $user_name = $_SESSION['u_name'] ?? '未登入';
    $role_name = $_SESSION['role_name'] ?? '無';
    $role_ID = $_SESSION['role_ID'] ?? null;
    $isAdmin = in_array($role_ID, [1, 2]);
    ?>
    <!DOCTYPE html>
    <html lang="zh-Hant">

    <head>
      <meta charset="UTF-8">
      <title>專題日總彙 - 首頁</title>
      <style>

      </style>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    </head>

    <body class="sb-nav-fixed <?= $isAdmin ? 'admin-mode' : 'user-mode' ?>">
      <?php include "nav.php"; ?>


      <div id="layoutSidenav">
        <div id="layoutSidenav_nav" class="<?= $isAdmin ? 'admin-sidenav-container' : '' ?>">
          <nav class="sb-sidenav accordion <?= $isAdmin ? 'sb-sidenav-dark admin-sidenav' : 'sb-sidenav-light' ?>" id="sidenavAccordion">
            <?php include "sidebar.php"; ?>
          </nav>
        </div>
        <main id="content" class="container-fluid py-4"><!-- .load() 塞子頁面 --></main>


      </div>
      <!-- 通知 Modal -->
      <div class="modal fade" id="bell_box">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">通知中心<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button></div>

            <div class="modal-body" id="notificationList">
              <div class="text-center text-muted">
                <p>載入中...</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <script>
      // 載入通知列表
      async function loadNotifications() {
          try {
              const response = await fetch('api.php?do=get_notifications');
              const notifications = await response.json();
              
              const listEl = document.getElementById('notificationList');
              if (!listEl) return;
              
              if (notifications.length === 0) {
                  listEl.innerHTML = '<p class="text-muted text-center">目前沒有通知</p>';
                  return;
              }
              
              let html = '';
              notifications.forEach(notif => {
                  const isRead = notif.is_read == 1;
                  const readClass = isRead ? 'text-muted' : '';
                  html += `
                      <div class="notification-item ${readClass}" data-msg-id="${notif.msg_ID}" style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; cursor: pointer;">
                          <div class="d-flex align-items-start">
                              <span class="me-2">📌</span>
                              <div class="flex-grow-1">
                                  <strong>${notif.msg_title || '通知'}</strong>
                                  <p class="mb-0 mt-1" style="font-size: 0.9rem;">${notif.msg_content || ''}</p>
                                  <small class="text-muted">${notif.msg_created_d ? new Date(notif.msg_created_d).toLocaleString('zh-TW') : ''}</small>
                              </div>
                          </div>
                      </div>
                  `;
              });
              
              listEl.innerHTML = html;
              
              // 點擊通知標記為已讀並自動消失
              listEl.querySelectorAll('.notification-item').forEach(item => {
                  item.addEventListener('click', async function() {
                      const msg_ID = this.dataset.msgId;
                      if (!msg_ID) return;
                      
                      // 防止重複點擊
                      if (this.classList.contains('marking-read')) return;
                      this.classList.add('marking-read');
                      
                      try {
                          const response = await fetch('api.php?do=mark_notification_read', {
                              method: 'POST',
                              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                              body: `msg_ID=${msg_ID}`
                          });
                          
                          const data = await response.json();
                          if (data.ok) {
                              // 在移除前先計算剩餘數量
                              const currentItems = listEl.querySelectorAll('.notification-item');
                              const remainingCount = Math.max(0, currentItems.length - 1);
                              
                              // 立即更新通知數量badge（在動畫開始前）
                              const badgeEl = document.getElementById('notificationCount');
                              if (badgeEl) {
                                  if (remainingCount > 0) {
                                      badgeEl.textContent = remainingCount;
                                      badgeEl.style.display = 'flex';
                                  } else {
                                      badgeEl.style.display = 'none';
                                  }
                              }
                              
                              // 添加淡出動畫
                              this.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                              this.style.opacity = '0';
                              this.style.transform = 'translateX(-20px)';
                              
                              // 動畫完成後移除元素
                              setTimeout(() => {
                                  this.remove();
                                  
                                  // 再次檢查剩餘數量（確保DOM已更新）
                                  const finalItems = listEl.querySelectorAll('.notification-item');
                                  const finalCount = finalItems.length;
                                  
                                  // 最終更新通知數量badge
                                  if (badgeEl) {
                                      if (finalCount > 0) {
                                          badgeEl.textContent = finalCount;
                                          badgeEl.style.display = 'flex';
                                      } else {
                                          badgeEl.style.display = 'none';
                                          listEl.innerHTML = '<p class="text-muted text-center">目前沒有通知</p>';
                                      }
                                  }
                                  
                                  // 從服務器更新通知數量（確保完全同步）
                                  updateNotificationCount().then(() => {
                                      // 確保badge正確顯示或隱藏
                                      if (badgeEl) {
                                          const serverCount = parseInt(badgeEl.textContent) || 0;
                                          if (serverCount === 0) {
                                              badgeEl.style.display = 'none';
                                          }
                                      }
                                  });
                              }, 300);
                          }
                      } catch (e) {
                          console.error('標記已讀失敗:', e);
                          this.classList.remove('marking-read');
                      }
                  });
              });
          } catch (error) {
              console.error('載入通知失敗:', error);
              const listEl = document.getElementById('notificationList');
              if (listEl) {
                  listEl.innerHTML = '<p class="text-danger text-center">載入通知失敗</p>';
              }
          }
      }
      
      // 更新通知數量
      async function updateNotificationCount() {
          try {
              const response = await fetch('api.php?do=get_notification_count');
              const data = await response.json();
              const count = parseInt(data.count) || 0;
              
              const badgeEl = document.getElementById('notificationCount');
              if (badgeEl) {
                  if (count > 0) {
                      badgeEl.textContent = count;
                      badgeEl.style.display = 'flex';
                  } else {
                      badgeEl.textContent = '0';
                      badgeEl.style.display = 'none';
                  }
              }
              return count;
          } catch (error) {
              console.error('更新通知數量失敗:', error);
              // 如果API失敗，隱藏badge
              const badgeEl = document.getElementById('notificationCount');
              if (badgeEl) {
                  badgeEl.style.display = 'none';
              }
              return 0;
          }
      }
      
      // 當通知modal打開時載入通知
      const bellBox = document.getElementById('bell_box');
      if (bellBox) {
          bellBox.addEventListener('show.bs.modal', function() {
              loadNotifications();
          });
      }
      
      // 頁面載入時更新通知數量
      document.addEventListener('DOMContentLoaded', function() {
          updateNotificationCount();
          // 每30秒更新一次通知數量
          setInterval(updateNotificationCount, 30000);
      });
      </script>

    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .preview-pane { width:100%; max-width:640px; margin:10px auto 0; }
  .preview-box  { margin:0 auto; }
  .preview-img  { width:100%; height:auto; object-fit:contain; border:1px solid #ddd; border-radius:8px; display:block; }
</style>


<?php include "modules/notify.php"; ?>
<!-- 再載你的 app.js（最後） -->
<script src="js/app.js"></script>

    </body>

    </html>