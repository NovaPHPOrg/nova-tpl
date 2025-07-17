<?php

declare(strict_types=1);

namespace nova\plugin\tpl;

use app\Application;
use nova\framework\core\StaticRegister;

use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;

class TplHandler extends StaticRegister
{
    public static function registerInfo(): void
    {
        EventManager::addListener("route.before", function ($event, &$uri) {
            $error_title =  "";
            $error_message = "";
            $error_sub_message =   "";
            if (class_exists('\nova\plugin\cookie\Session')) {
                \nova\plugin\cookie\Session::getInstance()->start();
                $error_title =  \nova\plugin\cookie\Session::getInstance()->get("error_title");
                $error_message = \nova\plugin\cookie\Session::getInstance()->get("error_message");
                $error_sub_message =   \nova\plugin\cookie\Session::getInstance()->get("error_sub_message");
            }
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

            foreach ($map as $key => $value) {
                if (str_ends_with($uri, "/".$key)) {

                    if (empty($value["error_title"])) {
                        $value = $map["400"];
                    }

                    $viewResponse = new ViewResponse();
                    $viewResponse->init(
                        '',
                        [
                            'title' => Application::SYSTEM_NAME,
                        ],
                        "{",
                        "}",
                        ROOT_PATH.DS."nova".DS."plugin".DS."tpl".DS."error".DS,
                    );

                    if (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true') {
                        throw new AppExitException($viewResponse->asTpl("error", [
                            "error_title" => $value["error_title"],
                            "error_message" => $value["error_message"],
                            "error_sub_message" => $value["error_sub_message"],
                        ]));
                    } else {
                        throw new AppExitException($viewResponse->asTpl("layout"));
                    }
                }
            }

        });
    }
}
