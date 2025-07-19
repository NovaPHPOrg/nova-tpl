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

use function nova\framework\config;

use nova\framework\core\Context;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\framework\http\ResponseType;

/**
 * 视图响应类
 *
 * 继承自 Response 类，用于处理模板视图的渲染和响应。
 * 支持模板编译、布局文件、数据传递等功能。
 *
 * @package nova\plugin\tpl
 * @author Your Name
 * @since 1.0.0
 */
class ViewResponse extends Response
{
    /**
     * 模板布局文件路径
     *
     * @var string
     */
    private string $__layout = "";

    /**
     * 传递给模板的数据数组
     *
     * @var array
     */
    private array $__data = [];

    /**
     * 模板左定界符
     *
     * @var string
     */
    private string $__left_delimiter = "{";

    /**
     * 模板右定界符
     *
     * @var string
     */
    private string $__right_delimiter = "}";

    /**
     * 模板文件目录路径
     *
     * @var string
     */
    private string $__template_dir = "";

    /**
     * 静态资源目录路径
     *
     * @var string
     */
    private string $_static_dir = ROOT_PATH . DS . "runtime" . DS . "static";

    /**
     * 构造函数
     *
     * @param mixed        $data   响应数据
     * @param int          $code   HTTP状态码
     * @param ResponseType $type   响应类型
     * @param array        $header HTTP响应头
     */
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
     *
     * 查找顺序：
     * 1. 模块/控制器/视图.tpl
     * 2. 模块/视图.tpl
     * 3. 视图.tpl
     * 4. 绝对路径视图.tpl
     *
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
        if (str_starts_with($view, ROOT_PATH . DS)) {
            $paths[] =  $view . ".tpl";
        }
        // 查找视图文件
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // 如果所有路径都找不到，抛出异常
        throw new ViewException("视图文件 '{$view}' 不存在，已查找以下位置：".join('<br> ', $paths));
    }

    /**
     * 初始化视图响应配置
     *
     * @param  string $layout          布局文件路径
     * @param  array  $data            传递给模板的数据
     * @param  string $left_delimiter  模板左定界符
     * @param  string $right_delimiter 模板右定界符
     * @param  string $__template_dir  模板目录路径
     * @return void
     */
    public function init($layout = "", $data = [], $left_delimiter = "{", $right_delimiter = "}", $__template_dir = ROOT_PATH . DS . "app" . DS . "view" . DS): void
    {
        $this->__layout = $layout;
        $this->__data = $data;
        $this->__left_delimiter = $left_delimiter;
        $this->__right_delimiter = $right_delimiter;
        $this->__template_dir = $__template_dir;
    }

    /**
     * 视图编译器实例
     *
     * @var ViewCompile|null
     */
    private ?ViewCompile $viewCompile = null;

    /**
     * 渲染模板并返回HTML响应
     *
     * @param  string        $view    视图名称
     * @param  array         $data    传递给模板的数据
     * @param  array         $headers HTTP响应头
     * @return Response      HTML响应对象
     * @throws ViewException 当模板编译失败时抛出异常
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
     * 动态编译模板
     *
     * 将模板文件编译为PHP代码并执行，返回渲染后的HTML内容。
     *
     * @param  string        $view 视图文件路径
     * @return string        渲染后的HTML内容
     * @throws ViewException 当模板编译或执行失败时抛出异常
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

    /**
     * 编译模板文件
     *
     * @param  string $tplFile 模板文件路径
     * @return string 编译后的PHP文件路径
     */
    private function compile($tplFile): string
    {
        return $this->viewCompile->compile($tplFile);
    }

}
