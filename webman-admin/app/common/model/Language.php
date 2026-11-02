<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\traits\DataFormat;
use app\common\model\concern\LaravelSoftDelete;

class Language extends BaseModel
{
    use LaravelSoftDelete;
    use DataFormat;

    protected $name = 'language';

    public static function validateAttributes(string $name): ?string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '') {
            return '语言名称不能为空';
        }
        if ($length > 20) {
            return '语言名称不能超过 20 个字符';
        }
        if (!preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/D', $name)) {
            return '语言名称必须为有效区域码，例如 zh-cn 或 en-us';
        }
        return null;
    }

    /**
     * 默认语言不可重命名或删除；zh-cn 视为内置默认。
     */
    public function isDefaultLanguage(): bool
    {
        return (int) $this->is_default === 1 || strtolower((string) $this->getAttribute('name')) === 'zh-cn';
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->getAttribute('name'),
            'isDefault' => (int) $this->is_default,
            'status' => (int) $this->status,
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }
}
