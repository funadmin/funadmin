<?php

declare(strict_types=1);

return [
    // 会员注销冷静期（天）：期内被删会员的用户名、手机号、邮箱仍被占用，管理员可在回收站恢复；期满后放开给新账号使用。
    'member_cooling_days' => 30,
];
