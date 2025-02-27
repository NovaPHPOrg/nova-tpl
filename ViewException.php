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

class ViewException extends ControllerException
{
    private string $tpl = "";
    public function __construct(string $message = "", string $tpl = "")
    {
        parent::__construct($message, null);
        Logger::error("Tpl Error: $tpl");
        Logger::error($message);
        $this->tpl = $tpl;
    }

    public function getTpl(): string
    {
        return $this->tpl;
    }
}
