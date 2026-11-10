<?php
declare(strict_types=1);
namespace plugins\cms;
use app\common\plugin\sdk\Plugin as BasePlugin;
final class Plugin extends BasePlugin
{
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }
    public function enabled(): bool { \app\cms\service\ModelService::seed(); \app\cms\service\SiteRoleSchema::bootstrap(); \app\cms\service\FormPermissionUpgrade::backfill(); return true; }
    public function disabled(): bool { return true; }
    public function purgeData(): bool { return false; }
}
