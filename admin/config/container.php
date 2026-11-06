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

$container = new Webman\Container;
$container->addDefinitions([
    app\common\middleware\ApiMiddleware::class => static fn () => new app\common\middleware\ApiMiddleware(
        support\Container::get(app\common\service\member\MemberApiSessionService::class),
        support\Container::get(app\common\service\authentication\BearerTokenExtractor::class)
    ),
]);
return $container;