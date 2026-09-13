<?php

declare(strict_types=1);
namespace app\console\ai\model;

use app\console\model\BackendModel;

/** 档案实体；序列化始终隐藏密文与内部唯一索引字段。 */
final class AiConfigurationProfile extends BackendModel
{
    protected $name = 'ai_configuration_profile';
    protected $hidden = ['secret_ciphertext', 'default_admin_id'];
    protected $json = ['configuration'];
    protected $jsonAssoc = true;
    protected $type = ['id'=>'integer', 'admin_id'=>'integer', 'is_default'=>'boolean'];
}
