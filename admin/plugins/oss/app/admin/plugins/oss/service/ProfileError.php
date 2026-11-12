<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use InvalidArgumentException;
/**
 * 云存储配置的 404/409 业务错误：除中文提示外带稳定的机器可读原因（$reason），
 * 控制器按宿主约定放进响应 data.code（同 AdminController 的 FORM_* 错误码），前端据此显示本地化文案。
 */
final class ProfileError extends InvalidArgumentException
{
    public const NOT_FOUND='OSS_PROFILE_NOT_FOUND';
    public const NOT_IN_RECYCLE='OSS_PROFILE_NOT_IN_RECYCLE';
    public const CURRENT='OSS_PROFILE_CURRENT';
    public const IN_USE='OSS_PROFILE_IN_USE';
    public function __construct(public readonly string $reason,string $message,int $code){parent::__construct($message,$code);}
    public static function notFound():self{return new self(self::NOT_FOUND,'云存储配置不存在',404);}
    public static function notInRecycle():self{return new self(self::NOT_IN_RECYCLE,'云存储配置不在回收站',404);}
    public static function current(string $message='不能删除当前存储配置，请先把其他配置设为当前存储'):self{return new self(self::CURRENT,$message,409);}
    public static function inUse():self{return new self(self::IN_USE,'仍有附件（含回收站中的附件）使用此配置，不能彻底删除',409);}
}
