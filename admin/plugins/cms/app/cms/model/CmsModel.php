<?php
declare(strict_types=1);
namespace app\cms\model;
use app\common\model\BaseModel;
/**
 * CMS 表模型基类。
 *
 * 时间戳由调用方用 Input::now()（UTC）显式写入，所以关闭自动时间戳；
 * JSON 列由 Input::json() 写入、Input::row() 解码，所以不声明 cast；
 * 不声明 $connection，查询走默认连接，仍处于外层 Db::transaction 中。
 */
abstract class CmsModel extends BaseModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [];
}
