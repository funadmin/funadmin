<?php

declare(strict_types=1);

return [
    'enabled' => [
        'title' => '展示示例页面',
        'type' => 'switch',
        'value' => true,
        'tip' => '关闭后由示例插件业务代码隐藏示例内容。',
    ],
    'welcome_message' => [
        'title' => '欢迎语',
        'type' => 'text',
        'value' => '欢迎使用 FunAdmin 示例插件',
    ],
    'page_size' => [
        'title' => '默认每页数量',
        'type' => 'number',
        'value' => 20,
    ],
];
