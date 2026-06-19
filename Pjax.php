<?php

declare(strict_types=1);

namespace nova\plugin\tpl;

use nova\framework\http\Response;

class Pjax
{
    public static function redirectTo(string $link): Response
    {

        $pjax = (
            isset($_SERVER['HTTP_X_PJAX'])
            && $_SERVER['HTTP_X_PJAX'] === 'true'
        );

        // 普通请求直接302
        if (!$pjax) {
            return Response::asRedirect($link);
        }

        // HTML上下文编码
        $htmlUrl = htmlspecialchars(
            $link,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        // JS上下文编码
        $jsUrl = json_encode(
            $link,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return Response::asHtml(
            <<<HTML
<meta http-equiv="refresh" content="0;url=$htmlUrl">
<title id="title">302 Redirect</title> 
<style id="style"></style> 
<div id="container" class="container"></div> 
<script id="script"> 
window.pageLoadFiles = []; 
window.pageOnLoad = function (loading) { 
    location.replace('$jsUrl'); 
    return false 
};
</script>
<noscript>
    <a href="{$htmlUrl}">Continue</a>
</noscript>
HTML
        );
    }
}
