<?php

declare(strict_types=1);

use app\admin\business\order\model\Order;

if (!is_subclass_of(Order::class, \Illuminate\Database\Eloquent\Model::class)) {
    throw new RuntimeException('生成模型必须继承 Eloquent Model');
}
echo 'business order PHP contract: PASS' . PHP_EOL;
