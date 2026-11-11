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
 * 软删除写 deleted_at 时同样取 UTC（freshTimestamp），与 Input::now() 写入的其他时间一致。
 */
abstract class CmsModel extends BaseModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [];

    public function freshTimestamp(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::now('UTC');
    }
}
