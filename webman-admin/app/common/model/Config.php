<?php

namespace app\common\model;
use app\admin\traits\AdminDataFormat;
use app\common\model\concern\LaravelSoftDelete;

class Config extends BaseModel
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

    /**
     * 配置写入校验：编码/分组/类型/规则/选项边界，并规范化配置值。
     */
    public static function validateAttributes(array &$data): ?string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,29}$/', $data['code'])) {
            return '配置编码必须以字母开头，且只能包含字母、数字、点、横线和下划线，最长 30 位';
        }
        if ($data['group'] === '' || strlen($data['group']) > 80 || !ConfigGroup::where('name', $data['group'])->find()) {
            return '请选择有效的配置分组';
        }
        $builtInTypes = ['date', 'json'];
        $typeExists = in_array($data['type'], $builtInTypes, true)
            || FieldType::where('name', $data['type'])->where('status', 1)->find();
        if ($data['type'] === '' || strlen($data['type']) > 30 || !$typeExists) {
            return '请选择有效的字段类型';
        }
        if (strlen($data['verify']) > 30 || ($data['verify'] !== '' && $data['verify'] !== '0' && !FieldVerify::where('verify', $data['verify'])->find())) {
            return '请选择有效的验证规则';
        }
        if (strlen($data['extra']) > 255) {
            return '选项定义不能超过 255 个字符';
        }
        if ((function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark'])) > 100) {
            return '配置备注不能超过 100 个字符';
        }
        [$data['value'], $error] = self::normalizeValue($data['type'], $data['value'], $data['extra']);
        return $error;
    }

    public static function normalizeValue(string $type, mixed $raw, string $extra): array
    {
        if (in_array($type, ['checkbox', 'array', 'tags', 'images', 'files'], true)) {
            $items = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);
            $value = implode("\n", array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string) $item), $items), static fn (string $item): bool => $item !== ''))));
        } elseif ($type === 'switch') {
            $value = in_array($raw, [1, '1', true, 'true', 'on'], true) ? '1' : '0';
        } else {
            $value = is_scalar($raw) || $raw === null ? (string) $raw : '';
        }
        if ($type === 'number' && $value !== '' && !preg_match('/^-?\d+$/', $value)) {
            return ['', '配置值必须为整数'];
        }
        if (in_array($type, ['float', 'decimal'], true) && $value !== '' && !is_numeric($value)) {
            return ['', '配置值必须为数字'];
        }
        if ($type === 'json' && $value !== '') {
            json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['', '配置值必须是合法的 JSON'];
            }
        }
        if (in_array($type, ['date', 'datetime'], true) && $value !== '') {
            $format = $type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if (!$date || $date->format($format) !== $value) {
                return ['', $type === 'date' ? '配置值必须是有效日期' : '配置值必须是有效日期时间'];
            }
        }
        if ($type === 'range' && $value !== '') {
            $range = preg_split('/\s+-\s+/', $value, 2);
            if (count($range) !== 2) {
                return ['', '配置值必须是有效日期时间范围'];
            }
            foreach ($range as $item) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $item);
                if (!$date || $date->format('Y-m-d H:i:s') !== $item) {
                    return ['', '配置值必须是有效日期时间范围'];
                }
            }
        }
        if (in_array($type, ['radio', 'select', 'checkbox'], true) && $extra !== '') {
            $allowed = array_keys(self::parseOptions($extra));
            $values = $type === 'checkbox' ? array_filter(explode("\n", $value)) : [$value];
            foreach ($values as $item) {
                if ($item !== '' && !in_array($item, $allowed, true)) {
                    return ['', '配置值不在允许的选项范围内'];
                }
            }
        }
        return [$value, null];
    }

    public static function parseOptions(string $extra): array
    {
        $options = [];
        foreach (preg_split('/\r?\n/', $extra) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$value, $label] = array_pad(explode(':', $line, 2), 2, $line);
            $options[trim($value)] = trim($label);
        }
        return $options;
    }

    public function toApiData(): array
    {
        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'group' => (string) $this->group,
            'type' => (string) $this->type,
            'verify' => (string) ($this->verify ?? ''),
            'value' => (string) ($this->value ?? ''),
            'extra' => (string) ($this->extra ?? ''),
            'remark' => (string) ($this->remark ?? ''),
            'status' => (int) $this->status,
            'isSystem' => (int) $this->is_system === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }

}