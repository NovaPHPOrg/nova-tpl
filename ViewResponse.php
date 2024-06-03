<?php

namespace nova\plugin\tpl;

use nova\framework\App;
use nova\framework\request\Response;

class ViewResponse extends Response
{
    /**
     * @var string $__layout 模板布局
     */
    private ?string $__layout = "";
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

    private  ?ViewResponse $response = null;
    private function getViewFile($view): string
    {
        if($this->__use_controller_structure) {
            $req = App::getInstance()->getReq();
            $controller = $req->getController();
            $module = $req->getModule();
            $action = $req->getAction();
            if($view == "") $view = $action ;
            $view = $this->__template_dir . DS .  $module . DS . $controller . DS . $view.".tpl";
        }else{
            $view = $this->__template_dir . DS . $view.".tpl";
        }
        return $view;
    }



    public  function init($layout = "", $data = [],$__use_controller_structure = true, $left_delimiter= "{", $right_delimiter= "}",$__template_dir = ROOT_PATH.DS."app".DS."view".DS): void
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
    public function asTpl(string $view, array $data = [], array $headers = []): Response
    {
       $this->__data = array_merge($this->__data,$data);

       $layout = "";
       if($this->__layout != "") {
           $layout = $this->getViewFile($this->__layout);
       }
       $view = $this->getViewFile($view);

        try {
            $this->viewCompile = new ViewCompile($view,$layout,ROOT_PATH.DS."runtime".DS."view", $this->__left_delimiter, $this->__right_delimiter);

            $tplPath =  $this->viewCompile->getTplName();

            $complied_file =  $this->viewCompile->compile($tplPath);

            $this->__data["__template_file"] =  $this->viewCompile->template_file;

            ob_end_clean();

            ob_start();

            extract($this->__data);

            include $complied_file;

            $result = ob_get_clean();
        }catch (\Exception $e) {
            throw new ViewException($e->getMessage());
        }

       return self::asHtml($result, $headers);
   }

   private function compile($tplFile): string
   {
       return $this->viewCompile->compile($tplFile);
   }


}