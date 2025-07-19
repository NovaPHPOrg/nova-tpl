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

use nova\framework\core\Logger;
use nova\framework\route\ControllerException;

/**
 * 视图异常类
 *
 * 用于处理模板渲染过程中发生的异常，继承自 ControllerException
 * 提供模板文件路径的记录和获取功能
 *
 * @package nova\plugin\tpl
 * @since 1.0.0
 */
class ViewException extends ControllerException
{
    /**
     * 模板文件路径
     *
     * @var string
     */
    private string $tpl = "";

    /**
     * 构造函数
     *
     * 初始化视图异常，记录模板错误信息和模板文件路径
     *
     * @param string $message 异常消息
     * @param string $tpl     模板文件路径
     */
    public function __construct(string $message = "", string $tpl = "")
    {
        parent::__construct($message, null);
        Logger::error("Tpl Error: $tpl");
        Logger::error($message);
        $this->tpl = $tpl;
    }

    /**
     * 获取模板文件路径
     *
     * @return string 模板文件路径
     */
    public function getTpl(): string
    {
        return $this->tpl;
    }
}
