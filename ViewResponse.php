<?php

namespace nova\plugin\tpl;

use nova\framework\App;
use nova\framework\cache\Cache;
use nova\framework\request\Response;
use nova\framework\request\ResponseType;
use nova\framework\request\Route;

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
    /**
     * @var bool $__use_controller_structure 是否使用控制器结构
     */
    private bool $__use_controller_structure = true;

    private string $_static_dir = ROOT_PATH . DS . "runtime" . DS . "static";
    private ?ViewResponse $response = null;


    private Cache $cache;

    public function __construct(mixed $data = '', int $code = 200, ResponseType $type = ResponseType::HTML, array $header = [])
    {
        parent::__construct($data, $code, $type, $header);
        if (!is_dir($this->_static_dir)) {
            mkdir($this->_static_dir, 0777, true);
        }
        $this->cache = new Cache();
    }

    private function getViewFile($view,$layout = false): string
    {
        if ( $this->__use_controller_structure) {
            $req = App::getInstance()->getReq();
            $controller = $req->route->controller;
            $module = $req->route->module;
            $action = $req->route->action;
            if ($view == "") $view = $action;
            if($layout){
                $view = $this->__template_dir . DS . $module  . DS . $view . ".tpl";
            }else {
                $view = $this->__template_dir . DS . $module . DS . strtolower($controller) . DS . $view . ".tpl";
            }
        } else {
            $view = $this->__template_dir . DS . $view . ".tpl";
        }
        return $view;
    }


    public function init($layout = "", $data = [], $__use_controller_structure = true, $left_delimiter = "{", $right_delimiter = "}", $__template_dir = ROOT_PATH . DS . "app" . DS . "view" . DS): void
    {
        $this->__layout = $layout;
        $this->__data = $data;
        $this->__left_delimiter = $left_delimiter;
        $this->__right_delimiter = $right_delimiter;
        $this->__template_dir = $__template_dir;
        $this->__use_controller_structure = $__use_controller_structure;
    }

    private ?ViewCompile $viewCompile = null;

    /**
     * @throws ViewException
     */
    public function asTpl(string $view = "", bool $static = false, array $data = [], array $headers = []): Response
    {
        $pjax =  App::getInstance()->getReq()->isPjax();
        $uri = Route::$uri;
        $view = $this->getViewFile($view);
        if ($static) {
            $hashCheck = true;
            if(!empty($this->__layout)){
                $file = $this->getViewFile($this->__layout,true);
                $hash = $this->cache->get($file);
                $layoutHash = md5_file($file);
                $hashCheck = $hash==$layoutHash;
            }
            if($hashCheck){
                $file = $this->checkStatic($view,$uri,$pjax);
                if($file!=null){
                    return self::asStatic($file, $headers);
                }
            }

        }

        $this->__data = array_merge($this->__data, $data);
        $this->__data["__pjax"] = $pjax;
        $result = $this->dynamicCompilation($view);



        if ($static) {
            $this->static($uri,$result,$pjax);
            if(!empty($this->__layout)) {
                $file = $this->getViewFile($this->__layout,true);
                $layoutHash = md5_file($file);
                $this->cache->set($file, $layoutHash);
            }
        }

        return self::asHtml($result, $headers);
    }



    /**
     * @throws ViewException
     */
    private function dynamicCompilation($view): string
    {
        $layout = "";
        if ($this->__layout != "") {
            $layout = $this->getViewFile($this->__layout,true);
        }

        try {
            $this->viewCompile = new ViewCompile($this->__template_dir,$view, $layout, ROOT_PATH . DS . "runtime" . DS . "view", $this->__left_delimiter, $this->__right_delimiter);

            $tplPath = $this->viewCompile->getTplName();

            $complied_file = $this->viewCompile->compile($tplPath);

            $this->__data["__template_file"] = $this->viewCompile->template_file;

            ob_end_clean();

            ob_start();

            extract($this->__data);

            include $complied_file;

            $result = ob_get_clean();
        } catch (\Exception $e) {
            throw new ViewException($e->getMessage());
        }
        return $result;
    }

    private function compile($tplFile): string
    {
        return $this->viewCompile->compile($tplFile);
    }

    function checkStatic($tpl, $uri,$pjax): ?string
    {
        $file = $this->_static_dir . DS . md5($uri) ;
        if($pjax){
            $file = $file."_pjax";
        }
        $file = $file . ".html";
        if (file_exists($file)) {
            if (filemtime($file) > filemtime($tpl)) {
                return $file;
            }
        }
        return null;
    }

    function static($uri,$result,$pjax): void
    {
        $path = $this->_static_dir . DS . md5($uri);
        if($pjax){
            $path = $path."_pjax";
        }
        $path = $path . ".html";
        file_put_contents($path, $result);
    }


}