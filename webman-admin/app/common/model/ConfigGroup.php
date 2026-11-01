<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/26
 */


namespace app\common\model;
use app\admin\traits\AdminDataFormat;
use app\common\model\concern\LaravelSoftDelete;

class ConfigGroup extends BaseModel
{
    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use AdminDataFormat;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public static function validateAttributes(array $data): ?string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,29}$/', $data['name'])) {
            return '分组编码必须以字母开头，且只能包含字母、数字、横线和下划线，最长 30 位';
        }
        if ($data['title'] === '' || (function_exists('mb_strlen') ? mb_strlen($data['title']) : strlen($data['title'])) > 60) {
            return '分组标题不能为空且不能超过 60 个字符';
        }
        return null;
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'title' => (string) $this->title,
            'status' => (int) $this->status,
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }

}