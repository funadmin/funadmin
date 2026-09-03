<?php

namespace app\backend\service;

use app\backend\model\AdminMenu;
use app\common\model\Addon;
use app\common\service\AbstractService;
use app\common\traits\Jump;
use fun\addons\Service;
use think\Exception;
use think\facade\Cache;

class AddonService extends AbstractService
{
    use Jump;

    protected $myaddon = 'myaddon';

    public function addAddonMenu(array $menu, int $pid = 0, string $module = 'backend')
    {
        $parentPermissionId = $pid > 0 ? (int) (AdminMenu::find($pid)->permission_id ?? 0) : 0;
        AdminMenu::where('source_type', 'addon')->where('source_name', $module)->update(['status' => 1]);
        ResourceRegistryService::instance()->registerTree($menu, $parentPermissionId, $pid, $module, 'addon', $module);
    }

    public function delAddonMenu(array $menu, string $module = 'backend')
    {
        AdminMenu::where('source_type', 'addon')->where('source_name', $module)->update(['status' => 0]);
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->where('status', 1)->find()) {
            $manager->save(['status' => 0]);
        }
        $this->delMenuCache();
    }

    public function addAddonManager()
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager) {
            $manager->save(['status' => 1]);
        } else {
            ResourceRegistryService::instance()->registerTree([[
                'title' => '已装插件',
                'href' => '',
                'visible' => 1,
                'type' => 1,
                'status' => 1,
                'icon' => 'layui-icon layui-icon-app',
                'sort' => 50,
            ]], 0, 0, 'backend', 'system', $this->myaddon);
            $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        }
        return $manager;
    }

    public function delMenuCache()
    {
        Cache::clear();
    }

    public function installAddon(string $name, string $type = '')
    {
        $class = get_addons_instance($name);
        if (empty($class)) {
            throw new Exception(lang('addons %s is not ready', [$name]));
        }
        $addonInfo = get_addons_info($name);
        foreach (array_filter(explode(',', (string) ($addonInfo['depend'] ?? ''))) as $dependency) {
            if (empty(get_addons_info($dependency))) {
                throw new Exception('Please install the dependent plugin first: ' . $addonInfo['depend']);
            }
        }

        $class->install();
        run_addon_migrations($name);
        $menuConfig = get_addons_menu($name);
        if (!empty($menuConfig)) {
            [$menu, $pid] = $this->getMenu($menuConfig);
            $this->addAddonMenu($menu, $pid, $name);
        }
        $addonInfo['status'] = 1;
        $model = new Addon();
        $installed = $this->isInstall($name);
        if ($installed) {
            if ($installed->delete_time > 0) {
                $model->restore(['id' => $installed->id]);
            }
            $result = $model->update(['status' => 1], ['id' => $installed->id]);
        } else {
            $result = $model->save($addonInfo);
        }
        if (!$result) {
            throw new Exception(lang('addon install fail'));
        }
        Service::copyApp($name, true);
        Service::updateAddonsInfo($name);
        refreshaddons();
        return true;
    }

    public function uninstallAddon(string $name)
    {
        if ($name === '' || !preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new Exception(lang('addon name is not right'));
        }
        $model = new Addon();
        $info = $model->withTrashed()->where('name', $name)->find();
        if (!$info) {
            throw new Exception(lang('addon is not exist'));
        }
        if ((int) $info->status === 1) {
            throw new Exception(lang('Please disable addons %s first', [$name]));
        }
        if (!$info->force()->delete()) {
            throw new Exception(lang('addon uninstall fail'));
        }
        get_addons_instance($name)->uninstall();
        ResourceRegistryService::instance()->removeSource('addon', $name);
        $this->removeEmptyAddonManager();
        Service::removeApp($name, true);
        Service::updateAddonsInfo($name, 1, 0);
        refreshaddons();
        return true;
    }

    public function isInstall($name)
    {
        return $name === '' ? false : Addon::withTrashed()->where('name', $name)->find();
    }

    public function getMenu($config = [])
    {
        $isNav = $config['is_nav'] ?? 1;
        $menuItems = $config['menu'];
        $items = isset($menuItems[0]) && is_array($menuItems[0]) ? $menuItems : [$menuItems];
        $menu = [];
        $pid = 0;
        foreach ($items as $item) {
            if ($isNav == -1) {
                $menu = array_merge($menu, $item['menulist']);
            } elseif ($isNav == 0) {
                $menu[] = $item;
                $pid = (int) $this->addAddonManager()->id;
            } else {
                $menu[] = $item;
            }
        }
        return [$menu, $pid];
    }

    public function modifyAddon(string $name)
    {
        $info = Addon::where('name', $name)->find();
        $addonInfo = get_addons_info($name);
        $addonInfo['status'] = $addonInfo['status'] ? 0 : 1;
        $class = get_addons_instance($name);
        $menuConfig = get_addons_menu($name);
        if (!empty($menuConfig)) {
            [$menu, $pid] = $this->getMenu($menuConfig);
            $addonInfo['status'] ? $this->addAddonMenu($menu, $pid, $name) : $this->delAddonMenu($menu, $name);
        }
        $info->status = $addonInfo['status'];
        Service::updateAddonsInfo($name, $addonInfo['status']);
        refreshaddons();
        $info->save();
        $addonInfo['status'] == 1 ? $class->enabled() : $class->disabled();
    }

    private function removeEmptyAddonManager(): void
    {
        $manager = AdminMenu::where('source_type', 'system')->where('source_name', $this->myaddon)->find();
        if ($manager && !AdminMenu::where('pid', $manager->id)->find()) {
            ResourceRegistryService::instance()->removeSource('system', $this->myaddon);
        }
    }
}
