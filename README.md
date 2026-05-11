# TPL 插件

面向 Nova 应用的模板编译、错误页、静态资源与输出压缩。入口为 `Handler::registerInfo()`，在 `framework_start` 中由 `package.php` 注册。

## 目录结构

```
nova/plugin/tpl/
├── Handler.php              # 事件注册（插件入口）
├── handler/
│   ├── TplHandler.php       # 以 URI 后缀匹配的错误页（AppExitException）
│   └── StaticHandler.php    # /static/ 与 /static/bundle
├── minify/
│   ├── NovaMinify.php       # HTML/CSS/JS 压缩与静态直出
│   └── JsMinify.php         # JS 压缩实现（JShrink 系）
├── error/
│   ├── error.tpl            # 错误片段（PJAX 主体）
│   └── layout.tpl           # 整页布局（非 PJAX）
├── ViewResponse.php         # 视图路径解析、编译、渲染
├── ViewCompile.php          # .tpl → PHP 编译
├── ViewException.php        # 视图异常（继承 ControllerException）
└── package.php
```

---

## 模板语法

定界符默认为 `{` 与 `}`，可在 `ViewResponse::init()` 中修改。编译顺序在 `ViewCompile::compileContent()` 中固定：**先结构（`_compile_struct`）→ 再 `{function}` / `{call}`（`_compile_custom_tags`）→ 最后剩余 `{tag ...}` 走函数回调（`_compile_function`）**。

### 注释与原始输出

| 写法 | 含义 |
|------|------|
| `{* ... *}` | 模板注释，编译为 PHP 块注释 |
| `{~ expr }` | 原样 `echo`，不做 HTML 转义（注意 XSS） |

### 变量与输出

| 写法 | 含义 |
|------|------|
| `{$var}` | `htmlspecialchars(strval($var), ENT_QUOTES, "UTF-8")` |
| `{$var nofilter}` | `strval($var)`，不转义 |
| `{$obj->prop}` | 对象属性，`strval` 后输出 |
| `{$a.b}` | 转为 `$a['b']` 后再按普通变量规则参与后续匹配（多层点号依赖正则多次替换） |

### 运算符与空合并

| 写法 | 含义 |
|------|------|
| `{expr ? a : b}` | 三元，`echo strval(expr ? a : b)`（`expr` 与分支须能被当前正则正确吃掉，复杂表达式建议拆变量） |
| `{a or b}` | PHP `??`：`echo strval(a ?? b)` |

### 赋值

| 写法 | 含义 |
|------|------|
| `{$x = expr}` | `<?php $x = expr; ?>` |

### 控制流

| 写法 | 含义 |
|------|------|
| `{if cond}` / `{else if cond}` / `{else}` / `{/if}` | 标准 `if / elseif / else / endif` |
| `{while cond}` / `{/while}` | `while / endwhile` |
| `{break}` / `{continue}` | 同 PHP |
| `{foreach $arr as $item}` / `{foreach $arr as $k => $v}` / `{/foreach}` | `foreach`；循环内可用 `$item@index`、`$item@iteration`、`$item@first`、`$item@last`、`$item@total`（实现为 `$_foreach_<name>_*` 变量） |

### 包含

| 写法 | 含义 |
|------|------|
| `{include file=xxx}` | `xxx` 相对**当前模板所在目录**；内部调用 `$this->compile($dir.'/'.$xxx)`，再 `include` 编译结果 |

### 组件：`{function}` / `{call}`

定义可复用片段（实现为闭包变量 `$tpl_func_<name>`，支持递归）：

```text
{function name="blockName" items=[] prefix=""}
    {foreach $items as $item}
        {$prefix}{$item}
    {/foreach}
{/function}

{call name="blockName" items=$list prefix="-"}
```

- `name`：字母数字下划线。
- 属性列表解析为 PHP `array(...)`；`[]` 会写成 `array()`。
- `{call ...}` 会 `array_merge(get_defined_vars(), <解析出的数组>)` 再调用，以便继承外层变量。

### 其它 `{name ...}` 标签

