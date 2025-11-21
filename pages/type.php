<link rel="stylesheet" href="css/group_manage.css?v=<?= time() ?>">

<div class="group-management-container" id="type_app">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-layer-group me-2" style="color: #ffc107;"></i>分類管理
        </h1>
    </div>

    <!-- 新增區塊 -->
    <div class="add-group-card">
        <div class="card-header">
            <h5>
                <i class="fa-solid fa-plus-circle"></i>新增分類
            </h5>
        </div>
        <div class="card-body">
            <form id="addForm" method="post" action="api.php?do=add_group" class="add-group-form">
                <input type="text" name="group_name" id="group_name" class="form-control add-group-input"
                    placeholder="輸入分類名稱..." required autocomplete="off" v-model="type_name">
                <button type="button" class="btn btn-add-group" @click="type_new_submit()">
                    <i class="fa-solid fa-plus me-2"></i>新增
                </button>
            </form>
        </div>
    </div>


    <!-- 分類清單 -->
    <div class="groups-list-card">
        <div class="card-header">
            <h5>
                <i class="fa-solid fa-list"></i>分類清單
            </h5>
            <span class="badge-count">共 {{ all_type.length }} 筆</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="groups-table">
                    <thead>
                        <tr>
                            <th>創建時間</th>
                            <th>名稱</th>
                            <th>狀態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(i, key) in all_type">
                            <td>{{ i.type_created_d }}</td>
                            <td>{{ i.type_value }}</td>
                            <td>
                                <span :class="'status-badge'+ (i.type_status==1 ? ' active' : ' inactive')">
                                    {{ i.type_status == 1 ? '啟用' : '停用' }}
                                </span>
                            </td>
                            <td>
                                <button @click="type_stop(i.type_ID,0)" class="btn btn-danger" v-if="i.type_status==1"><i
                                        class="fa-solid fa-toggle-off me-2"></i>停用</button>
                                <button @click="type_stop(i.type_ID,1)" class="btn btn-success" v-else><i
                                        class="fa-solid fa-toggle-on me-2"></i>啟用</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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

    // 🔹🔹🔹 新增：每次載入這個頁面，先把舊的 typeVueApp 清掉 🔹🔹🔹
    if (window.typeVueApp && typeof window.typeVueApp.unmount === 'function') {
        try {
            window.typeVueApp.unmount();
        } catch (e) {
            console.warn('卸載 type app 時出錯:', e);
        }
    }
    // 把全域變數清成 null，好讓下面的 if (!window.typeVueApp) 一定會再跑一次
    window.typeVueApp = null;

    if (!window.typeVueApp) {
        window.typeVueApp = Vue.createApp({
            data() {
                return {
                    type_name: '',
                    all_type: []
                }
            },
            methods: {
                get_type_all() {
                    $.post("../modules/type.php?do=get_type_all", item => {
                        this.all_type = JSON.parse(item);
                    });
                },
                type_new_submit() {
                    if (!this.type_name.trim()) {
                        toast({ type: 'warning', title: '請輸入分類名稱' });
                        return;
                    } else {
                        $.post("../modules/type.php?do=type_new_submit", { type_name: this.type_name })
                            .done(() => {
                                this.get_type_all();
                                toast({ type: 'success', title: '資料已送出', text: '感謝您的填寫！' })
                                this.type_name = '';
                            })
                    }
                },
                type_stop(type_ID, status) {
                    $.post("../modules/type.php?do=type_stop", { type_ID: type_ID, type_status: status })
                        .done(() => {
                            this.get_type_all();
                            toast({ type: 'success', title: '狀態已更新' });
                        });
                }
            },
            mounted() {
                this.get_type_all();
            }
        }).mount("#type_app");
    }
</script>
