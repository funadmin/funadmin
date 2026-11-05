<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    if (!Db::table('field_type')->where('name', 'hidden')->exists()) {
        $now = date('Y-m-d H:i:s');
        Db::table('field_type')->insert([
            'name' => 'hidden',
            'title' => '隐藏域',
            'isoption' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
