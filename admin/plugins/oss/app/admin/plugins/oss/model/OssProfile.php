<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\model;
use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
/** 云存储配置；id 为 16 位十六进制字符串（主键，oss_setting.current_profile_id 引用它），credentials 为加密后的密文。软删除只写 deleted_at（表上没有 deleted_token）。 */
final class OssProfile extends BaseModel
{
    use LaravelSoftDelete;
    public $timestamps = false;
    protected $table = 'oss_profile';
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;
}
