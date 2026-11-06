<?php

declare(strict_types=1);

use support\Db;

/** Retire the removed local market operator UI; keep tables, orders and legacy member tokens. */
return static function (): void {
    if (Db::schema()->hasTable('permission')) {
        Db::table('permission')->where('app_name','admin')->where(function ($query): void {
            $query->where('source_type','plugin')->where('source_name','market')->orWhere('code','like','market:%');
        })->update(['status'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
    }
    if (Db::schema()->hasTable('admin_menu')) {
        Db::table('admin_menu')->where('app_name','admin')->where(function ($query): void {
            $query->where(function ($source): void { $source->where('source_type','plugin')->where('source_name','market'); })
                ->orWhere('href','/plugin/market')->orWhere('href','like','/plugin/market/%')
                ->orWhere('href','/admin/plugin/market')->orWhere('href','like','/admin/plugin/market/%');
        })->update(['status'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
    }
};
