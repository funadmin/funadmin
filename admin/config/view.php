<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/*
 * 视图使用 Blade：模板后缀 .blade.php，编译缓存在 runtime/views。
 * 处理器基于 illuminate/view 实现（webman/blade 尚不支持 illuminate 13），模板定位规则与 webman 文档一致。
 * 需要自定义指令或组件时增加 extension 回调，例如：
 *   'extension' => function (\Illuminate\View\Compilers\BladeCompiler $blade) {
 *       $blade->directive('datetime', fn ($expression) => "<?php echo date('Y-m-d H:i:s', $expression); ?>");
 *   },
 */
return [
    'handler' => app\common\view\Blade::class,
];
