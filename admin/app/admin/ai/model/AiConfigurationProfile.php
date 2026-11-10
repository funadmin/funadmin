<?php

declare(strict_types=1);
namespace app\admin\ai\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

/** 档案实体；序列化始终隐藏密文与内部唯一索引字段。 */
final class AiConfigurationProfile extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'ai_configuration_profile';
    protected $hidden = ['secret_ciphertext', 'default_admin_id', 'deleted_token'];
    protected $fillable = ['admin_id', 'name', 'configuration', 'secret_ciphertext', 'is_default'];
    protected $casts = ['configuration' => 'array', 'id' => 'integer', 'admin_id' => 'integer', 'is_default' => 'boolean'];
}
