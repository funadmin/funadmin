<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\model;
use app\common\model\BaseModel;
/** 云存储配置；id 为 16 位十六进制字符串，credentials 为加密后的密文。 */
final class OssProfile extends BaseModel
{
    public $timestamps = false;
    protected $table = 'oss_profile';
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;
}
