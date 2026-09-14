<?php

declare(strict_types=1);

namespace app\console\development\http;

use app\common\form\schema\FormSchemaException;
use app\console\development\exception\BusinessOperationException;
use app\console\development\service\BusinessConflictException;
use app\console\development\service\BusinessResourceGoneException;
use InvalidArgumentException;
use Throwable;

final class BusinessApiErrorMapper
{
    private const ERRORS = [
        'BUSINESS_TARGET_FORBIDDEN' => [403, '没有插件开发权限', false],
        'BUSINESS_TABLE_FORBIDDEN' => [403, '不允许访问该业务表', false],
        'BUSINESS_TARGET_UNAVAILABLE' => [409, '插件目标不可用，请刷新后重试', true],
        'BUSINESS_TARGET_IDENTITY_CONFLICT' => [409, '提案与业务所属目标不一致', false],
        'BUSINESS_SCHEMA_IDENTITY_CONFLICT' => [409, '保存结构与业务表身份不一致', false],
        'BUSINESS_SAVED_SCHEMA_REQUIRED' => [409, '插件生成必须使用当前已保存结构', false],
        'BUSINESS_PLUGIN_DYNAMIC_PUBLISH_FORBIDDEN' => [422, '插件业务不支持动态发布，请生成源码后安装或更新插件', false],
        'BUSINESS_DEFAULT_CONNECTION_ONLY' => [422, '插件业务仅支持默认数据库连接', false],
        'BUSINESS_TABLE_PREFIX_REQUIRED' => [422, '新表名称必须使用所属插件前缀', false],
        'BUSINESS_TABLE_ALREADY_EXISTS' => [409, '新建业务表已存在', false],
        'BUSINESS_CODE_ALREADY_EXISTS' => [409, '业务标识已存在，请更换业务标识', false],
        'BUSINESS_EXTERNAL_TABLE_MISSING' => [409, '外部依赖表不存在', false],
        'BUSINESS_TABLE_STRATEGY_INVALID' => [422, '业务表策略无效', false],
        'FORM_SCHEMA_CONFLICT' => [409, '表单结构已被修改，请刷新后重试', false],
        'DATABASE_INSPECTION_STALE' => [409, '数据库结构已变化，请重新检查', true],
        'GENERATION_PLAN_CONFLICT' => [409, '生成计划存在冲突', false],
        'GENERATION_TARGET_DRIFT' => [409, '生成目标已变化，请重新预览', true],
        'GENERATION_SUPERSEDED' => [409, '生成计划已被更新计划替代', false],
        'GENERATION_IN_PROGRESS' => [409, '生成任务正在执行', true],
        'GENERATION_BUSY' => [409, '生成服务繁忙，请稍后重试', true],
        'GENERATION_RECOVERY_REQUIRED' => [409, '生成任务需要人工恢复', false],
        'UNSUPPORTED_GENERATION_INPUT' => [422, '正式生成请求包含不支持的输入', false],
        'GENERATION_RECOVERY_STATUS_CONFLICT' => [409, '恢复状态已变化，请刷新后重试', true],
        'GENERATION_BINDING_CONFLICT' => [409, '生成记录与恢复事务不匹配', false],
    ];

    public static function map(Throwable $exception, ?string $requestId = null): array
    {
        $code = self::code($exception);
        [$httpStatus, $message, $retryable] = self::ERRORS[$code] ?? self::fallback($exception);
        $details = $exception instanceof BusinessOperationException ? $exception->details() : [];
        // 仅将已知业务错误映射到公开表单字段，不透传异常文本或数据库信息。
        $field = match ($code) {
            'BUSINESS_CODE_ALREADY_EXISTS' => 'code',
            'BUSINESS_TABLE_ALREADY_EXISTS', 'BUSINESS_TABLE_PREFIX_REQUIRED', 'BUSINESS_EXTERNAL_TABLE_MISSING' => 'table',
            'BUSINESS_DEFAULT_CONNECTION_ONLY' => 'connection',
            default => null,
        };
        if ($field !== null) $details['fieldErrors'] = [$field => $message];
        return [
            'httpStatus' => $httpStatus,
            'message' => $message,
            'error' => [
                'code' => $code,
                'requestId' => self::requestId($requestId),
                'retryable' => $retryable,
                'details' => BusinessResponseSanitizer::sanitize($details),
            ],
        ];
    }

    private static function code(Throwable $exception): string
    {
        if ($exception instanceof BusinessOperationException) return $exception->errorCode();
        $message = $exception->getMessage();
        if ($exception instanceof InvalidArgumentException && in_array($message, ['表单标识已存在', '业务模块标识已存在'], true)) {
            return 'BUSINESS_CODE_ALREADY_EXISTS';
        }
        if (isset(self::ERRORS[$message])) return $message;
        if ($exception instanceof BusinessResourceGoneException) return 'RESOURCE_GONE';
        if ($exception instanceof BusinessConflictException) return 'GENERATION_PLAN_CONFLICT';
        if ($exception instanceof FormSchemaException || $exception instanceof InvalidArgumentException) return 'VALIDATION_ERROR';
        return 'INTERNAL_ERROR';
    }

    private static function fallback(Throwable $exception): array
    {
        return match (self::code($exception)) {
            'RESOURCE_GONE' => [410, '请求的资源已退役或不存在', false],
            'VALIDATION_ERROR' => [422, '请求参数不合法', false],
            default => [500, '服务器内部错误', false],
        };
    }

    private static function requestId(?string $requestId): string
    {
        $value = trim((string) $requestId);
        if ($value !== '' && strlen($value) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1) return $value;
        return bin2hex(random_bytes(16));
    }
}
