<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
namespace fun\builder\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Env;

class Config extends Command
{

    public function configure()
    {
        $this->setName('builder:config')->setDescription('ApiTraits js file to builder folder');
    }

    public function execute(Input $input, Output $output)
    {
        //获取默认配置文件
        $content = file_get_contents(root_path() . 'extend/fun/builder/layout/builder.js');
        $builderPath = public_path() . 'static/js';
        $builderFile =  $builderPath .'/builder.js';

        //判断文件是否存在
        if (is_file($builderFile)) {
            $output->info(sprintf('The builder js file "%s" already exists', $builderFile));
        }

        if (false === file_put_contents($builderFile, $content)) {
            $output->info(sprintf('The builder file "%s" could not be written to "%s"', $builderFile,$builderPath));
        }

        $output->writeln('create builder js ok');
    }

}
