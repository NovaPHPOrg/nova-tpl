<?php
namespace nova\plugin\tpl;
use nova\framework\log\Logger;

class ViewCompile
{
    private string $template_name;
    private string $compile_path;

    private string $left_delimiter = "{";
    private string $right_delimiter = "}";

    private string $template_layout = "";

    public string $template_file = "";

    public string $template_dir = "";

    /**
     * @throws ViewException
     */
    public function __construct($template_dir,$template_name,$template_layout, $compile_path, $leftDelimiter = '{', $rightDelimiter = '}')
    {
        $this->template_dir = $template_dir;
        $this->template_name = $template_name;
        $this->compile_path = $compile_path;
        if (!is_dir($this->compile_path)) {
            mkdir($this->compile_path, 0777, true);
        }
        if (!file_exists($template_name)) {
            throw new ViewException("View file not found: $template_name");
        }
        $this->left_delimiter = $leftDelimiter;
        $this->right_delimiter = $rightDelimiter;
        $this->template_layout = $template_layout;
        Logger::info("View: $template_name Layout: $template_layout compile_path: $compile_path");
        //检查编译目录是否有已经编译的文件
        $this->preCompileLayout();
    }

    /**
     * 预编译layout
     * @return void
     * @throws ViewException
     */
    private function preCompileLayout():void
    {
        if (!empty($this->template_layout)) {
            if ($this->template_name === $this->template_layout){
                throw new ViewException("Layout can't be the same as the view file: $this->template_name");
            }
            $this->template_file = $this->template_name;
            $this->template_name = $this->template_layout;
        }
    }

    public function getTplName():string
    {
        return $this->template_name;
    }


    /**
     * 编译模板
     * @param $tplFile
     * @return string
     */
    public function compile($tplFile):string
    {
        //判断tplFile是否为绝对路径
        if (!str_contains($tplFile, DS)) {
            $tplFile = $this->template_dir .DS. $tplFile ;
            if(!str_ends_with($tplFile,".tpl")){
                $tplFile = $tplFile.".tpl";
            }
        }

        $compileFile = $this->compile_path . DS . md5($tplFile) . ".php";
        if (file_exists($compileFile)) {
            if (filemtime($tplFile) > filemtime($compileFile)) {
                //如果源文件的修改时间大于编译文件的修改时间，重新编译
                $this->compileFile($tplFile,$compileFile);
            }
        }else{
            $this->compileFile($tplFile,$compileFile);
        }
        return $compileFile;
    }

    private function compileFile(string $tplFile,string $compileFile): void
    {
        $content = file_get_contents($tplFile);
        $content = $this->compileContent($content);
        file_put_contents($compileFile, $content);
    }

    private function compileContent(string $content):string
    {
        $content = $this->_compile_struct($content);
        $content = $this->_compile_function($content);
        $content = '<?php namespace nova\plugin\tpl; if(!class_exists("' . str_replace("\\", "\\\\", ViewResponse::class) . '", false)) exit("[ Nova ] Render Error. ");?>' . $content;
        return $content;
    }


