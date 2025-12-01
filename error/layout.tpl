<!doctype html>
<html lang="zh-CN" class="mdui-theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
    <meta name="renderer" content="webkit"/>
    <title id="title">{$title}</title>
    {*MDUI JS库*}
    <link rel="preconnect" href="https://fonts.loli.net">
    <link rel="preconnect" href="https://gstatic.loli.net" crossorigin>
    <!-- 使用 font-display=swap 避免字体加载时的布局偏移 -->
    <link href="https://fonts.loli.net/css2?family=Material+Icons&family=Material+Icons+Outlined&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/static/bundle?file=
    framework/libs/mdui.css,
    framework/base.css,
    framework/utils/Loading.css,
        framework/pjax/nprogress.min.css,
    &type=css&v={$__v}">

    <style id="style">
    </style>
    <style>
        .container {
            display: flex;
            flex-direction: column; /* 子元素竖直排列 */
            justify-content: center; /* 子元素在主轴（此处为垂直方向）居中 */
            align-items: center; /* 子元素水平居中（如果需要） */
            height: 95vh; /* 或自定义高度 */
        }
    </style>
</head>

<body>

<script src="/static/bundle?file=
    framework/libs/vhcheck.min.js,
    framework/libs/mdui.global.min.js,
    framework/bootloader.js,
    framework/utils/Loading.js,
    framework/utils/Logger.js,
    framework/utils/Loader.js,
    framework/utils/Event.js,
    framework/utils/Toaster.js,
    framework/utils/Form.js,
    framework/utils/Request.js,
    framework/theme/ThemeSwitcher.js,
    framework/language/NodeUtils.js,
    framework/language/TranslateUtils.js,
    framework/language/Language.js,
framework/pjax/pjax.min.js,
framework/pjax/nprogress.js,
framework/pjax/PjaxUtils.js,
    &type=js&v={$__v}"></script>
<script>
    let level = debug ? 'debug' : 'error';
    $.logger.setLevel(level);
    $.logger.info('App is running in ' + level + ' mode');
    $.request.setBaseUrl(baseUri).setOnCode(401,()=>{
        $.toaster.error('登录已过期，请重新登录');
        setTimeout(()=>{
            window.location.href = '/login';
        },1000);
    }).setOnCode(301,(response)=>{
        window.location.href = response.data;
    });
</script>


<div class="container" id="container">

</div>


<script>
    let pjax = new PjaxUtils(true, function () {

    }, "/404");
    pjax.loadUri(window.location.pathname);
    $("[data-pjax-item]").on("click",function () {
        pjax.loadUri($(this).data("href"));
    });

</script>
<script id="script"> </script>
</body>
</html>

