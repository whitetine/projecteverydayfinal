document.addEventListener("DOMContentLoaded", () => {
    // 檢查是否有有效的團隊 ID
    const teamId = window.TEAM_ID || (typeof TEAM_ID !== 'undefined' ? TEAM_ID : 0);
    const periodId = window.PERIOD_ID || (typeof PERIOD_ID !== 'undefined' ? PERIOD_ID : 0);
    
    if (!teamId || teamId <= 0) {
      const errorMsg = document.getElementById('error-message');
      if (errorMsg) {
        errorMsg.textContent = '錯誤：缺少團隊 ID，請從評分狀態頁面點擊「查看結果」進入';
        errorMsg.style.display = 'block';
      } else {
        alert('錯誤：缺少團隊 ID，請從評分狀態頁面點擊「查看結果」進入');
      }
      return;
    }
    
    loadDetailData(teamId, periodId);
    setupViewToggle();
  });
  
  function loadDetailData(teamId, periodId) {
    const tId = teamId || (typeof TEAM_ID !== 'undefined' ? TEAM_ID : 0);
    const pId = periodId || (typeof PERIOD_ID !== 'undefined' ? PERIOD_ID : 0);
    
    fetch(`teacher_review_detail_data.php?team_ID=${tId}&period_ID=${pId}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          const errorMsg = document.getElementById('error-message');
          if (errorMsg) {
            errorMsg.textContent = '讀取資料錯誤：' + (res.msg || '未知錯誤');
            errorMsg.style.display = 'block';
          } else {
            alert("讀取資料錯誤：" + res.msg);
          }
          return;
        }
  
        // 隱藏錯誤訊息（如果之前有顯示）
        const errorMsg = document.getElementById('error-message');
        if (errorMsg) {
          errorMsg.style.display = 'none';
        }
  
        renderBasicInfo(res);
        renderMatrix(res);
        renderAvgTable(res);
        renderNoReview(res);
      })
      .catch(err => {
        const errorMsg = document.getElementById('error-message');
        if (errorMsg) {
          errorMsg.textContent = '載入資料時發生錯誤：' + err.message;
          errorMsg.style.display = 'block';
        } else {
          alert('載入資料時發生錯誤：' + err.message);
        }
      });
  }
  
  function renderBasicInfo(d){
    document.getElementById("team-name").textContent =
      `組別：${d.teamName}（ID: ${d.teamId}）`;
  
    document.getElementById("period-info").textContent =
      `期間：${d.periodTitle}（${d.periodRange}）`;
  
    document.getElementById("back-link").href =
      `teacher_review_status.php?period_ID=${d.periodId}`;
  
    const stat = `
      <span class="badge bg-secondary me-1">學生數：${d.N}</span>
      <span class="badge bg-info text-dark me-1">
        本週已評分學生數：${countReviewer(d.didReview)}
      </span>
      <span class="badge ${d.completed ? "bg-success" : "bg-danger"}">
        ${d.completed ? "已完成" : "未完成"}
      </span>
    `;
    document.getElementById("stat-badges").innerHTML = stat;
  }
  
  function countReviewer(obj){
    let c = 0;
    Object.values(obj).forEach(v => { if(v>0) c++; });
    return c;
  }
  
  /* ---------- 矩陣 ---------- */
  function renderMatrix(d){
    const ids = d.studentIds;
    const names = d.students;
  
    let html = `
      <table class="table table-bordered table-sm table-matrix sticky-head">
      <thead>
        <tr>
          <th>評分人 \\ 被評人</th>
    `;
  
    ids.forEach(s => {
      html += `<th>${names[s]}<br><small>${s}</small></th>`;
    });
  
    html += `<th>已評數</th></tr></thead><tbody>`;
  
    ids.forEach(a => {
      html += `<tr><th class="text-start">${names[a]}<br><small>${a}</small></th>`;
  
      ids.forEach(b => {
        if (a === b) {
          html += `<td class="cell-self">—</td>`;
        } else {
          const sc = d.score[a][b] ?? "";
          const cm = d.comment[a][b] ?? "";
          html += `
            <td>
              <div class="cell-score">${sc}</div>
              <div class="cell-comment">${cm.replace(/\n/g,"<br>")}</div>
            </td>
          `;
        }
      });
  
      html += `<td><strong>${d.didReview[a]}</strong></td></tr>`;
    });
  
    html += `</tbody></table>`;
  
    document.getElementById("matrix-wrapper").innerHTML = html;
  }
  
  /* ---------- 平均分 ---------- */
  function renderAvgTable(d){
    const ids = d.studentIds;
    const names = d.students;
  
    let html = `<table class="table table-bordered table-sm w-auto">
      <thead><tr><th>學生</th><th>平均分</th><th>被評次數</th></tr></thead>
      <tbody>`;
  
    ids.forEach(s => {
      html += `
        <tr>
          <td>${names[s]}（${s}）</td>
          <td>${d.avg[s] ?? "—"}</td>
          <td>${d.recvCnt[s]}</td>
        </tr>
      `;
    });
  
    html += "</tbody></table>";
  
    document.getElementById("avg-table-wrapper").innerHTML = html;
  }
  
  /* ---------- 未完成 ---------- */
  function renderNoReview(d){
    let html = "";
    if (d.notReviewed.length === 0) {
      html = '<li class="text-muted">（無）</li>';
    } else {
      d.notReviewed.forEach(id => {
        html += `<li>${d.students[id]}（${id}）</li>`;
      });
    }
    document.getElementById("no-review-list").innerHTML = html;
  }
  
  /* ---------- 分數/評論切換 ---------- */
  function setupViewToggle(){
    const matrixWrapper = document.getElementById("matrix-wrapper");
    const btn  = document.getElementById("toggleView");
    if (!btn || !matrixWrapper) return;
    
    let mode = false;

    btn.addEventListener("click", () => {
      mode = !mode;
      matrixWrapper.classList.toggle("comment-mode", mode);
      btn.textContent = mode ? "顯示分數" : "顯示評論";
    });
  }
  