
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
<!-- <link rel="stylesheet" href="css/main.css?v=<?= time() ?>"> -->
<style>
/* 關掉 Edge/IE 內建的「顯示密碼」與清除鈕 */
#inputPassword::-ms-reveal,
#inputPassword::-ms-clear{
  display: none !important;
}

/* 兼容某些 Chromium 衍生內建的自動填按鈕（少見，但一起擋） */
#inputPassword::-webkit-credentials-auto-fill-button,
#inputPassword::-webkit-contacts-auto-fill-button{
  visibility: hidden !important;
  display: none !important;
  pointer-events: none !important;
}
</style>
<!-- <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/brite/bootstrap.min.css" rel="stylesheet"> -->
<!-- <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin@7.0.5/css/styles.css" rel="stylesheet"> -->

<!-- 你的自訂 CSS -->
<!-- <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/main.css?v=<?= time() ?>"> -->

<!-- Font Awesome：只留最新版 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- jQuery（只要一次，擇一保留 CDN） -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 bundle（含 Popper，一次就夠） -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<!-- Vue：只留一個版本（你是 Vue3 就保留這個，刪掉本地 vue.js） -->
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

<!-- 其他需要的插件 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.lordicon.com/lordicon.js"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
