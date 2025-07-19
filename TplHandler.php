<?php

declare(strict_types=1);

namespace nova\plugin\tpl;

use app\Application;
use nova\framework\core\StaticRegister;

use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;

/**
 * 模板处理器类
 *
 * 负责处理HTTP错误页面的模板渲染，包括：
 * - 监听路由前置事件
 * - 检测错误状态码
 * - 渲染对应的错误页面模板
 * - 支持PJAX请求的错误页面处理
 *
 * @author Ankio
 * @version 1.0
 * @since 2025-01-01
 */
class TplHandler extends StaticRegister
{
    /**
     * 注册模板处理器信息
     *
     * 监听路由前置事件，当检测到错误状态码时：
     * - 从Session中获取自定义错误信息
     * - 根据状态码映射对应的错误页面
     * - 渲染错误页面模板
     * - 支持PJAX和完整页面两种渲染模式
     *
     * @return void
     */
    public static function registerInfo(): void
    {
        // 监听路由前置事件，在路由处理前检查错误状态码
        EventManager::addListener("route.before", function ($event, &$uri) {
            // 初始化错误信息变量
            $error_title =  "";
            $error_message = "";
            $error_sub_message =   "";

            // 如果Session类存在，尝试从Session中获取自定义错误信息
            if (class_exists('\nova\plugin\cookie\Session')) {
                $error_title =  \nova\plugin\cookie\Session::getInstance()->get("error_title");
                $error_message = \nova\plugin\cookie\Session::getInstance()->get("error_message");
                $error_sub_message =   \nova\plugin\cookie\Session::getInstance()->get("error_sub_message");
            }

            // 定义HTTP状态码与错误信息的映射关系
            $map = [
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
                    "error_title" => $error_title,
                    "error_message" => $error_message,
                    "error_sub_message" => $error_sub_message,
                ]
            ];

            // 遍历错误映射，检查URI是否以错误状态码结尾
            foreach ($map as $key => $value) {
                if (str_ends_with($uri, "/".$key)) {

                    // 如果错误标题为空，使用400错误作为默认值
                    if (empty($value["error_title"])) {
                        $value = $map["400"];
                    }

                    // 创建视图响应对象
                    $viewResponse = new ViewResponse();
                    $viewResponse->init(
                        '', // 布局模板为空
                        [
                            'title' => Application::SYSTEM_NAME, // 设置页面标题为系统名称
                        ],
                        "{", // 模板左定界符
                        "}", // 模板右定界符
                        ROOT_PATH.DS."nova".DS."plugin".DS."tpl".DS."error".DS, // 错误模板目录
                    );

                    // 检查是否为PJAX请求
                    if (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true') {
                        // PJAX请求：只渲染错误页面内容，不包含布局
                        throw new AppExitException($viewResponse->asTpl("error", [
                            "error_title" => $value["error_title"],
                            "error_message" => $value["error_message"],
                            "error_sub_message" => $value["error_sub_message"],
                        ]));
                    } else {
                        // 完整页面请求：渲染包含布局的完整页面
                        throw new AppExitException($viewResponse->asTpl("layout"));
                    }
                }
            }

        });
    }
}