未被上述规则消费的 `{word ...}` 会进入 `_compile_function`：

- `{foo()}` → `echo foo();`
- `{foo(...)}` → 括号内解析为 `foo` 的参数或命名参数数组，生成 `echo foo(...);`
- `{unset(...)}` → 生成 `unset(...);`（无 `echo`）

### 编译缓存与解析

- 编译输出目录：`ROOT_PATH/runtime/view/`，文件名为 `md5(模板绝对路径).php`。
- 源文件 `mtime` 新于编译文件则重新编译。
- `compile($tplFile)`：无目录分隔符的短名会补 `template_dir` 与 `.tpl`；找不到时向父目录**最多上溯 3 层**查找；仍失败抛出 `RuntimeException`。
- 编译后的 PHP 文件头部会校验 `ViewResponse` 类是否存在，防止被直接当 URL 访问执行。

### 视图路径（`ViewResponse::getViewFile`）

在 `asTpl($view, ...)` 中解析真实 `.tpl` 路径，顺序为（有路由时）：

1. `{template_dir}/{module}/{controller小写}/{view}.tpl`
2. `{template_dir}/{module}/{view}.tpl`
3. `{template_dir}/{view}.tpl`

若 `$view` 以 `ROOT_PATH/` 开头，还会尝试 `{view}.tpl`。找不到时抛出 `ViewException`，消息中带出已尝试路径。

---

## 错误处理

### HTTP 错误页：`TplHandler`

在 `route.before` 中根据 **URI 是否以 `/\{code\}` 结尾** 匹配（如 `/404`、`/500`）。命中后抛出 `AppExitException`，携带已渲染的 `Response`，中断后续路由。

| 后缀 | 行为 |
|------|------|
| `/400` … `/504` | 使用内置文案映射 `ERROR_MAP` |
| `/error` | 从 Session 读取 `error_title`、`error_message`、`error_sub_message`；若无 `nova\plugin\cookie\Session` 类则退回 400 文案 |

**PJAX**（`request()->isPjax() === true`）：直接 `asTpl('error', [...])`，返回 `error/error.tpl` 片段。

**非 PJAX**：`asTpl('layout')`，仅注入 `title` 等初始化数据；页面再通过 PJAX 加载当前 `pathname`，第二次请求通常为 PJAX，从而拿到 `error.tpl` 内容。接入方需保证 `layout.tpl` 与前端 PJAX 逻辑一致。

### 模板与渲染：`ViewException`

- `ViewCompile` 构造时视图文件不存在、或 layout 与视图为同一文件：抛出 `ViewException`。
- `ViewResponse::dynamicCompilation()` 捕获一般 `Exception` 后包装为 `ViewException`（`AppExitException` 原样向上抛出，供框架正常退出）。
- `ViewException` 构造会写错误日志；可选记录 `tpl` 路径（当前构造调用处多数只传 message，模板路径字段可能为空）。

### 静态与 Bundle

- **Bundle**：参数非法、无有效文件、类型非 `js`/`css` 时返回 **400** 纯文本说明（`ResponseType::RAW`）；`If-None-Match` 命中则 **304**。
- **普通 `/static/`**：将 URI 中 `..` 置空后映射到 `ROOT_PATH/app/static/`；**不做 `realpath` 约束**，与 bundle 的校验强度不同，部署时应避免依赖「仅剔除 `..`」作为唯一防护。

---

## 代码压缩（NovaMinify）

### 触发点（与 `Handler` 一致）

1. **`response.static.before`**：`NovaMinify::handleStaticFile($file)`  
   - 对 **单个** 静态请求的 `.js` / `.css` / `.html|.htm`：`echo` 压缩后的内容并返回 `true`（由框架决定是否短路后续输出）。  
   - `filename` 以 `.min` 结尾（即 `*.min.js` 等）跳过压缩，返回 `false`。

2. **`response.html.before`**：`$data = NovaMinify::minifyHtml($data)`  
   - 整页 HTML 在发出前压缩。

### JavaScript

