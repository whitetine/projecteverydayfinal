window.mountApplyUploader = function(mountSelector) {
  const app = Vue.createApp({
    //1015update
    data() {
      // ⭐ 直接在 data() 中讀取申請人姓名，這是整個顯示的關鍵！
      const userName = (window.CURRENT_USER && window.CURRENT_USER.u_name) ? window.CURRENT_USER.u_name : '';
      return {
        selectedFileID: '',
        applyUser: userName,  // ⭐ 這行是整個顯示的關鍵！
        applyOther: '',
        previewPercent: 50,
        imagePreview: null,
        files: [],
        selectedFileUrl: ''
      };
    },

    //-------
    methods: {
      async submitForm() {
        const formEl = document.getElementById('applyForm');
        const fd = new FormData(formEl); // 自動包含檔案與文字欄位

        //1015update
        // 確保傳送 u_ID 而不是 u_name（因為 docsubdata 表的 dcsub_u_ID 需要 ID）
        const userId = (window.CURRENT_USER && window.CURRENT_USER.u_ID) ? window.CURRENT_USER.u_ID : '';
        if (userId) {
          fd.set('apply_user', userId); // 使用 u_ID
        } else if (!fd.has('apply_user')) {
          fd.append('apply_user', this.applyUser);
        }

        //----------

        try {
          const res = await fetch('pages/somefunction/upload.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (data.status === 'success') {
            Swal.fire('成功', data.message, 'success');

            //1015update
            formEl.reset();
            this.applyOther = '';
            this.imagePreview = '';
            this.selectedFileID = '';
            // 重置後重新設置申請人姓名
            if (window.CURRENT_USER && window.CURRENT_USER.u_name) {
              this.applyUser = window.CURRENT_USER.u_name;
            }
            //-----------

          } else {
            Swal.fire('失敗', data.message || '請檢查表單', 'error');
          }
        } catch (e) {
          Swal.fire('錯誤', '無法連線到伺服器', 'error');
        }
      },
      //1015update
      previewImage(e){
        const file = e.target.files[0];
        if(file){
          const reader = new FileReader();
          reader.onload = (event)=>{
            this.imagePreview = event.target.result;
          };
          reader.readAsDataURL(file);
        }
      },
      async fetchFiles() {
        try {
          const API_ROOT = location.pathname.includes('/pages/') ? '../api.php' : 'api.php';
          const res = await fetch(`${API_ROOT}?do=listActiveFiles`, { cache: 'no-store' });
          const data = await res.json();
          if (Array.isArray(data)) {
            this.files = data;
          } else if (data && Array.isArray(data.rows)) {
            this.files = data.rows;
          } else if (data && Array.isArray(data.data)) {
            this.files = data.data;
          }
        } catch (e) {
          console.error('fetchFiles error:', e);
          Swal.fire('錯誤', '無法載入文件列表', 'error');
        }
      }
    },
    watch:{
      selectedFileID(newVal){
        if(newVal){
          const file = this.files.find(f => f.file_ID == newVal);
          if (file && file.file_url) {
            this.selectedFileUrl = file.file_url;
          } else {
            this.selectedFileUrl = `templates/file_${newVal}.pdf`;
          }
        }else{
          this.selectedFileUrl = '';
        }
      }
    },
    created(){
      // 確保申請人姓名在初始化時就被設置
      const userName = (window.CURRENT_USER && window.CURRENT_USER.u_name) ? window.CURRENT_USER.u_name : '';
      if (userName) {
        this.applyUser = userName;
      }
    },
    mounted(){
      // ⭐ 強制設置申請人姓名（頁面載入時立即顯示）
      const userName = (window.CURRENT_USER && window.CURRENT_USER.u_name) ? window.CURRENT_USER.u_name : '';
      
      // 立即設置到 Vue（最重要！）
      if (userName) {
        this.applyUser = userName;
      }
      
      // 立即設置到 DOM
      const inputEl = document.getElementById('apply_user');
      if (inputEl) {
        if (userName) {
          inputEl.value = userName;
        } else if (inputEl.value) {
          // 如果 DOM 有值但 Vue 沒有，同步到 Vue
          this.applyUser = inputEl.value;
        }
      }
      
      // 使用 $nextTick 確保 DOM 更新後再次確認
      this.$nextTick(() => {
        const userName = (window.CURRENT_USER && window.CURRENT_USER.u_name) ? window.CURRENT_USER.u_name : '';
        if (userName) {
          this.applyUser = userName;
          const inputEl = document.getElementById('apply_user');
          if (inputEl) {
            inputEl.value = userName;
            // 觸發 input 事件確保 Vue v-model 同步
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }
      });
      
      // 使用 setTimeout 作為備用方案
      setTimeout(() => {
        const userName = (window.CURRENT_USER && window.CURRENT_USER.u_name) ? window.CURRENT_USER.u_name : '';
        if (userName) {
          this.applyUser = userName;
          const inputEl = document.getElementById('apply_user');
          if (inputEl) {
            inputEl.value = userName;
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }
      }, 50);
      
      this.fetchFiles();
    }

      //---------------

  });
  app.mount(mountSelector || '#app');
};

