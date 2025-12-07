<?php

declare(strict_types=1);

namespace nova\plugin\tpl\handler;

use nova\framework\core\Context;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\framework\http\ResponseType;
use nova\plugin\tpl\minify\NovaMinify;

use function nova\framework\config;

use function nova\framework\dump;
use const ROOT_PATH;

/**
 * 静态资源处理器类
 *
 * 负责处理静态文件路由和框架核心脚本合并：
 * - bootloader.js 添加调试和版本信息
 * - /static/ 路径下的文件路由
 * - /static/bundle 脚本合并请求
 *
 * @author Ankio
 * @version 2.0
 * @since 2025-01-01
 */
class StaticHandler
{
    
    /**
     * 处理静态文件路由
     * 
     * @param string $uri 请求URI
     * @return void
     * @throws AppExitException 返回静态文件响应
     */
    public static function handleStaticRoute(string $uri): void
    {
        // 处理 bundle 合并请求
        if (str_starts_with($uri, "/static/bundle")) {
            self::handleBundleRequest();
        }
        
        // 处理普通静态文件
        if (str_starts_with($uri, "/static/")) {
            $file = substr($uri, 8);
            $file = str_replace("..", "", $file);
            throw new AppExitException(
                Response::asStatic(ROOT_PATH . '/app/static/' . $file),
                "Send static file"
            );
        }
    }

    /**
     * 处理框架核心bundle合并请求
     * 
     * 支持通过查询参数指定要合并的文件：
     * - /static/bundle?file=file1.js,file2.js&type=js - 加载JS文件
     * - /static/bundle?file=file1.css,file2.css&type=css - 加载CSS文件
     * 
     * 参数说明：
     * - file: 逗号分隔的文件列表（必需）
     * - type: 文件类型，js 或 css（必需）
     * - v: 版本号，用于缓存控制（可选）
     * 
     * 安全限制：
     * - 所有文件必须在 /app/static/ 目录下
     * - 文件扩展名必须与 type 参数一致
     * - 不允许路径穿越（..）
     *
     * @return void
     * @throws AppExitException 返回合并后的内容
     */
    private static function handleBundleRequest(): void
    {
        $files = $_GET['file'] ?? null;
        $type = strtolower($_GET['type'] ?? 'js');
        $version = $_GET['v'] ?? '';
        // 验证 type 参数
        if (!in_array($type, ['js', 'css'], true)) {
            throw new AppExitException(
                Response::createResponse(
                    "/* Invalid type parameter. Must be 'js' or 'css' */",
                    400,
                    ResponseType::RAW,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                ),
                "Invalid type parameter"
            );
        }
        
        // 验证 file 参数
        if (empty($files)) {
            throw new AppExitException(
                Response::createResponse(
                    "/* Missing file parameter */",
                    400,
                    ResponseType::RAW,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                ),
                "Missing file parameter"
            );
        }
        
        // 解析并验证文件列表
        $fileList = self::parseAndValidateFiles($files, $type);
        
        // 设置内容类型
        $contentType = $type === 'css' ? 'text/css' : 'application/javascript';
        
        // 如果没有有效文件，返回400错误
        if (empty($fileList)) {
            throw new AppExitException(
                Response::createResponse(
                    "/* No valid files to bundle */",
                    400,
                    ResponseType::RAW,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                ),
                "No valid files"
            );
        }
        
        // 生成ETag用于缓存（包含文件列表和版本）
        // 优化：使用更快的哈希算法（xxh64比md5快3-5倍）
        $hashData = implode('|', $fileList) . $version;
        $filesHash = in_array('xxh64', hash_algos()) ? hash('xxh64', $hashData) : md5($hashData);
        $etag = '"bundle-' . $filesHash . '"';
        
        // 检查客户端缓存
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            throw new AppExitException(
                Response::createResponse(
                    '',
                    304,
                    ResponseType::NONE,
                    [
                        'ETag' => $etag,
                        'Cache-Control' => 'public, max-age=864000'
                    ]
                ),
                "Bundle cache hit"
            );
        }
        
        // 合并并压缩文件内容
        $output = self::bundleFiles($fileList, $version, $type);
        
