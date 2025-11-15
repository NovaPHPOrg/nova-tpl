<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\plugin\tpl\minify;

use Exception;

use const ROOT_PATH;

/**
 * 资源压缩处理器
 * 
 * 负责压缩 HTML、CSS、JS 资源，减少传输体积
 * 
 * 优化策略：
 * - 跳过已压缩文件（.min.js, .min.css）
 * - 使用专业的压缩算法
 * - 保留重要的格式（如 pre 标签内容）
 * 
 * 注意：
 * - 该类不负责注册，由 Handler 统一注册
 * - 所有方法都是静态的，便于外部调用
 */
class NovaMinify
{
    /**
     * 处理静态文件压缩
     * 
     * 优化：使用 pathinfo 提取扩展名，性能更好
     * 
     * @param string $file 文件路径
     * @return bool true 表示已处理并输出，false 表示继续默认流程
     */
    public static function handleStaticFile(string $file): bool
    {
        // 获取文件扩展名（优化：使用 pathinfo 而非多次 str_ends_with）
        $pathInfo = pathinfo($file);
        $filename = $pathInfo['filename'] ?? '';
        $ext = strtolower($pathInfo['extension'] ?? '');
        
        // 跳过已压缩文件
        if (str_ends_with($filename, '.min')) {
            return false;
        }
        
        // 根据文件类型选择压缩方法
        switch ($ext) {
            case 'js':
                echo self::minifyJs(file_get_contents($file));
                return true;
                
            case 'css':
                echo self::minifyCss(file_get_contents($file));
                return true;
                
            case 'html':
            case 'htm':
                echo self::minifyHtml(file_get_contents($file));
                return true;
                
            default:
                return false;
        }
    }

    /**
     * 压缩 HTML 内容
     * 
     * @param string $rawInput 原始 HTML
     * @return string 压缩后的 HTML
     */
    public static function minifyHtml(string $rawInput): string
    {
        if (trim($rawInput) === "") {
            return $rawInput;
        }

        // 保存 pre 标签内容（避免破坏格式）
        $preBlocks = [];
        $input = preg_replace_callback(
            '/<pre\b[^>]*>(.*?)<\/pre>/is',
            function ($matches) use (&$preBlocks) {
                $placeholder = '<[PRE_PLACEHOLDER_' . count($preBlocks) . ']>';
                $preBlocks[] = $matches[0];
                return $placeholder;
            },
            $rawInput
        );

        // 移除 HTML 属性之间的多余空格
        $input = preg_replace_callback(
            '#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s',
            function ($matches) {
                return '<' . $matches[1] . preg_replace(
                    '#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s',
                    ' $1$2',
                    $matches[2]
                ) . $matches[3] . '>';
            },
            str_replace("\r", "", $input)
        );
        
        // 压缩内联 CSS
        if (str_contains($input, ' style=')) {
            $input = preg_replace_callback(
                '#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s',
                function ($matches) {
                    return '<' . $matches[1] . ' style=' . $matches[2] . self::minifyCss($matches[3]) . $matches[2];
                },
                $input
            );
        }

        // 压缩 <style> 标签内的 CSS
        if (str_contains($input, '</style>')) {
            $input = preg_replace_callback(
                '#<style(.*?)>(.*?)</style>#is',
                function ($matches) {
                    return '<style' . $matches[1] . '>' . self::minifyCss($matches[2]) . '</style>';
                },
                $input
            );
        }

        // 压缩 <script> 标签内的 JS
        if (str_contains($input, '</script>')) {
            $input = preg_replace_callback(
                '#<script(.*?)>(.*?)</script>#is',
                function ($matches) {
                    return '<script' . $matches[1] . '>' . self::minifyJs($matches[2]) . '</script>';
                },
                $input
            );
        }

        // HTML 压缩规则（保留注释以便理解）
        $patterns = [
            // 修复自闭合标签（img, input）
            '#<(img|input)(>| .*?>)#s',
            
            // 移除标签之间的换行和多余空格
            '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
            
            // 移除标签与文本之间的空格
            '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s',
            
            // 移除标签之间的空格（开标签+开标签，闭标签+闭标签）
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s',
            
            // 移除文本与标签之间的长空格
            '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s',
            
            // 移除空标签
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s',
            
            // 重置自闭合标签修复
            '#<(img|input)(>| .*?>)<\/\1>#s',
            
            // 清理连续的 &nbsp;
            '#(&nbsp;)&nbsp;(?![<\s])#',
            
            // 清理标签之间的 &nbsp;
            '#(?<=\>)(&nbsp;)(?=\<)#',
            
            // 移除 HTML 注释（保留 IE 条件注释）
            '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
        ];
        
        $replacements = [
            '<$1$2</$1>',         // 添加闭合标签
            '$1$2$3',             // 压缩标签间空格
            '$1$2$3',             // 压缩文本与标签空格
            '$1$2$3$4$5',         // 压缩标签之间空格
            '$1$2$3$4$5$6$7',     // 压缩长空格
            '$1$2$3',             // 移除空标签
            '<$1$2',              // 恢复自闭合标签
            '$1 ',                // 保留单个 &nbsp;
            '$1',                 // 移除标签间 &nbsp;
            ''                    // 移除注释
        ];
        
        $input = preg_replace($patterns, $replacements, $input);

        if ($input === null) {
            return $rawInput;
        }
        
        // 还原 pre 标签内容
        foreach ($preBlocks as $i => $block) {
            $input = str_replace('<[PRE_PLACEHOLDER_' . $i . ']>', $block, $input);
        }

        return $input;
    }

