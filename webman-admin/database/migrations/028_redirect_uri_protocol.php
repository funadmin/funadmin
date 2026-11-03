<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $s = Db::schema();
    if (!$s->hasColumn('redirect_uri','uri_type')) $s->table('redirect_uri', static function (Blueprint $t): void { $t->enum('uri_type',['authorization_callback','post_logout'])->default('authorization_callback'); });
    if (!$s->hasColumn('redirect_uri','redirect_uri')) $s->table('redirect_uri', static function (Blueprint $t): void { $t->string('redirect_uri',2048)->default(''); });
    if (!$s->hasColumn('redirect_uri','status')) $s->table('redirect_uri', static function (Blueprint $t): void { $t->smallInteger('status')->default(1); });
    // uri 是早期本地骨架列；保留但允许新协议只写 redirect_uri。
    if ($s->hasColumn('redirect_uri','uri')) $s->table('redirect_uri', static function (Blueprint $t): void { $t->string('uri',2048)->nullable()->change(); });
    $connection = Db::connection();
    $table = $connection->getQueryGrammar()->wrapTable('redirect_uri');
    $connection->statement('UPDATE ' . $table . " SET redirect_uri=COALESCE(uri,'') WHERE redirect_uri=''");
};
