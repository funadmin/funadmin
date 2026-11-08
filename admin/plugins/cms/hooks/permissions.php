<?php
declare(strict_types=1);
return ['scope'=>static function(array $codes,array $context):array {
    try {
        $operations=(new \app\cms\service\SiteAuthorization())->acrossSites((string)$context['admin_id'],$codes);
        return \app\cms\service\PermissionCatalog::scope($codes,$operations);
    } catch(\InvalidArgumentException $error) {
        // 站点权限表缺失（CMS 尚未完成升级）：保留宿主授予的码，让 CMS 接口自己返回 409 提示升级，而不是先被 403 掩盖。
        // 此时站点权限无法计算，CMS 接口在执行任何操作前都会因同一原因拒绝，因此不会放宽访问。
        if($error->getCode()===409&&!\app\cms\service\SiteRoleSchema::ready())return array_values($codes);
        return array_values(array_filter($codes,static fn($c)=>!str_starts_with((string)$c,'cms:')));
    } catch(\Throwable) { return array_values(array_filter($codes,static fn($c)=>!str_starts_with((string)$c,'cms:'))); }
}];