- 入口：`NovaMinify::minifyJs()` → 必要时 `ensureSemicolons()` → `JsMinify::minify()`（基于 JShrink 思路的实现）。
- **跳过压缩**：空串；`isMinifiedJs()` 判定已压；含 `import`/`export` 的模块脚本（`isModernJs()`）原样返回，避免旧压缩器破坏语法。
- **失败**：`JsMinify::minify` 抛异常时捕获，回退为**仅** `ensureSemicolons($input)` 后的源码，不中断响应。

### CSS

- `minifyCss()`：多轮 `preg_replace` 去注释（保留 `/*!`）、空白、`0.6→.6`、部分颜色缩写、`border:none→0`、去空选择器等。  
- 依赖正则，极端或非标准写法可能改变语义；出问题时应先对比压缩前后。

### HTML

- `replaceVersion()`：将字面量 `[version]`、`[debug]` 替换为当前配置/调试状态（与 `Context::isDebug()`、`config('version')` 相关）。
- **`<pre>...</pre>`** 用占位符保护，避免压扁。
- 内联 `style`、`style` 块、`script` 块分别走 `minifyCss` / `minifyJs`。
- 若某步 `preg_replace` 返回 `null`，回退为压缩前字符串，避免白屏。

### Bundle 合并（`StaticHandler::bundleFiles`）

- 非 `.min` 的 JS/CSS 在合并时分别调用 `NovaMinify::minifyJs` / `minifyCss`；已带 `.min` 的文件原样拼接。
- **仅 JS bundle** 注入：`window.loadedResources`、`window.debug`、`window.version`（版本在调试模式下为时间戳）；并为每个文件写 `window.loadedResources['{uri}/static/{file}'] = true`。
- **CSS bundle**：抽取各文件中的 `@import` 到输出靠前位置，再拼接正文。

---

## 静态与 Bundle API

**普通文件**：`GET /static/{相对 app/static 的路径}`，URI 中的 `..` 会被删除。

**合并**：

```http
GET /static/bundle?file=a.js,b.js&type=js&v=1.0
GET /static/bundle?file=a.css,b.css&type=css&v=1.0
```

- `file`：逗号分隔，路径相对 `app/static`，可带或不带 `static/` 前缀；**逐项** `realpath`，必须落在 `app/static` 下且扩展名与 `type` 一致；非法项**跳过**而非整体失败。  
- 若**没有任何**有效文件 → 400。  
- 缓存：`ETag`（优先 `xxh64`，否则 `md5`），`Cache-Control: public, max-age=864000`。

---

## 事件流（当前实现）

```
route.before
  → TplHandler::handleErrorPage($uri)
  → StaticHandler::handleStaticRoute($uri)

response.static.before
  → NovaMinify::handleStaticFile($file)

response.html.before
  → NovaMinify::minifyHtml($data)
```

---

## 配置

`package.php`：

```php
return [
    "config" => [
        "framework_start" => [
            "nova\\plugin\\tpl\\Handler",
        ],
    ],
];
```

---

## 扩展与测试提示

- 新逻辑优先挂到 `EventManager` 的合适阶段，保持 `Handler.php` 只做注册。
- `ViewCompile` / `NovaMinify` 的方法多为 `public static`，便于单测；模板编译注意正则顺序导致的边界问题（复杂表达式优先在控制器中算好再传入模板）。

---

## 故障排查

| 现象 | 排查 |
|------|------|
| Bundle 400 | 是否缺少 `file`/`type`；`type` 是否仅为 `js` 或 `css`；列表是否全部校验失败 |
| 静态 404 | 文件是否在 `app/static`；bundle 路径是否被 `realpath` 拒绝 |
| 模板报错 | 看 `ViewException` 消息与日志；检查 `runtime/view` 缓存是否需删 |
| JS 压缩后运行错误 | 是否为现代语法/ESM（应自动跳过）；或 `JsMinify` 失败仅做了分号补齐——应用构建链上应使用 terser/esbuild 等 |
| HTML 被压坏 | 检查 `preg` 是否误伤非常规标签；`pre` 是否成对 |

---

## License

Copyright (c) 2025–2026. All rights reserved.