        // 返回Bundle响应
        throw new AppExitException(
            Response::createResponse(
                $output,
                200,
                ResponseType::RAW,
                [
                    'Content-Type' => "{$contentType}; charset=utf-8",
                    'ETag' => $etag,
                    'Cache-Control' => 'public, max-age=864000'
                ]
            ),
            "Bundle sent"
        );
    }
    
    /**
     * 解析并验证文件列表
     * 
     * @param string $files 逗号分隔的文件列表
     * @param string $type 文件类型 'js' 或 'css'
     * @return array 验证通过的文件路径数组
     */
    private static function parseAndValidateFiles(string $files, string $type): array
    {
        $validFiles = [];
        $fileArray = array_map('trim', explode(',', $files));
        
        foreach ($fileArray as $file) {
            $validatedPath = self::validateStaticPath($file, $type);
            if ($validatedPath) {
                $validFiles[] = $validatedPath;
            }
        }
        
        return $validFiles;
    }
    
    /**
     * 验证静态文件路径的安全性
     * 
     * @param string $path 用户提供的路径
     * @param string $type 文件类型 'js' 或 'css'
     * @return string|null 验证后的相对路径，失败返回null
     */
    private static function validateStaticPath(string $path, string $type): ?string
    {
        // 标准化路径：移除前导斜杠和 /static/ 前缀
        $path = ltrim($path, '/');
        $path = preg_replace('#^static/#', '', $path);
        
        // 构建完整路径
        $fullPath = ROOT_PATH . '/app/static/' . $path;
        $realPath = realpath($fullPath);
        
        // realpath验证：文件必须存在且在 /static 目录下
        $staticDir = realpath(ROOT_PATH . '/app/static/');
        if (!$realPath || !str_starts_with($realPath, $staticDir . DIRECTORY_SEPARATOR)) {
            return null;
        }
        
        // 验证扩展名
        $expectedExt = '.' . $type;
        if (!str_ends_with($realPath, $expectedExt)) {
            return null;
        }
        
        // 返回相对于 /app/static/ 的路径
        return substr($realPath, strlen($staticDir) + 1);
    }
    
    /**
     * 合并并压缩文件内容
     * 
     * @param array $fileList 文件列表（相对于 /app/static/ 的路径）
     * @param string $version 版本号
     * @param string $type 文件类型 'js' 或 'css'
     * @return string 合并并压缩后的内容
     * 
     * 优化：
     * - 使用数组累积而非字符串拼接
     * - 自动压缩减少传输体积
     */
    private static function bundleFiles(array $fileList, string $version, string $type): string
    {
        // 使用数组收集输出，性能更好
        $parts = [
            "/* Framework Bundle - Auto Generated & Minified */",
            "/* Version: {$version} */",
            "/* Type: {$type} */",
            "/* Generated: " . date('Y-m-d H:i:s') . " */",
            "/* Files: " . implode(', ', array_map('basename', $fileList)) . " */\n"
        ];
        
        // CSS 专用：收集所有 @import 语句
        $cssImports = [];
        
        // 仅对 JS 文件添加运行时标记
        if ($type === 'js') {
            // 标记所有文件已加载
            $parts[] = ";if(!window.loadedResources){window.loadedResources = {};}";

            $version = Context::instance()->isDebug()?(string)time():config("version");
            $debug = Context::instance()->isDebug()?"true":"false";
            $parts[] = "\nwindow.debug = $debug;";
            $parts[] = "window.version = '$version';";
        }
        $uri = Context::instance()->request()->getBasicAddress();
        
        // 合并所有文件内容
        foreach ($fileList as $file) {
            $filePath = ROOT_PATH . '/app/static/' . $file;
            $filename = basename($file);

            $parts[] = "\n/* ========== {$filename} ========== */";
            $output = self::remove_bom(file_get_contents($filePath));
            
            // CSS：提取 @import 语句
            if ($type === 'css') {
                $output = self::extractCssImports($output, $cssImports);
            }
            
            if (!str_contains($filename, ".min")) {
                if ($type === 'js') {
                    $parts[] = NovaMinify::minifyJs($output);
                } else {
                    $parts[] = NovaMinify::minifyCss($output);
                }
            } else {
                $parts[] = $output;
            }

            $parts[] = "/* ========== End of {$filename} ========== */\n";
            if ($type == "js") $parts[] = "window.loadedResources['{$uri}/static/{$file}'] = true;";
        }
        
        // CSS：将所有 @import 插入到开头
        if ($type === 'css' && !empty($cssImports)) {
            // 在头部注释后插入所有 @import
            array_splice($parts, 5, 0, [
                "\n/* ========== Extracted @import Statements ========== */",
                implode("\n", $cssImports),
                "/* ========== End of @import Statements ========== */\n"
            ]);
        }
        
        $output = implode("\n", $parts);
        
        // 自动压缩合并后的内容
        return $output;
    }
    
    /**
     * 提取 CSS 中的 @import 语句
     * 
     * @param string $css CSS 内容
     * @param array &$imports 收集的 @import 语句（引用传递）
     * @return string 移除 @import 后的 CSS
     */
    private static function extractCssImports(string $css, array &$imports): string
    {
        // 匹配所有可能的 @import 格式：
        // @import "url";
        // @import"url";  (无空格)
        // @import 'url';
        // @import url("url");
        // @import url('url');
        $pattern = '/@import\s*(?:url\s*\(\s*)?["\']([^"\']+)["\']\s*\)?[^;]*;/i';
        
        preg_match_all($pattern, $css, $matches);
        
        // 收集所有 @import（去重）
        foreach ($matches[0] as $importStatement) {
            if (!in_array($importStatement, $imports, true)) {
                $imports[] = $importStatement;
            }
        }
        
        // 从原 CSS 中移除所有 @import
        return preg_replace($pattern, '', $css);
    }
    static function remove_bom($str) {
        if (str_starts_with($str, "\xEF\xBB\xBF")) {
            return substr($str, 3);
        }
        return $str;
    }

}

