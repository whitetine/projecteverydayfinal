<?php
$user_name = $_SESSION['user_name'] ?? '未登入';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-light fixed-top">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <button class="border-0 me-2" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            
            <a class="navbar-brand mb-0 text-black" href="main.php">專題系統</a>
        </div>
        <div class="d-flex align-items-center">
            <form class="d-flex align-items-center gap-2 me-3 mb-0" role="search">
                <input class="form-control form-control-sm" type="search" placeholder="搜尋" aria-label="搜尋" style="max-width: 200px;">
                <button class="btn btn-lg btn btn-secondary btn-sm" type="submit">Search</button><!--btn btn-outline-light btn-sm-->
            </form>
            <div class="position-relative me-3" style="cursor:pointer;" onclick="$('#bell_box').modal('show')">
                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="notificationCount">2</span>
                <lord-icon src="https://cdn.lordicon.com/bpptgtfr.json"
                    trigger="hover"
                    colors="primary:#0000"
                    style="width:40px;height:40px">
                </lord-icon>
            </div>
          
        </div>
    </div>
</nav>