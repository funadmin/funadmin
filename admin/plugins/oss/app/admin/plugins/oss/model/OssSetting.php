<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\model;
use app\common\model\BaseModel;
/** 云存储全局设置（单行，id=1），记录当前启用的配置。 */
final class OssSetting extends BaseModel
{
    public $timestamps = false;
    protected $table = 'oss_setting';
    protected $guarded = [];
}
