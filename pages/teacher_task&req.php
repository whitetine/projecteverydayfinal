<link rel="stylesheet" href="css/group_manage.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/task.css?v=<?= time() ?>">
<?php session_start(); ?>
<style>
    .filter_row {
    display: flex;
    align-items: flex-end; /* 讓 Select 與 成員文字的底對齊 */
    gap: 24px;
    margin-bottom: 16px;
}

.team-select,
.team-members {
    display: flex;
    flex-direction: column;
}

.team-select-input {
    font-size: 18px;
    width: 300px; /* 不用撐到 50%，看起來更剛好 */
}

.team-members-list {
    font-size: 18px;
    background: #f7f7f7;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
    min-width: 200px;
}

</style>
<div class="group-management-container" id="task_app">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-layer-group me-2" style="color: #ffc107;"></i>專題需求牆
        </h1>
        **期限快到時發mail提醒
        **分頁
        **查看相關連結里程碑、任務
    </div>
    <div class="filter_row">
        <div class="team-select">
            <label class="form-label">選擇小組</label>
            <select class="form-select team-select-input" v-model="now_team_ID"
                @change="get_task(); get_requirement();">
                <option :value="i.team_ID" v-for="i in all_team_ID">
                    {{ i.team_project_name }}
                </option>
            </select>
        </div>

        <div class="team-members">
            <label class="form-label">成員</label>
            <div class="team-members-list">
                {{ teamMembers.map(m => m.u_name).join('、') }}
            </div>
        </div>
    </div>

    <!-- 專題需求牆內容顯示小卡 -->
    <div id="req_wall" class="req-wall">
        <div class="req-board">
            <div class="req-board-header">
                <h2 class="req-board-title">
                    目前基本需求
                </h2>
                <span class="req-board-sub">共 {{ filtered_requirement.length }} 筆</span>
            </div>
            <div class="req-filter-row">
                <span>狀態：</span>
                <button class="req-filter-btn" :class="{ active: filter.requirement_status === '' }"
                    @click="filter.requirement_status = ''">ALL</button>

                <button class="req-filter-btn" :class="{ active: filter.requirement_status === 'notyet' }"
                    @click="filter.requirement_status = 'notyet'">未回報</button>

                <button class="req-filter-btn" :class="{ active: filter.requirement_status === 'taken' }"
                    @click="filter.requirement_status = 'taken'">審核中</button>

                <button class="req-filter-btn" :class="{ active: filter.requirement_status === 'return' }"
                    @click="filter.requirement_status = 'return'">被退件</button>

                <button class="req-filter-btn" :class="{ active: filter.requirement_status === 'done' }"
                    @click="filter.requirement_status = 'done'">已通過</button>
            </div>
            <div class="req-card-list">
                <!-- 單張需求卡片 -->
                <div class="req-card" v-for="item in filtered_requirement" :key="item.req_ID"
                    @click="now_requirement_click(item)">
                    <div class="req-color-bar" :style="{backgroundColor: item.color_hex}"></div>
                    <div class="req-card-body">
                        <div class="req-card-title-row">
                            <h3 class="req-card-title">
                                {{ item.req_title }}
                            </h3>
                            <span class="req-count-tag" class="" v-if="item.status==0">未回報</span>
                            <span class="req-count-tag" v-if="item.status==1" style="background:#F8BF63">審核中</span>
                            <span class="req-count-tag" v-if="item.status==2" style="background:#FF775C">被退件</span>
                            <span class="req-count-tag" v-if="item.status==3" style="background:#CAFCBB">已通過</span>
                        </div>
                        <p class="req-direction">
                            {{ item.req_direction }}
                        </p>

                        <div class="req-date-row">
                            <span class="req-date" v-if="item.req_start_d">
                                起：{{ item.req_start_d }}
                            </span>
                            <span class="req-date" v-if="item.req_end_d">
                                迄：{{ item.req_end_d }}
                            </span>
                        </div>
                        <div class="req-count-row">
                            <span class="req-count-label">量化目標：</span>
                            <span class="req-count-tag" v-for="j in item.req_count">
                                {{ j }}
                            </span>
                        </div>
                    </div>
                </div>
                <!-- / 單張需求卡片 -->
            </div>
        </div>
        <div class="req-board">
            <div class="req-board-header">
                <h2 class="req-board-title">
                    任務公佈欄
                </h2>
                <span class="req-board-sub">共 {{ filtered_task.length }} 筆</span>
            </div>


            <div class="req-filter-all">
                <div class="req-filter-row">
                    <span>狀態：</span>
                    <button class="req-filter-btn" :class="{ active: filter.task_filter_status === '' }"
                        @click="filter.task_filter_status = ''">ALL</button>

                    <button class="req-filter-btn" :class="{ active: filter.task_filter_status === 'notyet' }"
                        @click="filter.task_filter_status = 'notyet'">未屬名</button>

                    <button class="req-filter-btn" :class="{ active: filter.task_filter_status === 'taken' }"
                        @click="filter.task_filter_status = 'taken'">被接下</button>

                    <button class="req-filter-btn" :class="{ active: filter.task_filter_status === 'done' }"
                        @click="filter.task_filter_status = 'done'">已完成</button>
                </div>
            </div>

            <div class="req-card-list">
                <!-- 單張需求卡片 -->
                <div class="req-card" v-for="item in filtered_task" :key="item.task_ID" @click="now_task_click(item)">
                    <div class="req-color-bar"
                        :style="'background:'+(item.task_priority==1?'#FFE98A':item.task_priority==2?'#FFCC8A':item.task_priority==3?'#FF955C':'#FF2E2E')">
                    </div>
                    <div class="req-card-body">
                        <div class="req-card-title-row">
                            <h3 class="req-card-title">
                                {{ item.task_title }}
                            </h3>
                            <span class="req-count-tag" v-if="item.task_status==0">未屬名</span>
                        </div>
                        <p class="req-direction">
                            {{ item.task_desc }}
                        </p>
                        <div class="req-count-row" v-if="item.task_status!==0">
                            <p class="req-direction">
                                {{item.done_name}}
                            </p>
                            <span class="req-count-tag" v-if="item.task_status==1 && item.task_done_d"
                                style="background:#F8BF63">{{item.task_done_d+'已接下該任務'}}</span>
                            <span class="req-count-tag" v-if="item.task_status==1 && !item.task_done_d"
                                style="background:#F8BF63">{{'已被分配任務'}}</span>
                            <span class="req-count-tag" v-if="item.task_status==3"
                                style="background:#CAFCBB">{{item.task_done_d+'已完成該任務'}}</span>
                        </div>
                        <div class="req-count-row">
                            <span class="req-count-label">創立者：</span>
                            <span class="req-count-label" style="margin-right: 14px;">{{item.creator_name}}</span>
                            <span class="req-count-label">創立時間：</span>
                            <span class="req-count-label">{{ item.task_created_d }}</span>
                        </div>
                        <div class="req-date-row">
                            <span class="req-date" v-if="item.task_start_d">
                                起：{{ item.task_start_d }}
                            </span>
                            <span class="req-date" v-if="item.task_end_d">
                                迄：{{ item.task_end_d }}
                            </span>
                        </div>
                    </div>
                </div>
                <!-- / 單張需求卡片 -->
            </div>

        </div>
    </div>
    <teleport to="body">
        <!-- 任務task modal -->
        <div class="modal fade" id="task_modal" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>{{form.id?'編輯任務':'新增任務'}}</h2>
                        <i class="fa-solid fa-square-xmark ms-auto" style="font-size: 24px; cursor:pointer;"
                            @click="task_modal_close"></i>
                    </div>
                    <div class="modal-body">
                        <table>
                            <tr>
                                <td>
                                    <div class="input-group" role="group" aria-label="Basic radio toggle button group">
                                        <span class="input-group-text"><b>連結需求或里程碑：</b></span>
                                        <select class="form-select" v-model="form.select1">
                                            <option value=null>不連結</option>
                                            <option value="req">基本需求</option>
                                            <option value="miles">里程碑</option>
                                        </select>
                                        <select class="form-select" v-model="form.select2" v-if="form.select1=='req'">
                                            <option :value="i.req_ID" v-for="i in all_requirement">{{i.req_title}}
                                            </option>
                                        </select>
                                        <select class="form-select" v-model="form.select2" v-if="form.select1=='miles'">
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>任務標題</b></span>
                                        <input type="text" v-model="form.title" class="form-control" id="title">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group range-group">
                                        <span class="input-group-text"><b>誰的任務</b></span>
                                        <select class="form-select" v-model="form.who_task">
                                            <option value=null>暫不部屬</option>
                                            <option :value="i.team_u_ID"
                                                v-for="i in (all_teammumber.filter(c => c.role_ID === 6))">{{i.u_name}}
                                            </option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group range-group">
                                        <span
                                            class="input-group-text"><b>重要程度({{form.priority==1?'一般':form.priority==2?'重要':form.priority==3?'緊急':'超級緊急'}})</b></span>
                                        <input type="range" max="4" min="1" step="1" class="form-range"
                                            v-model="form.priority">
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <center><span style="color:gray">以下資料非必填</span></center>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>任務說明</b></span>
                                        <textarea class="form-control" rows="4" style="resize: none;"
                                            v-model="form.desc"></textarea>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>起始日</b></span>
                                        <input type="datetime-local" class="form-control" v-model="form.start_d">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text"><b>截止日</b></span>
                                        <input type="datetime-local" class="form-control" v-model="form.end_d">
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" @click="task_submit('edit')" v-if="form.id">送出編輯</button>
                        <button class="btn btn-primary" @click="task_submit('new')" v-else>確定新增</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- 查看基本需求look req modal -->
        <div class="modal fade" id="req_look_modal" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>{{now_requirement.req_title}}</h2>
                        <i class="fa-solid fa-square-xmark ms-auto" style="font-size: 24px; cursor:pointer;"
                            @click="req_modal_close"></i>
                    </div>
                    <div class="modal-body">
                        <div class="req-card-list">
                            <!-- 單張需求卡片 -->
                            <div class="req-card">
                                <div class="req-color-bar" :style="{backgroundColor: now_requirement.color_hex}"></div>
                                <div class="req-card-body">
                                    <p class="req-direction">
                                        {{ now_requirement.req_direction }}
                                    </p>

                                    <div class="req-date-row">
                                        <span class="req-date" v-if="now_requirement.req_start_d">
                                            起：{{ now_requirement.req_start_d }}
                                        </span>
                                        <span class="req-date" v-if="now_requirement.req_end_d">
                                            迄：{{ now_requirement.req_end_d }}
                                        </span>
                                    </div>
                                    <div class="req-count-row">
                                        <span class="req-count-label">量化目標：</span>
                                        <span class="req-count-tag" v-for="j in now_requirement.req_count">
                                            {{ j }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- / 單張需求卡片 -->
                            <!-- 回報欄位 -->
                            <!-- <table v-if="req_return">
                                <tr>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text"><b>回報說明</b></span>
                                            <textarea class="form-control" rows="4" style="resize: none;"
                                                v-model="return_form.rp_remark"></textarea>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="return_form.count1">
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text"><b>回報目標</b></span>
                                            <input type="text" class="form-control" placeholder="目標(ex:粉絲數)"
                                                :name="'count_one[]'" style="width: 25%; background-color: #ddd"
                                                v-model="return_form.count1" readonly>
                                            <input type="number" class="form-control" placeholder="數字"
                                                :name="'count_two[]'" min="0" v-model="return_form.count2">
                                            <input type="text" class="form-control" placeholder="單位(ex:人)"
                                                :name="'count_three[]'" style="width: 10%; background-color: #ddd"
                                                v-model="return_form.count3" readonly>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <center><span style="color:gray">此回報將送給指導老師審核！</span></center>
                                    </td>
                                </tr>
                            </table> -->
                        </div>
                    </div>
                    <div class="modal-footer" v-if="now_requirement.status==1">
                        <!-- v-if="!req_return" -->
                        <button class="btn btn-danger" @click="req_return_click(2)"
                            style="margin-right: 14px;">退件</button>
                        <button class="btn btn-primary" @click="req_return_click(3)">通過</button>
                    </div>
                    <!-- <div class="modal-footer" v-else>
                        <button class="btn btn-secondary" @click="this.req_return=false"
                            style="margin-right: 14px;">取消回報</button>
                        <button class="btn btn-primary" @click="req_return_submit">確定回報</button>
                    </div> -->
                </div>
            </div>
        </div>
    </teleport>
</div>
<script>
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

    // 🔹 先把舊的 taskVueApp 卸載掉，避免第二次載入頁面時抓不到 Vue
    if (window.taskVueApp && typeof window.taskVueApp.unmount === 'function') {
        try {
            window.taskVueApp.unmount();
        } catch (e) {
            console.warn('卸載 task app 時出錯:', e);
        }
    }

    window.taskVueApp = null;
    if (!window.taskVueApp) {
        window.taskVueApp = Vue.createApp({
            data() {
                return {
                    all_teammumber: [],
                    all_team_ID: [],
                    now_team_ID: 1,
                    all_requirement: [],
                    all_task: [],


                    u_ID: "<?= $_SESSION["u_ID"] ?>",
                    now_group: {
                        ID: "",
                        name: "",
                    },
                    now_requirement: [],
                    now_task: [],
                    filter: {
                        task_filter: "",
                        task_filter_status: "",
                        requirement_status: "",
                    },
                    form: {
                        id: null,
                        select1: null,
                        select2: null,
                        title: null,
                        desc: null,
                        start_d: null,
                        end_d: null,
                        priority: 1,
                        who_task: null,
                    },
                    return_form: {
                        rp_remark: null,
                        count1: null,
                        count2: null,
                        count3: null,
                    },
                    req_return: false,
                }
            },
            computed: {
                teamMembers() {
                    // 過濾 all_teammumber 中符合 team_ID 的成員
                    return this.all_teammumber.filter(i => i.team_ID == this.now_team_ID && i.role_ID == 6);
                },
                filtered_requirement() {
                    const statusFilter = this.filter.requirement_status;
                    return this.all_requirement.filter(item => {
                        switch (statusFilter) {
                            case 'notyet':   // 未回報
                                return item.status === 0;
                            case 'taken':    // 審核中
                                return item.status === 1;
                            case 'return':   // 被退件
                                return item.status === 2;
                            case 'done':     // 已通過
                                return item.status === 3;
                            default:         // '' = ALL
                                return true;
                        }
                    });
                },
                filtered_task() {
                    const mineFilter = this.filter.task_filter;              // '' or 'mine'
                    const statusFilter = this.filter.task_filter_status;     // '', 'notyet', 'taken', 'done'
                    const u_ID = this.u_ID;
                    return this.all_task.filter(item => {
                        // 1️⃣ 先處理「篩選：我的」
                        if (mineFilter === 'mine') {
                            const isCreator = item.task_u_ID === u_ID;          // 我建立的任務
                            const isTaker = item.task_done_u_ID === u_ID;     // 我接下的任務
                            if (!isCreator && !isTaker) return false;
                        }
                        // 2️⃣ 再處理狀態篩選
                        switch (statusFilter) {
                            case 'notyet':   // 未屬名
                                return item.task_status === 0;
                            case 'taken':    // 被接下
                                return item.task_status === 1;
                            case 'done':     // 已完成
                                return item.task_status === 3;
                            default:         // '' = ALL
                                return true;
                        }
                    });
                },
            },
            methods: {
                get_team() {
                    $.post("../modules/teacher_task&req.php?do=get_now_teammember", this.now_group, item => {
                        this.all_teammumber = JSON.parse(item)["team_member"]
                        this.all_team_ID = JSON.parse(item)["team_IDs"]
                    })
                },
                get_requirement() {
                    $.post("../modules/teacher_task&req.php?do=get_now_group", item => {
                        this.now_group.ID = JSON.parse(item)["group_ID"]
                        this.now_group.name = JSON.parse(item)["group_name"]
                    })
                        .done(() => {
                            $.post("../modules/teacher_task&req.php?do=get_requirement", { ID: this.now_group, now_team_ID: this.now_team_ID }, item => {
                                this.all_requirement = JSON.parse(item)
                                this.all_requirement.forEach(i => {
                                    if (i.req_count) {
                                        i.req_count = JSON.parse(i.req_count)
                                    }
                                })
                            })
                                .done(() => {
                                    this.get_task()
                                })
                        })
                },
                get_task() {
                    $.post("../modules/teacher_task&req.php?do=get_task", { team_ID: this.now_team_ID }, item => {
                        this.all_task = JSON.parse(item)
                    })
                },
                // 以上=>GET，搜尋各種資料，於畫面載入時執行
                now_requirement_click(item) {
                    this.now_requirement = item;
                    $('#req_look_modal').modal('show');
                },
                req_modal_close() {
                    this.req_return = false
                    this.return_form = {
                        rp_remark: null,
                        count1: null,
                        count2: null,
                        count3: null,
                    }
                    $('#req_look_modal').modal('hide')
                },
                req_return_click(type) {
                    $.post("../modules/teacher_task&req.php?do=req_return_click", { now_team_ID: this.now_team_ID, req_ID: this.now_requirement.req_ID, status: type })
                        .done(() => {
                            toast({ type: 'success', title: '已成功通過' })
                            $('#req_look_modal').modal('hide')
                            this.get_requirement()
                        })
                },
            },
            mounted() {
                this.get_team(),
                    this.get_requirement();
            },

        }).mount("#task_app");
    }
</script>