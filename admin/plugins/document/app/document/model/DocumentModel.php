<?php

declare(strict_types=1);

namespace app\document\model;

use app\common\model\BaseModel;

/**
 * 技术文档表模型基类。
 *
 * 时间戳由服务用 Rules::now() 显式写入，关闭自动时间戳；JSON 列由服务编码、解码，不声明 cast；
 * 不声明 $connection，查询走默认连接，仍处于外层 Db::transaction 中。
 */
abstract class DocumentModel extends BaseModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [];
}
