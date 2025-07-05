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

use Exception;
use nova\framework\cache\Cache;

use nova\framework\core\Logger;
use function nova\framework\config;

use nova\framework\core\Context;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\framework\http\ResponseType;

use nova\framework\route\Route;

class ViewResponse extends Response
{
    /**
     * @var string $__layout 模板布局
     */
    private string $__layout = "";
    /**
     * @var array $__data 模板数据
     */
    private array $__data = [];
    /**
     * @var string $__left_delimiter 模板左定界符
     */
    private string $__left_delimiter = "{";
    /**
     * @var string $__right_delimiter 模板右定界符
     */
    private string $__right_delimiter = "}";
    /**
     * @var string $__template_dir 模板目录
     */
    private string $__template_dir = "";


    private string $_static_dir = ROOT_PATH . DS . "runtime" . DS . "static";


    public function __construct(mixed $data = '', int $code = 200, ResponseType $type = ResponseType::HTML, array $header = [])
    {
        parent::__construct($data, $code, $type, $header);
        if (!is_dir($this->_static_dir)) {
            mkdir($this->_static_dir, 0777, true);
        }
        $this->init();
    }

    /**
     * 按优先级从不同位置查找视图文件
     * @param  string        $view 视图名称
     * @return string        返回找到的第一个视图文件路径
     * @throws ViewException 当视图文件都不存在时抛出异常
     */
    private function getViewFile(string $view): string
    {
        $route = Context::instance()->request()->getRoute();

        // 初始化路径数组
        $paths = [];

        // 确保 route 存在，否则跳过模块 & 控制器级别的路径
        if ($route !== null) {
            $controller = $route->controller;
            $module = $route->module;

            // 如果 $view 为空，确保 route 不为空
            if ($view === "") {
                $view = $route->action ?? "";
            }

            // 添加高优先级路径
            $paths[] = $this->__template_dir . DS . $module . DS . strtolower($controller) . DS . $view . ".tpl";
            $paths[] = $this->__template_dir . DS . $module . DS . $view . ".tpl";
        }

        // 添加基础路径（如果 route 为空，也至少有这个路径）
        $paths[] = $this->__template_dir . DS . $view . ".tpl";

        // 查找视图文件
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // 如果所有路径都找不到，抛出异常
        throw new ViewException("视图文件 '{$view}' 不存在");
    }

    public function init($layout = "", $data = [], $left_delimiter = "{", $right_delimiter = "}", $__template_dir = ROOT_PATH . DS . "app" . DS . "view" . DS): void
    {
        $this->__layout = $layout;
        $this->__data = $data;
        $this->__left_delimiter = $left_delimiter;
        $this->__right_delimiter = $right_delimiter;
        $this->__template_dir = $__template_dir;
    }

    private ?ViewCompile $viewCompile = null;

    /**
     * @throws ViewException
     */
    public function asTpl(string $view = "", array $data = [], array $headers = []): Response
    {
        $pjax =  Context::instance()->request()->isPjax();
        $view = $this->getViewFile($view);


        $this->__data = array_merge($this->__data, $data);
        $this->__data["__pjax"] = $pjax;
        $this->__data["__debug"] = Context::instance()->isDebug();
        $this->__data["__v"] = Context::instance()->isDebug() ? time() : config("version") ?? "";
        $result = $this->dynamicCompilation($view);

        return self::asHtml($result, $headers);
    }

    /**
     * @throws ViewException
     */
    private function dynamicCompilation($view): string
    {
        $layout = "";
        if ($this->__layout != "") {
            $layout = $this->getViewFile($this->__layout);
        }

        try {
            $this->viewCompile = new ViewCompile($this->__template_dir, $view, $layout, ROOT_PATH . DS . "runtime" . DS . "view", $this->__left_delimiter, $this->__right_delimiter);

            $tplPath = $this->viewCompile->getTplName();

            $complied_file = $this->viewCompile->compile($tplPath);

            $this->__data["__template_file"] = $this->viewCompile->template_file;

            ob_start();

            extract($this->__data);

            include $complied_file;

            $result = ob_get_clean();
        } catch (Exception $e) {
            if ($e instanceof AppExitException) {
                throw $e;
            }
            throw new ViewException($e->getMessage());
        }
        return $result;
    }

    private function compile($tplFile): string
    {
        return $this->viewCompile->compile($tplFile);
    }


}
