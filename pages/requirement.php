<!-- 
 get_cohort
 get_req_ch
  -->
<?php
session_start();
?>
<link rel="stylesheet" href="css/file_manage.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/group_manage.css?v=<?= time() ?>">
<style>
    input[type="color"].form-control {
        height: calc(2.5rem + 2px);
        /* 設定input:color高度 ， 跟 Bootstrap 5 的 input 高度一致 */
        padding: 0.25rem;
    }
</style>
<div id="req_app" class="container my-4">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-layer-group me-2" style="color: #ffc107;"></i>基本需求管理//多選操作,頁碼,屆別篩選 還沒做
        </h1>
    </div>
    <button @click="new_progress_all_show" class="btn btn-primary">新增科上基本需求</button>
    <br><br>

    <!-- 搜尋和篩選區 --><!-- T1114抓整合過的 只改文字 -->
    <div class="card mb-4 shadow-sm filter-card">
        <div class="card-header filter-header">
            <i class="fa-solid fa-filter me-2"></i>搜尋與篩選
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="fa-solid fa-magnifying-glass me-2"></i>搜尋標題名稱
                    </label>
                    <input type="text" class="form-control" v-model="searchText" placeholder="輸入標題名稱..."
                        @input="filter_change_req">
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fa-solid fa-toggle-on me-2"></i>狀態
                    </label>
                    <select class="form-select" v-model="statusFilter" @change="filter_change_req">
                        <option value="">全部</option>
                        <option value="1">啟用</option>
                        <option value="0">停用</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fa-solid fa-star me-2"></i>類組篩選
                    </label>
                    <select class="form-select" v-model="searchGroup" @change="filter_change_req">
                        <option value="">全部</option>
                        <option :value="i.group_ID" v-for="i in group">{{i.group_name}}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100" @click="clearFilters">
                        <i class="fa-solid fa-xmark me-2"></i>清除
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- 顯示當前基本需求設定表 -->
    <div class="groups-list-card">
        <div class="card-header">
            <h5>
                <i class="fa-solid fa-list"></i>基本需求
                <button class="btn btn-warning" v-if="!tableORcard" @click="tableORcard=!tableORcard">切換至清單顯示</button>
                <button class="btn btn-info" v-else @click="tableORcard=!tableORcard">切換至小卡顯示</button>
            </h5>
            <span class="badge-count">共 {{this.filter_allreq.length}} 筆</span>
        </div>
        <!-- 小卡顯示，若v-if不成立，該區塊不會載入 -->
        <div class="user-card-grid" style="margin-top: 20px;">
            <div class="user-card" style="cursor: pointer;" v-if="!tableORcard" v-for="(i,key) in filter_allreq">
                <!-- 頭上顯示：顯示學級 -->
                <div class="user-cohort-badge">
                    <i class="fa-solid fa-calendar-alt me-2"></i>{{i.cohort_name}}
                </div>
                <div class="user-card-header">
                    <!-- 名字 -->
                    <div class="user-info">
                        <div class="user-name-row">
                            <h3 class="user-name">{{i.req_title}}</h3>
                        </div>
                        <p class="user-id">{{i.req_direction}}</p>
                        <!-- 學號 -->
                    </div>
                </div>

                <div class="user-details">
                    <div class="detail-item">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <span class="detail-item-label">類組：</span>
                        <span class="detail-item-value">{{i.group_name}}</span>
                    </div>
                    <div class="detail-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span class="detail-item-label">分類：</span>
                        <span class="detail-item-value">{{i.type_value}}</span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <i class="fa-solid fa-info-circle"></i>
                        <span class="detail-item-label">量化目標：</span>
                        <span
                            class="detail-item-value">{{i.req_count!="[]"?JSON.parse(i.req_count)[0]+"&ensp;"+JSON.parse(i.req_count)[1]+"&ensp;"+JSON.parse(i.req_count)[2]:""}}
                        </span>
                    </div>
                    <div class="detail-item">
                        <i class="fa-solid fa-circle-check"></i>
                        <span class="detail-item-label">時間限制：</span>
                        <span class="detail-item-value" style="font-size: 0.85rem;">{{i.req_start_d}} ~ {{i.req_end_d}}
                        </span>
                    </div>
                    <div class="detail-item">
                        <i class="fa-solid fa-circle-check"></i>
                        <span class="detail-item-label">狀態：</span>
                        <span
                            :class="'badge badge-custom ' + ( i.req_status==1 ? 'badge-status-active' : 'badge-status-inactive')">
                            {{ i.req_status==1 ? '啟用中' : '已停用' }}
                        </span>
                    </div>
                </div>
                <div class="user-actions">
                    <div class="form-check user-select-checkbox">
                        <input class="form-check-input user-checkbox" type="checkbox"
                            value="<?= htmlspecialchars($user['u_ID']) ?>"
                            id="user_<?= htmlspecialchars($user['u_ID']) ?>">
                        <label class="form-check-label" for="user_<?= htmlspecialchars($user['u_ID']) ?>">
                            選擇
                        </label>
                    </div>
                    <button @click="req_edit_modal(key)" class="btn btn-primary"><i
                            class="fa-solid fa-pen-to-square me-2"></i>編輯</button>
                    <button @click="req_del(i.req_ID,0)" class="btn btn-danger" v-if="i.req_status==1"><i
                            class="fa-solid fa-toggle-off me-2"></i>停用</button>
                    <button @click="req_del(i.req_ID,1)" class="btn btn-success" v-else><i
                            class="fa-solid fa-toggle-on me-2"></i>啟用</button>
                </div>
            </div>
        </div>


        <!-- 清單顯示，若v-if不成立，該區塊不會載入 -->
        <div class="card-body" style="padding: 0;" v-if="tableORcard">
            <div class="table-responsive">
                <table class="groups-table">
                    <thead>
                        <tr>
                            <th>屆別</th>
                            <th>標題</th>
                            <th>說明</th>
                            <th>類組</th>
                            <th>分類</th>
                            <th>量化目標</th>
                            <th>起始日</th>
                            <th>截止日</th>
                            <th>顏色</th>
                            <th>創建者</th>
                            <th>創建時間</th>
                            <th>狀態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(i,key) in filter_allreq">
                            <td>{{i.cohort_name}}</td>
                            <td>{{i.req_title}}</td>
                            <td>{{i.req_direction}}</td>
                            <td>{{i.group_name}}</td>
                            <td>{{i.type_value}}</td>
                            <td>
                                {{i.req_count!="[]"?JSON.parse(i.req_count)[0]+"&emsp;"+JSON.parse(i.req_count)[1]+"&emsp;"+JSON.parse(i.req_count)[2]:""}}
                            </td>
                            <td>{{i.req_start_d}}</td>
                            <td>{{i.req_end_d}}</td>
                            <td style="display: flex;">{{i.color_hex}}
                                <div :style="'width: 23px;height:23px;border-radius:50%;background:'+i.color_hex"></div>
                            </td>
                            <td>{{i.u_name}}</td>
                            <td>{{i.req_created_d}}</td>
                            <td>{{i.req_status}}</td>
                            <td>
                                <button @click="req_edit_modal(key)" class="btn btn-primary"><i
                                        class="fa-solid fa-pen-to-square me-2"></i>編輯</button>
                                <button @click="req_del(i.req_ID,0)" class="btn btn-danger" v-if="i.req_status==1"><i
                                        class="fa-solid fa-toggle-off me-2"></i>停用</button>
                                <button @click="req_del(i.req_ID,1)" class="btn btn-success" v-else><i
                                        class="fa-solid fa-toggle-on me-2"></i>啟用</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- 新增科上基本需求 彈跳視窗modal -->
    <teleport to="body">
        <div class="modal fade" id="new_progress_all" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title" id="roleLabel">
                            <b>新增{{role_ID==1||role_ID==2?"科上":role_ID==4?"團隊":""}}基本需求</b>
                        </h3>
                    </div>
                    <div class="modal-body text-center">
                        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                            <span class="input-group-text"><b>選擇類組</b></span>
                            <template v-for="i in group">
                                <input type="radio" class="btn-check" :name="'btnradio'" :id="i.group_ID"
                                    autocomplete="off" :value="i.group_ID" @click="new_progress.group_ID=i.group_ID"
                                    v-model="new_progress.group_ID">
                                <label class="btn btn-outline-primary" :for="i.group_ID">{{ i.group_name }}</label>
                            </template>
                        </div>
                        <input type="hidden" v-model="form.req_ID" name="req_ID" v-if="form.req_ID">
                        <input type="hidden" v-model="new_progress.group_ID" name="ID">
                        <input type="hidden" v-model="new_progress.team_ID" name="tID">
                        <table width="100%" style="text-align: center;margin-top: 10px;">
                            <tr>
                                <td>
                                    <div class="input-group" role="group" aria-label="Basic radio toggle button group"
                                        v-if="role_ID==1||role_ID==2">
                                        <span class="input-group-text"><b>指定屆別</b></span>
                                        <select class="form-select" name="cohort" id="cohort" v-model="form.cohort_ID">
                                            <option :value="i.cohort_ID" v-for="i in cohort">{{i.cohort_name}}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group" role="group" aria-label="Basic radio toggle button group"
                                        v-if="role_ID==1||role_ID==2">
                                        <span class="input-group-text"><b>選擇分類</b></span>
                                        <select class="form-select" name="type" id="type" v-model="form.type_ID">
                                            <option :value="i.type_ID" v-for="i in type">{{i.type_value}}</option>
                                        </select>
                                        <input type="button" value="跳轉至新增分類" class="btn btn-primary" @click="go_type()">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>進度標題</b></span>
                                        <input type="text" class="form-control" name="title" id="title"
                                            v-model="form.req_title">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>進度說明</b></span>
                                        <textarea class="form-control" rows="4" name="describe" style="resize: none;"
                                            id="describe" v-model="form.req_direction"></textarea>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>開始時間</b></span>
                                        <input type="date" class="form-control" v-model="form.req_start_d" :min="today"
                                            id="startdate"
                                            @change="form.req_start_d>form.req_end_d?form.req_end_d='':''">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2"><span style="color:gray">以下資料非必填</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>結束時間</b></span>
                                        <input type="date" class="form-control" v-model="form.req_end_d"
                                            :min="form.req_start_d" id="enddate">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group" v-for="i in new_progress.count_number">
                                        <span class="input-group-text"><b>量化目標</b></span>
                                        <input type="text" class="form-control" placeholder="目標(ex:粉絲數)"
                                            :name="'count_one[]'" style="width: 25%;" v-model="form.count1">
                                        <input type="number" class="form-control" placeholder="數字" :name="'count_two[]'"
                                            min="1" v-model="form.count2">
                                        <input type="text" class="form-control" placeholder="單位(ex:人)"
                                            :name="'count_three[]'" style="width: 10%;" v-model="form.count3">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>圖表色彩</b></span>
                                        <input type="color" class="form-control" name="color" v-model="form.color_hex"
                                            style="height: auto;">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2"><span style="color:gray;">*圖表色彩：可設定學生產生甘特圖時的色彩，預設為淺黃色</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button class="btn" style="margin-right: 10px;" @click="new_progress_all_close">清除並關閉</button>
                        <input type="button" class="btn btn-primary" :value="form.req_ID?'送出編輯':'確定新增'"
                            @click="new_p_submit">
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</div>
<script>
    // 小視窗的
    function toast({ type = 'info', title = '', text = '', ms = 3000 } = {}) {
        Swal.fire({
            toast: true,
            position: 'bottom-end', // 🔹右下角
            icon: type,
            title: title,
            html: text ? `<small>${text}</small>` : '',
            timer: ms,
            timerProgressBar: true,
            showConfirmButton: false,
            allowEscapeKey: false,
            allowOutsideClick: false,
            customClass: { popup: 'my-toast' } // 套用上面 CSS 樣式
        });
    }

    if (!window.reqVueApp) {
        window.reqVueApp = Vue.createApp({
            data() {
                return {
                    role_ID: "<?= $_SESSION['role_ID']; ?>",
                    group: [],
                    team: [],
                    new_progress: {
                        group_ID: 0,
                        count_number: 1,
                    },
                    enddate: null,
                    startdate: null,
                    today: '',
                    cohort: [],
                    type: [],
                    filter_allreq: [],
                    filter_allreq: [],
                    form: {
                        count1: "",
                        count2: "",
                        count3: "",
                        req_ID: "",
                        cohort_ID: "",
                        type_ID: "",
                        req_title: "",
                        req_direction: "",
                        req_start_d: "",
                        req_end_d: "",
                        color_hex: "#FFEE66",
                        group_ID: "",
                    },
                    statusFilter: "",
                    searchText: "",
                    searchGroup: "",
                    tableORcard: true,
                }
            },
            methods: {
                get_req_ch() {
                    $.post("../modules/requirement.php?do=get_req_ch", item => {
                        this.filter_allreq = JSON.parse(item)
                        this.allreq = JSON.parse(item)
                    })
                },
                req_del(ID, number) {
                    $.post("../modules/requirement.php?do=req_del", { ID: ID, number: number }).done(() => { this.get_req_ch() })
                    toast({ type: 'success', title: '狀態已更新' });
                },
                req_edit_modal(key) {
                    this.form = this.filter_allreq[key]
                    this.new_progress.group_ID = this.form.group_ID
                    if (this.form.req_count != "[]") {
                        this.form.count1 = JSON.parse(this.form.req_count)[0]
                        this.form.count2 = JSON.parse(this.form.req_count)[1]
                        this.form.count3 = JSON.parse(this.form.req_count)[2]
                    }
                    this.enddate = this.form.req_end_d
                    this.startdate = this.form.req_start_d
                    $("#new_progress_all").modal("show")
                },
                select_group() {
                    $.post("../modules/requirement.php?do=get_all_group", item => {
                        this.group = JSON.parse(item)
                    })
                }, select_team() {
                    $.post("../modules/requirement.php?do=select_team", item => {
                        this.team = JSON.parse(item)
                    })
                },
                get_cohortANDtype() {
                    $.post("../modules/requirement.php?do=get_cohort", item => {
                        this.cohort = JSON.parse(item)
                    })
                    $.post("../modules/requirement.php?do=get_type", item => {
                        this.type = JSON.parse(item)
                    })
                }, new_progress_all_show() {
                    this.get_cohortANDtype()
                    $('#new_progress_all').modal('show')
                }, new_progress_all_close() {
                    $('#new_progress_all').modal('hide')
                    this.new_progress.count_number = 1
                    this.form = {
                        count1: "",
                        count2: "",
                        count3: "",
                        req_ID: "",
                        cohort_ID: "",
                        type_ID: "",
                        req_title: "",
                        req_direction: "",
                        req_start_d: this.today,
                        req_end_d: "",
                        color_hex: "#FFEE66",
                        group_ID: "",
                    }
                    this.new_progress.group_ID = ""
                }, new_p_submit() {//送出編輯 & 確定新增
                    if (!document.getElementById("title").value || !document.getElementById("describe").value || (!this.new_progress.group_ID && !this.new_progress.team_ID) || !document.getElementById("startdate").value || !document.getElementById("cohort").value || !document.getElementById("type").value) {
                        toast({ type: 'error', title: '送出失敗', text: '請輸入完整資料！(類組、屆別、分類、標題、說明、開始時間)' })
                    } else {
                        this.form.group_ID = this.new_progress.group_ID
                        $.post("../modules/requirement.php?do=new_progress_all", this.form)
                            .done(() => {
                                this.get_req_ch()
                                toast({ type: 'success', title: '資料已送出', text: '感謝您的填寫！' })
                                $('#new_progress_all').modal('hide')
                                this.new_progress_all_close()
                            })
                    }
                }, toggleButton() {
                    this.isPressed = !this.isPressed
                }, get_today() {//抓今天日期，給日期選擇器做最小值
                    const today = new Date();
                    const y = today.getFullYear();
                    const m = String(today.getMonth() + 1).padStart(2, '0');
                    const d = String(today.getDate()).padStart(2, '0');
                    this.today = `${y}-${m}-${d}`;
                    this.form.req_start_d = `${y}-${m}-${d}`;
                }, go_type() {
                    location.href = "main.php#pages/type.php";
                    this.new_progress_all_close()
                }, clearFilters() {
                    //篩選 清除按鈕
                    this.statusFilter = ""
                    this.searchText = ""
                    this.searchGroup = ""
                    this.filter_allreq = this.allreq
                }, filter_change_req() {
                    this.filter_allreq = this.allreq.filter(item => item.req_title.includes(this.searchText))
                    this.statusFilter != "" ? this.filter_allreq = this.filter_allreq.filter(item => item.req_status == this.statusFilter) : ""
                    this.searchGroup != "" ? this.filter_allreq = this.filter_allreq.filter(item => item.group_ID == this.searchGroup) : ""
                }
            },
            mounted() {
                this.get_req_ch()
                this.get_today()
                this.get_cohortANDtype()
                if (this.role_ID == 1 || this.role_ID == 2) {
                    this.select_group()
                } else if (this.role_ID == 4) {
                    this.select_team()
                }
            }
        }).mount("#req_app")
    }
</script>