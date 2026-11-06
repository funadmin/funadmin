<?php
declare(strict_types=1);
return ['scope'=>static function(array $codes,array $context):array {
    try {
        $operations=(new \app\cms\service\SiteAuthorization())->acrossSites((string)$context['admin_id'],$codes);
        return \app\cms\service\PermissionCatalog::scope($codes,$operations);
    } catch(\Throwable) { return array_values(array_filter($codes,static fn($c)=>!str_starts_with((string)$c,'cms:'))); }
}];
