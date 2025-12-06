<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\plugin\tpl;

use nova\framework\core\StaticRegister;
use nova\framework\event\EventManager;
use nova\plugin\tpl\handler\StaticHandler;
use nova\plugin\tpl\handler\TplHandler;
use nova\plugin\tpl\minify\NovaMinify;

/**
 * TPL 插件统一注册器
 * 
 * 负责注册所有 TPL 相关的处理器：
 * - TplHandler: 错误页面处理
 * - StaticHandler: 静态资源和Bundle处理
 * - NovaMinify: 资源压缩处理
 */
class Handler extends StaticRegister
{
    /**
     * 注册所有处理器
     * 
     * @return void
     */
    public static function registerInfo(): void
    {
        // 注册静态文件响应前处理（资源压缩）
        EventManager::addListener("response.static.before", function ($event, &$file) {
            return NovaMinify::handleStaticFile($file);
        });
        // 注册 HTML 响应前处理（HTML 压缩）
        EventManager::addListener("response.html.before", function ($event, &$data) {
            $data = NovaMinify::minifyHtml($data);
        });
        
        // 注册路由前处理
        EventManager::addListener("route.before", function ($event, &$uri) {
            // 处理错误页面
            TplHandler::handleErrorPage($uri);
            
            // 处理静态文件路由
            StaticHandler::handleStaticRoute($uri);
        });
    }
}