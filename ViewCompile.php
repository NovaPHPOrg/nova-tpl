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
use RuntimeException;

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
    public function __construct($template_dir, $template_name, $template_layout, $compile_path, $leftDelimiter = '{', $rightDelimiter = '}')
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
    private function preCompileLayout(): void
    {
        if (!empty($this->template_layout)) {
            if ($this->template_name === $this->template_layout) {
                throw new ViewException("Layout can't be the same as the view file: $this->template_name");
            }
            $this->template_file = $this->template_name;
            $this->template_name = $this->template_layout;
        }
    }

    public function getTplName(): string
    {
        return $this->template_name;
    }

    public function resolvePath(string $baseDir, string $file): string
    {
        if ($this->isAbsolutePath($file)) {
            $tplFile = $file;
        } else {
            $tplFile = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), DIRECTORY_SEPARATOR);
            if (!str_ends_with($tplFile, '.tpl')) {
                $tplFile .= '.tpl';
            }
        }

        if (!is_file($tplFile)) {
            $dir = dirname($tplFile);
            $base = basename($tplFile);

            for ($i = 0; $i < 3; $i++) {
                $parent = dirname($dir);
                if ($parent === $dir || $parent === '' || $parent === '.') {
                    break;
                }
                $dir = $parent;
                $candidate = $dir . DIRECTORY_SEPARATOR . $base;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            throw new RuntimeException("Template not found within 3 parent levels: {$base}");
        }

        return $tplFile;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/') {
            return true;
        }
        return strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === '\\');
    }

    /**
     * 编译模板（若已编译且未过期则复用）。
     */
    public function compile(string $tplFile): string
    {
        $tplFile = $this->resolvePath($this->template_dir, $tplFile);

        $compileFile = $this->compile_path . DS . md5($tplFile) . '.php';

        if (!file_exists($compileFile) || filemtime($tplFile) > filemtime($compileFile)) {
            $this->compileFile($tplFile, $compileFile);
        }

        return $compileFile;
    }

    private function compileFile(string $tplFile, string $compileFile): void
    {
        $content = file_get_contents($tplFile);
        $content = $this->compileContent($content, dirname($tplFile));
        file_put_contents($compileFile, $content);
    }

    private function compileContent(string $content, string $dir): string
    {
        // 1. 必须先执行基础结构编译，让引擎先把 $node.sub 转换为 $node['sub']
        $content = $this->_compile_struct($content, $dir);

        // 2. 再执行高级组件(function/call)编译，这样就能正确解析到 $node['sub'] 数组了
        $content = $this->_compile_custom_tags($content);

        $content = $this->_compile_function($content);
        $content = '<?php declare(strict_types=1); namespace nova\plugin\tpl; if(!class_exists("' . str_replace("\\", "\\\\", ViewResponse::class) . '", false)) exit("[ Nova ] Render Error. ");?>' . $content;
        return $content;
    }
    /**
     * 编译 {function} 和 {call} 语法
     */
    private function _compile_custom_tags(string $template_data): string
    {
        $ld = preg_quote($this->left_delimiter, '/');
        $rd = preg_quote($this->right_delimiter, '/');

        // 1. 编译 {function name="renderMenu" items=[] prefix=''}
        $template_data = preg_replace_callback(
            '/' . $ld . '\s*function\s+name=(["\'])([\w_]+)\1\s*(.*?)\s*' . $rd . '/i',
            function ($matches) {
                $name = $matches[2];
                $defaults = $this->_parse_attributes($matches[3]);
                return "<?php if (!isset(\$tpl_func_{$name})) { \$tpl_func_{$name} = function(\$__params = []) use (&\$tpl_func_{$name}) { extract(array_merge({$defaults}, \$__params), EXTR_OVERWRITE); ?>";
            },
            $template_data
        );

        // 2. 编译 {/function}
        $template_data = preg_replace(
            '/' . $ld . '\s*\/function\s*' . $rd . '/i',
            '<?php }; } ?>',
            $template_data
        );

        // 3. 编译 {call name="renderMenu" items=$myItems}
        $template_data = preg_replace_callback(
            '/' . $ld . '\s*call\s+name=(["\'])([\w_]+)\1\s*(.*?)\s*' . $rd . '/i',
            function ($matches) {
                $name = $matches[2];
                $params = $this->_parse_attributes($matches[3]);
                return "<?php \$tpl_func_{$name}({$params}); ?>";
            },
            $template_data
        );

        return $template_data;
    }

    /**
     * 解析模板标签参数转为 PHP array 代码
     */
    private function _parse_attributes(string $string): string
    {
        $pattern = '/\b([-\w]+?)\s*=\s*(\$[\w"\'\]\[\-_>\$]+|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|\[\]|([->\w]+))/';
        $params = [];
        if (preg_match_all($pattern, $string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $params[] = "'{$m[1]}' => {$m[2]}";
            }
        }
        return "[" . implode(', ', $params) . "]";
    }

    /**
     * 翻译模板语法
     * @param  string $template_data
     * @return string
     */
    private function _compile_struct(string $template_data, string $dir): string
    {
        $foreach_inner_before = '<?php if(!empty($1)){ $_foreach_$3_counter = 0; $_foreach_$3_total = count($1);?>';
        $foreach_inner_after = '<?php $_foreach_$3_index = $_foreach_$3_counter;$_foreach_$3_iteration = $_foreach_$3_counter + 1;$_foreach_$3_first = ($_foreach_$3_counter == 0);$_foreach_$3_last = ($_foreach_$3_counter == $_foreach_$3_total - 1);$_foreach_$3_counter++;?>';
        $pattern_map = [
            // 1) 模板注释
            '{\*([\s\S]+?)\*}' => '<?php /* $1*/?>',
            // 2) 原始输出：{~ ... }
            '{~(.*?)}' => '<?php echo $1; ?>',
            // 3) 点语法转换：{$foo.bar} => {$foo['bar']}
            '({((?!}).)*?)(\$[\w\"\'\[\]]+?)\.(\w+)(.*?})' => '$1$3[\'$4\']$5',
            // 4) foreach 内部的 @index / @iteration / ...
            '({.*?)(\$(\w+)@(index|iteration|first|last|total))+(.*?})' => '$1$_foreach_$3_$4$5',
            // 5) 对象属性输出
            '{(\$[\w\-_]+\->[\w\-_]+)}' => '<?php echo strval($1); ?>',
            // 6) nofilter 输出
            '{(\$[\$\w\.\"\'\[\]]+?)\snofilter\s*}' => '<?php echo strval($1); ?>',
            // 7) 三元运算
            '{([\w\$\.\[\]\=\'"\s\(\)]+)\?(.*?:.*?)}' => '<?php echo strval($1?$2); ?>',
            // or语法
            '{([\s\S]+?)\s+or\s+([\s\S]+?)}' => '<?php echo strval($1 ?? $2); ?>',
            // 8) 赋值语句
            '{(\$[\$\w\"\'\[\]]+?)\s*=(.*?)\s*}' => '<?php $1=$2; ?>',
            // 10) 默认安全输出
            '{(\$[\$\w\.\"\'\[\]]+?)\s*}' => '<?php echo htmlspecialchars(strval($1), ENT_QUOTES, "UTF-8"); ?>',
            // 11) while 结构
            '{while\s*(.+?)}' => '<?php while ($1) : ?>',

            '{\/while}' => '<?php endwhile; ?>',
            // 12) if/elseif/else 结构
            '{if\s*(.+?)}' => '<?php if ($1) : ?>',

            '{else\s*if\s*(.+?)}' => '<?php elseif ($1) : ?>',
            '{else}' => '<?php else : ?>',
            '{\/if}' => '<?php endif; ?>',
            // 13) break / continue
            '{break}' => '<?php break; ?>',
            '{continue}' => '<?php continue; ?>',
            // 14) foreach 结构

            '{foreach\s*(\$[\$\w\.\"\'\[\]]+?)\s*as(\s*)\$([\w\"\'\[\]]+?)}' => $foreach_inner_before . '<?php foreach( $1 as $$3 ) : ?>' . $foreach_inner_after,
            '{foreach\s*(\$[\$\w\.\"\'\[\]_]+?)\s*as\s*(\$[\w\"\'\[\]]+?)\s*=>\s*\$([\w\"\'\[\]]+?)}' => $foreach_inner_before . '<?php foreach( $1 as $2 => $$3 ) : ?>' . $foreach_inner_after,
            '{\/foreach}' => '<?php endforeach; }?>',
            // 15) include 语法
            '{include\s*file=(.+?)}' => '<?php include $this->compile($this->viewCompile->resolvePath(\'' . addslashes($dir) . '\', $1)); ?>',
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
     * @param  string               $template_data
     * @return string|string[]|null
     */
    private function _compile_function(string $template_data): array|string|null
    {
        $pattern = '/' . $this->left_delimiter . '(\w+)\s*(.*?)' . $this->right_delimiter . '/';
        return preg_replace_callback($pattern, [$this, '_compile_function_callback'], $template_data);
    }

    /**
     * 函数回调
     * @param                       $matches
     * @return string|string[]|null
     */
    private function _compile_function_callback($matches): array|string|null
    {

        if (empty($matches[2])) {
            return '<?php echo ' . $matches[1] . '();?>';
        }

        if ($matches[1] !== "unset") {
            $replace = '<?php echo ' . $matches[1] . '($1);?>';
        } else {
            $replace = '<?php  ' . $matches[1] . '($1);?>';
        }
        $sync = preg_replace('/\((.*)\)\s*$/', $replace, $matches[2], -1, $count);
        if ($count) {
            return $sync;
        }

        $params = $this->_parse_attributes($matches[2]);
        if ($params !== "[]") {
            return '<?php echo ' . $matches[1] . '(' . $params . ');?>';
        }
        return "";
    }

}
