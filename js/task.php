<?php
session_start()
?>
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
                    u_ID: "<?= $_SESSION["u_ID"] ?>",
                    all_requirement: [],
                    all_task: [],
                    all_teammumber: [],
                    now_group: {
                        ID: "",
                        name: "",
                        team_project_name: ""
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
                    now_team_ID: null,
                    req_return: false,
                }
            },
            methods: {
                get_requirement() {
                    $.post("../modules/task.php?do=get_now_group", item => {
                        this.now_group.ID = JSON.parse(item)["group_ID"]
                        this.now_group.name = JSON.parse(item)["group_name"]
                        this.now_group.team_project_name = JSON.parse(item)["team_project_name"]
                    })
                        .done(() => {
                            $.post("../modules/task.php?do=get_requirement", this.now_group, item => {
                                this.all_requirement = JSON.parse(item)
                                this.all_requirement.forEach(i => {
                                    if (i.req_count) {
                                        i.req_count = JSON.parse(i.req_count)
                                    }
                                })
                            })
                            $.post("../modules/task.php?do=get_now_teammember", this.now_group, item => {
                                this.all_teammumber = JSON.parse(item)["team_member"]
                                this.now_team_ID = JSON.parse(item)["team_ID"]
                            })
                                .done(() => {
                                    this.get_task()
                                })
                        })
                },
                get_task() {
                    $.post("../modules/task.php?do=get_task", { team_ID: this.now_team_ID }, item => {
                        this.all_task = JSON.parse(item)
                    })
                },
                // 以上=>GET，搜尋各種資料，於畫面載入時執行
                now_requirement_click(key) {
                    this.now_requirement = this.all_requirement[key]
                    $('#req_look_modal').modal('show')
                },
                now_task_click(key) {
                    this.now_task = this.all_task[key]
                    $('#task_look_modal').modal('show')
                },
                task_modal_show(type, id) {
                    if (type == "req") {
                        $('#req_look_modal').modal('hide')
                        this.form.select1 = "req"
                        this.form.select2 = id
                    } else if (type == "edit") {
                        $('#task_look_modal').modal('hide')
                        this.form = {
                            id: this.now_task.task_ID,
                            select1: (this.now_task.ms_ID ? 'miles' : this.now_task.req_ID ? 'req' : null),
                            select2: (this.now_task.ms_ID ? this.now_task.ms_ID : this.now_task.req_ID ? this.now_task.req_ID : null),
                            title: this.now_task.task_title,
                            desc: this.now_task.task_desc,
                            start_d: this.now_task.task_start_d,
                            end_d: this.now_task.task_end_d,
                            priority: this.now_task.task_priority,
                            who_task: (this.now_task.task_done_ID ?? null),
                        }
                    }
                    $('#task_modal').modal('show')
                },
                task_modal_close() {
                    $('#task_modal').modal('hide')
                    this.form = {
                        id: null,
                        select1: null,
                        select2: null,
                        title: null,
                        desc: null,
                        start_d: null,
                        end_d: null,
                        priority: 1,
                        who_task: null,
                    }
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
                task_submit(type) {
                    if (this.form.title == null) toast({ type: 'error', title: '請填寫完整資料！' })
                    else {
                        if (type == "new") {
                            $.post("../modules/task.php?do=new_task_submit", { form: this.form, now_team_ID: this.now_team_ID })
                                .done(() => {
                                    $('#task_modal').modal('hide')
                                    this.get_task()
                                    toast({ type: 'success', title: '新增成功' })
                                })
                        } else if (type == "edit") {
                            $.post("../modules/task.php?do=edit_task_submit", { form: this.form, id: this.now_task.task_ID, now_team_ID: this.now_team_ID })
                                .done(() => {
                                    $('#task_modal').modal('hide')
                                    this.get_task()
                                    toast({ type: 'success', title: '編輯成功' })
                                })
                        }
                    }
                },
                take_task(status) {
                    $.post("../modules/task.php?do=take_task", { id: this.now_task.task_ID, status: status })
                        .done(() => {
                            $('#task_look_modal').modal('hide')
                            this.get_task()
                            // 🔹 這裡原本是 =（指派），會有 bug，幫你改成 === 比較
                            if (status === 1) {
                                toast({ type: 'success', title: '接下任務囉！' })
                            } else if (status === 0) {
                                toast({ type: 'success', title: '已放棄該任務' })
                            } else if (status === 3) {
                                toast({ type: 'success', title: '恭喜完成任務！' })
                            }
                        })
                },
                req_return_click() {

                    // this.req_return = true
                    // this.return_form.count1 = this.now_requirement.req_count[0]
                    // this.return_form.count3 = this.now_requirement.req_count[2]
                },
                req_return_submit() {
                    if (!this.return_form.rp_remark || (this.return_form.count1 && !this.return_form.count2)) {
                        toast({ type: 'error', title: '請完整填寫回報！' })
                    } else {
                        $.post("../modules/task.php?do=req_return_submit", { form: this.return_form, now_team_ID: this.now_team_ID, req_ID: this.now_requirement.req_ID })
                            .done(() => {
                                $('#req_look_modal').modal('hide')
                                toast({ type: 'success', title: '送出成功，等待審核！' })
                            })
                    }
                }
            },
            mounted() {
                this.get_requirement();
            }
        }).mount("#task_app");
    }