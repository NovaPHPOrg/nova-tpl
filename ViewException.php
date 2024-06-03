<?php
namespace nova\plugin\tpl;
use nova\framework\log\Logger;
use nova\framework\request\ControllerException;

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