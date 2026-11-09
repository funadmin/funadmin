<?php
declare(strict_types=1);
namespace app\cms\service;
use InvalidArgumentException;
final class FormFieldCatalog
{
    public const TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'radio', 'checkbox', 'select', 'date', 'datetime', 'time', 'consent', 'file', 'image', 'hidden', 'paragraph', 'divider'];
    public const DATA_TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'radio', 'checkbox', 'select', 'date', 'datetime', 'time', 'consent', 'file', 'image', 'hidden'];
    public const FILE_TYPES = ['file', 'image'];
    public const OPTION_TYPES = ['radio', 'checkbox', 'select'];
    public const TEXT_LIMITS = ['text' => 2000, 'textarea' => 20000, 'hidden' => 500];
    public const ACCEPT_CATEGORIES = [
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];
    /**
     * 与内置表单控件（验证码块的 data-field/错误键、蜜罐、令牌与语言隐藏字段）冲突或易混淆的字段标识。
     * `source` 不保留：内置来源字段是 `_source`，而以 `source` 作为“来源渠道”隐藏字段是常见用法。
     */
    public const RESERVED_NAMES = ['captcha', 'captcha_id', 'hp', 'token', 'locale'];
    public const MAX_FIELDS = 100;
    public const MAX_FILE_SIZE = 20 * 1024 * 1024;
    public const MAX_FILES = 10;
    private const MAX_OPTIONS = 100;
    private const MAX_HTML = 20000;
    private const MAX_HTML_INPUT = 200000;

    /** @return list<array<string,mixed>> */
    public static function normalize(array $fields): array
    {
        if (!array_is_list($fields) || count($fields) > self::MAX_FIELDS) throw self::invalid('表单字段配置无效（最多 ' . self::MAX_FIELDS . ' 个）');
        $names = [];
        $display = ['paragraph' => 0, 'divider' => 0];
        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) throw self::invalid('字段定义无效');
            $type = $field['type'] ?? null;
            if (!in_array($type, self::TYPES, true)) throw self::invalid('字段类型无效');
            if (isset($display[$type])) {
                $name = '_' . $type[0] . ++$display[$type];
            } else {
                $name = is_string($field['name'] ?? null) ? trim($field['name']) : '';
                if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) throw self::invalid('字段标识无效：只能使用小写字母、数字和下划线，并以字母开头');
                if (in_array($name, self::RESERVED_NAMES, true)) throw self::invalid('字段标识为系统保留：' . $name);
                if (isset($names[$name])) throw self::invalid('字段标识重复：' . $name);
            }
            $names[$name] = true;
            $result[] = self::field($name, $type, $field);
        }
        return $result;
    }

    private static function field(string $name, string $type, array $field): array
    {
        $width = $field['width'] ?? 'full';
        if (!in_array($width, ['full', 'half'], true)) throw self::invalid('字段宽度无效');
        $out = ['name' => $name, 'type' => $type, 'label' => self::text($field['label'] ?? '', 100), 'width' => $width];
        if ($type === 'divider') return $out;
        if ($type === 'paragraph') return $out + ['html' => self::html($field['html'] ?? '')];
        if ($out['label'] === '') $out['label'] = $name;
        $out += [
            'placeholder' => self::text($field['placeholder'] ?? '', 255),
            'help' => self::text($field['help'] ?? '', 500),
            'required' => self::bool($field, 'required'),
            'default' => null,
        ];
        switch ($type) {
            case 'text':
            case 'textarea':
                $out += self::range($field, fn(mixed $v) => self::count($v, self::TEXT_LIMITS[$type], '文本长度'));
                break;
            case 'number':
                $out += self::range($field, self::number(...));
                $out['integer'] = self::bool($field, 'integer');
                break;
            case 'radio':
            case 'checkbox':
            case 'select':
                $out['options'] = self::options($field['options'] ?? null);
                if ($type === 'select') $out['multiple'] = self::bool($field, 'multiple');
                if ($type === 'checkbox' || ($type === 'select' && $out['multiple'])) $out += self::range($field, fn(mixed $v) => self::count($v, count($out['options']), '可选数量'));
                break;
            case 'date':
                $out += self::range($field, self::date(...));
                break;
            case 'consent':
                $out['html'] = self::html($field['html'] ?? '');
                break;
            case 'file':
            case 'image':
                $out['accept'] = self::accept($type, $field['accept'] ?? null);
                $out['max_size'] = self::limit($field['max_size'] ?? null, self::MAX_FILE_SIZE, self::MAX_FILE_SIZE, '单个文件大小');
                $out['max_files'] = self::limit($field['max_files'] ?? null, 1, self::MAX_FILES, '文件数量');
                break;
            case 'hidden':
                $out['value'] = self::text($field['value'] ?? '', self::TEXT_LIMITS['hidden']);
                $out['from_query'] = self::bool($field, 'from_query');
                break;
        }
        $default = $field['default'] ?? null;
        if (!in_array($type, ['file', 'image', 'hidden'], true) && $default !== null && $default !== '' && $default !== []) {
            $check = array_replace($out, ['required' => false]);
            $result = FormValueValidator::validate([$check], [$name => $default], []);
            if ($result['errors'] !== []) throw self::invalid('默认值无效：' . $out['label']);
            $out['default'] = $result['values'][$name];
        }
        return $out;
    }

    private static function range(array $field, callable $parse): array
    {
        $range = [];
        foreach (['min', 'max'] as $key) {
            if (isset($field[$key]) && $field[$key] !== '') $range[$key] = $parse($field[$key]);
        }
        if (isset($range['min'], $range['max']) && $range['min'] > $range['max']) throw self::invalid('最小值不能大于最大值');
        return $range;
    }

    private static function count(mixed $value, int $limit, string $what): int
    {
        if (!is_int($value) || $value < 0 || $value > $limit) throw self::invalid($what . '配置无效（0–' . $limit . '）');
        return $value;
    }

    private static function limit(mixed $value, int $default, int $limit, string $what): int
    {
        if ($value === null || $value === '') return $default;
        if (!is_int($value) || $value < 1 || $value > $limit) throw self::invalid($what . '超出系统上限');
        return $value;
    }

    private static function number(mixed $value): int|float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) throw self::invalid('数字范围配置无效');
        $number = $value + 0;
        if (!is_finite((float)$number)) throw self::invalid('数字范围配置无效');
        return $number;
    }

    private static function date(mixed $value): string
    {
        $date = is_string($value) ? FormValueValidator::temporal('date', $value) : null;
        if ($date === null) throw self::invalid('日期范围配置无效');
        return $date;
    }

    /** @return list<array{value:string,label:string}> */
    private static function options(mixed $options): array
    {
        if (!is_array($options) || !array_is_list($options) || $options === [] || count($options) > self::MAX_OPTIONS) throw self::invalid('选项字段需要 1 至 ' . self::MAX_OPTIONS . ' 个选项');
        $result = [];
        foreach ($options as $option) {
            if (!is_array($option)) throw self::invalid('选项定义无效');
            $value = self::text($option['value'] ?? '', 255);
            if ($value === '') throw self::invalid('选项值不能为空');
            if (isset($result[$value])) throw self::invalid('选项值不能重复');
            $label = self::text($option['label'] ?? '', 255);
            $result[$value] = ['value' => $value, 'label' => $label === '' ? $value : $label];
        }
        return array_values($result);
    }

    /** @return list<string> */
    private static function accept(string $type, mixed $accept): array
    {
        $categories = $type === 'image' ? ['image' => self::ACCEPT_CATEGORIES['image']] : self::ACCEPT_CATEGORIES;
        if ($accept === null || $accept === []) return array_keys($categories);
        if (!is_array($accept) || !array_is_list($accept) || count($accept) > 50) throw self::invalid('允许的文件类型配置无效');
        $extensions = array_merge(...array_values($categories));
        $result = [];
        foreach ($accept as $item) {
            $item = is_string($item) ? strtolower(ltrim(trim($item), '.')) : '';
            if (!isset($categories[$item]) && !in_array($item, $extensions, true)) throw self::invalid('不允许的文件类型：' . $item);
            $result[$item] = true;
        }
        return array_keys($result);
    }

    /** The cap applies to the sanitized output, so re-normalizing stored html (entities grow `&` into `&amp;`) stays idempotent. */
    private static function html(mixed $value): string
    {
        $html = HtmlSanitizer::clean(self::text($value, self::MAX_HTML_INPUT, true));
        $html = self::text($html, PHP_INT_MAX, true); // removed nodes can leave edge whitespace; trim so a second pass is stable
        if (mb_strlen($html) > self::MAX_HTML) throw self::invalid('内容清洗后超过 ' . self::MAX_HTML . ' 字');
        return $html;
    }

    private static function text(mixed $value, int $max, bool $multiline = false): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw self::invalid('文本格式无效');
        $text = FormValueValidator::clean((string)$value, $multiline);
        if ($text === null || mb_strlen($text) > $max) throw self::invalid('文本无效或超过 ' . $max . ' 字');
        return $text;
    }

    private static function bool(array $field, string $key): bool
    {
        $value = $field[$key] ?? false;
        if (!is_bool($value)) throw self::invalid($key . ' 必须为布尔值');
        return $value;
    }

    private static function invalid(string $message): InvalidArgumentException
    {
        return new InvalidArgumentException($message, 422);
    }
}
