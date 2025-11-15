<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\plugin\tpl\handler;

use app\Application;
use nova\framework\core\Context;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\plugin\tpl\ViewResponse;

use const DS;
use const ROOT_PATH;

/**
 * 错误页面处理器
 * 
 * 负责渲染错误页面（400, 404, 500等），支持：
 * - 标准HTTP错误码页面
 * - 自定义错误信息
 * - PJAX 局部刷新
 */
class TplHandler
{
    /**
     * HTTP状态码与错误信息的映射
     */
    private const array ERROR_MAP = [
            "400" => [
                "error_title" => "400 Bad Request",
                "error_message" => "错误的请求",
                "error_sub_message" => "抱歉，您的请求有误，请检查后重试。",
            ],
            "401" => [
                "error_title" => "401 Unauthorized",
                "error_message" => "未授权访问",
                "error_sub_message" => "抱歉，您需要登录后才能访问该页面。",
            ],
            "403" => [
                "error_title" => "403 Forbidden",
                "error_message" => "访问被禁止",
                "error_sub_message" => "抱歉，您未获得访问该页面的权限。",
            ],
            "404" => [
                "error_title" => "404 Not Found",
                "error_message" => "页面未找到",
                "error_sub_message" => "抱歉，您访问的页面不存在或已被删除。",
            ],
            "405" => [
                "error_title" => "405 Method Not Allowed",
                "error_message" => "请求方法不被允许",
                "error_sub_message" => "抱歉，您请求的方式不被服务器支持。",
            ],
            "500" => [
                "error_title" => "500 Internal Server Error",
                "error_message" => "服务器内部错误",
                "error_sub_message" => "抱歉，服务器遇到错误，暂时无法处理您的请求。",
            ],
            "502" => [
                "error_title" => "502 Bad Gateway",
                "error_message" => "错误的网关",
                "error_sub_message" => "抱歉，服务器收到了无效的响应，请稍后再试。",
            ],
            "503" => [
                "error_title" => "503 Service Unavailable",
                "error_message" => "服务暂不可用",
                "error_sub_message" => "抱歉，服务器目前无法处理您的请求，请稍后再试。",
            ],
            "504" => [
                "error_title" => "504 Gateway Timeout",
                "error_message" => "网关超时",
                "error_sub_message" => "抱歉，服务器请求超时，请稍后再试。",
            ],
            "error" => [
                "error_title" => "",
                "error_message" => "",
                "error_sub_message" => "",
            ]
        ];

    /**
     * 处理错误页面渲染
     * 
     * @param string $uri 请求URI
     * @return void
     * @throws AppExitException 抛出包含错误页面响应的异常
     */
    public static function handleErrorPage(string $uri): void
    {
        // 检查URI是否匹配错误状态码
        foreach (self::ERROR_MAP as $code => $errorInfo) {
            if (!str_ends_with($uri, "/{$code}")) {
                continue;
            }
            
            // 处理自定义错误信息
            if ($code === "error") {
                $errorInfo = self::getCustomErrorInfo();
            }
            
            // 渲染错误页面
            throw new AppExitException(
                self::renderErrorResponse($errorInfo)
            );
        }
    }
    
    /**
     * 获取自定义错误信息（从Session）
     * 
     * @return array 错误信息数组
     */
    private static function getCustomErrorInfo(): array
    {
        // 如果Session类不存在，返回默认400错误
        if (!class_exists('\nova\plugin\cookie\Session')) {
            return self::ERROR_MAP["400"];
        }
        
        $session = \nova\plugin\cookie\Session::getInstance();
        
        return [
            "error_title" => $session->get("error_title") ?: self::ERROR_MAP["400"]["error_title"],
            "error_message" => $session->get("error_message") ?: self::ERROR_MAP["400"]["error_message"],
            "error_sub_message" => $session->get("error_sub_message") ?: self::ERROR_MAP["400"]["error_sub_message"],
        ];
    }
    
    /**
     * 渲染错误响应
     * 
     * @param array $errorInfo 错误信息
     * @return ViewResponse 视图响应对象
     */
    private static function renderErrorResponse(array $errorInfo): Response
    {
        $viewResponse = new ViewResponse();
        $viewResponse->init(
            '',
            ['title' => Application::SYSTEM_NAME],
            "{",
            "}",
            ROOT_PATH . DS . "nova" . DS . "plugin" . DS . "tpl" . DS . "error" . DS
        );
        
        // PJAX 请求：只返回错误页面内容
        if (Context::instance()->request()->isPjax()) {
            return $viewResponse->asTpl("error", [
                "error_title" => $errorInfo["error_title"],
                "error_message" => $errorInfo["error_message"],
                "error_sub_message" => $errorInfo["error_sub_message"],
            ]);
        }
        
        // 完整页面请求：返回包含布局的页面
        return $viewResponse->asTpl("layout");
    }
}