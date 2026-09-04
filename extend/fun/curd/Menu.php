<?php

namespace fun\curd;

use app\backend\model\Permission;
use app\backend\service\ResourceRegistryService;
use fun\helper\CtrHelper;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Menu extends Command
{
    protected $sysController = [
        'sys.Attach', 'sys.AttachGroup', 'sys.Upgrade', 'Addon', 'Ajax',
        'Error', 'Index', 'Login',
    ];

    protected function configure()
    {
        $this->setName('menu')
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('app', '', Option::VALUE_OPTIONAL, 'app', 'backend')
            ->addOption('menuname', '', Option::VALUE_OPTIONAL, '菜单名称', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->setDescription('Menu Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $controller = (string) $input->getOption('controller');
        $app = strtolower((string) $input->getOption('app'));
        $sourceName = $controller !== '' ? strtolower(str_replace(['\\', '/'], '.', $controller)) : $app;
        if ($app === 'backend' && in_array($controller, $this->sysController, true)) {
            $output->error("{$controller}系统控制器不能生成");
            return false;
        }
        if ($controller === '' && $app === 'backend') {
            $output->error('Backend应用控制器不能为空');
            return false;
        }

        $registry = ResourceRegistryService::instance();
        if ($input->getOption('force') && $input->getOption('delete')) {
            $controller === '' ? $registry->removeModule($app) : $registry->removeSource('crud', $sourceName);
            $output->info('delete success');
            return true;
        }

        $controllers = $controller === ''
            ? CtrHelper::getControllersByApp($app)
            : [$this->analyzeController($app, $controller)];
        $menus = [];
        foreach (array_filter($controllers) as $item) {
            $children = [];
            foreach ($item['methods'] as $method) {
                $children[] = [
                    'href' => $item['route_info'] . '/' . $method['name'],
                    'title' => $method['comment'],
                    'status' => 1,
                    'type' => 2,
                    'visible' => 0,
                ];
            }
            $menus[] = [
                'href' => $item['route_info'],
                'title' => $input->getOption('menuname') ?: $item['comment'],
                'status' => 1,
                'type' => 1,
                'visible' => 1,
                'icon' => 'i-ep-menu',
                'menulist' => $children,
            ];
        }
        if ($controller === '') {
            $menus = [[
                'href' => '',
                'title' => ucfirst($app) . 'Manager',
                'status' => 1,
                'type' => 1,
                'visible' => 1,
                'module' => $app,
                'icon' => 'i-ep-menu',
                'menulist' => $menus,
            ]];
        }
        $registry->registerTree($menus, 0, 0, $app, 'crud', $sourceName);
        $output->info('make success');
        return true;
    }

    private function analyzeController(string $app, string $controller): array
    {
        $parts = explode('/', str_replace('.', '/', $controller));
        $parts[] = ucfirst(array_pop($parts));
        $file = app_path($app . '/controller') . implode(DS, $parts) . '.php';
        $result = CtrHelper::analyzeController($app, $file);
        if (!$result) {
            throw new \RuntimeException('控制器分析失败：' . $controller);
        }
        return $result;
    }
}
