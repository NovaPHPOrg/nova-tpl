<!doctype html>
<html lang="zh-CN" class="mdui-theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
    <meta name="renderer" content="webkit"/>
    <title id="title">{$title}</title>
    {*MDUI JS库*}
    <link rel="stylesheet" href="/static/framework/libs/mdui.css?v={$__v}">
    <link rel="stylesheet" href="/static/framework/base.css?v={$__v}">
    <script src="/static/framework/libs/mdui.global.min.js?v={$__v}"></script>
    <link rel="stylesheet" href="/static/framework/icons/fonts.css?v={$__v}">
    <link rel="stylesheet" href="/static/framework/utils/Loading.css?v={$__v}">
    <script src="/static/framework/libs/vhcheck.min.js?v={$__v}"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="/static/icons/apple-touch-icon.png?v={$__v}"/>
    <link rel="icon" type="image/png" sizes="32x32" href="/static/icons/favicon-32x32.png?v={$__v}"/>
    <link rel="icon" type="image/png" sizes="16x16" href="/static/icons/favicon-16x16.png?v={$__v}"/>
    <link rel="icon" type="image/ico" href="/static/icons/favicon.ico?v={$__v}"/>

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
<script src="/static/framework/bootloader.js?v={$__v}"></script>
<script src="/static/framework/utils/Loading.js?v={$__v}"></script>
<script src="/static/framework/utils/Logger.js?v={$__v}"></script>
<script src="/static/framework/utils/Loader.js?v={$__v}"></script>
<script src="/static/framework/utils/Event.js?v={$__v}"></script>
<script src="/static/framework/utils/Toaster.js?v={$__v}"></script>
<script src="/static/framework/utils/Request.js?v={$__v}"></script>
<script src="/static/components/theme/ThemeSwitcher.js?v={$__v}"></script>
<script src="/static/components/language/Language.js?v={$__v}"></script>
<script>
    window._v = "{$__v}"
    let level = debug ? 'debug' : 'error';
    $.logger.setLevel(level);
    $.logger.info('App is running in ' + level + ' mode');
    $.preloader([
        'Loading',
        'Logger',
        'Event',
        'Toaster',
        'Request',
        'ThemeSwitcher',
        'Language'
    ]);
    window.loading && window.loading.close();
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
    $.loader(['Pjax'], () => {
        let pjax = new PjaxUtils(true, function () {

        }, "/404");
        pjax.loadUri(window.location.pathname);
        $("[data-pjax-item]").on("click",function () {
            pjax.loadUri($(this).data("href"));
        });
    });

</script>
<script id="script"> </script>
</body>
</html>