    /**
     * 翻译模板语法
     * @param string $template_data
     * @return string
     */
    private function _compile_struct(string $template_data): string
    {
        $foreach_inner_before = '<?php if(!empty($1)){ $_foreach_$3_counter = 0; $_foreach_$3_total = count($1);?>';
        $foreach_inner_after = '<?php $_foreach_$3_index = $_foreach_$3_counter;$_foreach_$3_iteration = $_foreach_$3_counter + 1;$_foreach_$3_first = ($_foreach_$3_counter == 0);$_foreach_$3_last = ($_foreach_$3_counter == $_foreach_$3_total - 1);$_foreach_$3_counter++;?>';
        $pattern_map = [
            '{\*([\s\S]+?)\*}' => '<?php /* $1*/?>',
            '{~(.*?)}' => '<?php echo $1; ?>',
            '({((?!}).)*?)(\$[\w\"\'\[\]]+?)\.(\w+)(.*?})' => '$1$3[\'$4\']$5',
            '({.*?)(\$(\w+)@(index|iteration|first|last|total))+(.*?})' => '$1$_foreach_$3_$4$5',
            '{(\$[\$\w\.\"\'\[\]]+?)\snofilter\s*}' => '<?php echo $1; ?>',
            '{([\w\$\.\[\]\=\'"\s]+)\?(.*?:.*?)}' => '<?php echo $1?$2; ?>',

            '{(\$[\$\w\"\'\[\]]+?)\s*=(.*?)\s*}' => '<?php $1=$2; ?>',
            '{(\$[\$\w\.\"\'\[\]]+?)\s*}' => '<?php echo htmlspecialchars($1, ENT_QUOTES, "UTF-8"); ?>',

            '{while\s*(.+?)}' => '<?php while ($1) : ?>',
            '{\/while}' => '<?php endwhile; ?>',

            '{if\s*(.+?)}' => '<?php if ($1) : ?>',

            '{else\s*if\s*(.+?)}' => '<?php elseif ($1) : ?>',
            '{else}' => '<?php else : ?>',
            '{break}' => '<?php break; ?>',
            '{continue}' => '<?php continue; ?>',

            '{\/if}' => '<?php endif; ?>',
            '{foreach\s*(\$[\$\w\.\"\'\[\]]+?)\s*as(\s*)\$([\w\"\'\[\]]+?)}' => $foreach_inner_before . '<?php foreach( $1 as $$3 ) : ?>' . $foreach_inner_after,
            '{foreach\s*(\$[\$\w\.\"\'\[\]_]+?)\s*as\s*(\$[\w\"\'\[\]]+?)\s*=>\s*\$([\w\"\'\[\]]+?)}' => $foreach_inner_before . '<?php foreach( $1 as $2 => $$3 ) : ?>' . $foreach_inner_after,
            '{\/foreach}' => '<?php endforeach; }?>',

            '{include\s*file=(.+?)}' => '<?php include $this->compile($1); ?>',
        ];

        foreach ($pattern_map as $p => $r) {
            $pattern = '/' . str_replace(["{", "}"], [$this->left_delimiter . '\s*', '\s*' . $this->right_delimiter], $p) . '/i';
            $count = 1;
            while ($count != 0) {
                $template_data = preg_replace($pattern, $r, $template_data, -1, $count);
            }
        }

        return $template_data;
    }
    /**
     * 函数编译
     * @param string $template_data
     * @return string|string[]|null
     */
    private function _compile_function(string $template_data): array|string|null
    {
        $pattern = '/' . $this->left_delimiter . '(\w+)\s*(.*?)' . $this->right_delimiter . '/';
        return preg_replace_callback($pattern, [$this, '_compile_function_callback'], $template_data);
    }


    /**
     * 函数回调
     * @param $matches
     * @return string|string[]|null
     */
    private function _compile_function_callback($matches): array|string|null
    {

        if (empty($matches[2])) return '<?php echo ' . $matches[1] . '();?>';

        if ($matches[1] !== "unset") {
            $replace = '<?php echo ' . $matches[1] . '($1);?>';
        } else {
            $replace = '<?php  ' . $matches[1] . '($1);?>';
        }
        $sync = preg_replace('/\((.*)\)\s*$/', $replace, $matches[2], -1, $count);
        if ($count) return $sync;

        $pattern_inner = '/\b([-\w]+?)\s*=\s*(\$[\w"\'\]\[\-_>\$]+|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|([->\w]+))\s*?/';
        if (preg_match_all($pattern_inner, $matches[2], $matches_inner, PREG_SET_ORDER)) {
            $params = "array(";
            foreach ($matches_inner as $m) $params .= '\'' . $m[1] . "'=>" . $m[2] . ", ";
            $params .= ")";
            return '<?php echo ' . $matches[1] . '(' . $params . ');?>';
        }
        return "";
    }

}