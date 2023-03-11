<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2020/9/21
 */

namespace fun\curd;

use fun\curd\service\CurdService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;

/**
 * Class Curd
 * @package app\backend\command
 * 功能待完善
 */
class Addon extends Command
{
    protected function configure()
    {
        $this->setName('addon')
            ->addOption('name', '', Option::VALUE_REQUIRED, '插件名', '')
            ->addOption('title', '', Option::VALUE_REQUIRED, '插件标题', '')
            ->addOption('description', '', Option::VALUE_OPTIONAL, '插件名', '')
            ->addOption('author', '', Option::VALUE_OPTIONAL, '插件作者', '')
            ->addOption('ver', '', Option::VALUE_OPTIONAL, '插件版本', '')
            ->addOption('requires', '', Option::VALUE_OPTIONAL, '插件需求版本', '')
            ->addOption('menu', 'u', Option::VALUE_OPTIONAL, '菜单', 0)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->addOption('jump', '', Option::VALUE_OPTIONAL, '跳过重复文件', 1)
            ->setDescription('Addon Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['name'] = $input->getOption('name');
        $param['title'] = $input->getOption('title');
        $param['description'] = $input->getOption('description');
        $param['author'] = $input->getOption('author');
        $param['version'] = $input->getOption('ver');
        $param['requires'] = $input->getOption('requires');
        $param['menu']  = $input->getOption('menu');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $param['menu'] = $input->getOption('menu');
        $param['jump'] = $input->getOption('jump');
        $param['addon'] = $param['name'];
        if (empty($param['name'])) {
            $output->info("插件名不能为空");
            return false;
        }
        $curdService = new CurdService($param);
        try {
            $curdService->makeAddon();
            $output->info('make success');
        }catch (\Exception $e){
            $output->writeln('----------------');
            $output->error($e->getMessage());
            $output->writeln('----------------');
        }
    }
}