    /**
     * 压缩 CSS 内容
     * 
     * 优化策略：
     * - 移除注释（保留 /*! 重要注释）
     * - 移除多余空格和换行
     * - 简化颜色代码（#aabbcc → #abc）
     * - 优化属性值（0.6 → .6，0px → 0）
     * - 移除空选择器
     * 
     * @param string $input 原始 CSS
     * @return string 压缩后的 CSS
     */
    public static function minifyCss(string $input): string
    {
        if (trim($input) === "") {
            return $input;
        }
        
        // 定义压缩规则
        $patterns = [
            // 移除注释（保留 /*! 开头的重要注释）
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
            
            // 移除多余空格
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            
            // 简化 padding/margin: 0 0 0 0 → 0
            '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
            
            // 修复 background-position:0 → background-position:0 0
            '#(background-position):0(?=[;\}])#si',
            
            // 简化小数：0.6 → .6
            '#(?<=[\s:,\-])0+\.(\d+)#s',
            
            // 移除字符串值的引号（保留 content 属性）
            '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
            
            // 移除 url() 中的引号
            '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
            
            // 简化 HEX 颜色代码：#aabbcc → #abc
            '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
            
            // 优化：border:none → border:0
            '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
            
            // 移除空选择器
            '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ];
        
        $replacements = [
            '$1',                  // 保留字符串和重要注释
            '$1$2$3$4$5$6$7',      // 压缩空格
            '$1',                  // 简化 padding/margin
            '$1:0 0',              // 修复 background-position
            '.$1',                 // 简化小数
            '$1$3',                // 移除字符串引号
            '$1$2$4$5',            // 移除 url 引号
            '$1$2$3',              // 简化颜色
            '$1:0',                // 优化 border
            '$1$2'                 // 移除空选择器
        ];
        
        return preg_replace($patterns, $replacements, $input);
    }

    /**
     * 压缩 JavaScript 内容
     * 
     * 使用 JsMinify 进行专业压缩
     * 
     * @param string $input 原始 JS
     * @return string 压缩后的 JS
     */
    public static function minifyJs(string $input): string
    {
        if (trim($input) === "") {
            return $input;
        }
        
        try {
            return JsMinify::minify($input);
        } catch (Exception $e) {
            // 压缩失败时返回原始内容
            return $input;
        }
    }
}
