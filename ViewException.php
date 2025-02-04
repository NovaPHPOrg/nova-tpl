<?php
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

    function getTpl():string
    {
        return $this->tpl;
    }
}