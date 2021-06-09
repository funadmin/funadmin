<?php

namespace app\backend\command;

use app\backend\service\CurdService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;

/**
 * Class Menu
 * @package app\backend\command
 * 功能待完善
 */
class Menu extends Command
{
    protected function configure()
    {
        $this->setName('menu')
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('addon', 'a', Option::VALUE_OPTIONAL, '插件名', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->setDescription('Menu Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['controller'] = $input->getOption('controller');
        $param['addon'] = $input->getOption('addon');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        if (empty($param['controller'])) {
            $output->info("控制器不能为空");
            return false;
        }
        try {
            $output->info('make success');
        }catch (\Exception $e){
            $output->writeln('----------------');
            $output->error($e->getMessage());
            $output->writeln('----------------');
        }
    }
}
