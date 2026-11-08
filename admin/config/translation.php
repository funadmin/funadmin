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

/**
 * Multilingual configuration
 */
return [
    // Default language
    'locale' => 'zh-cn',
    // Fallback language
    'fallback_locale' => ['zh-cn', 'en-us'],
    // 公共语言包目录；业务模块与插件的语言包放在各自目录的 lang/<语言>.php，由 GeneratedLanguagePacks 自动发现。
    'path' => [app_path('common/lang')],
];