<?php
namespace fun\addons\command;

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
        $this->setName('addons:config')
            ->setDescription('config to config folder');
    }

    public function execute(Input $input, Output $output)
    {
        //获取默认配置文件
        $content = file_get_contents(root_path() . 'extend/fun/addons/config.php');
        $configPath = config_path() ;
        $configFile = $configPath . 'addons.php';
        //判断目录是否存在
        if (!file_exists($configPath)) {
            @mkdir($configPath, 0755, true);
        }

        //判断文件是否存在
        if (is_file($configFile)) {
            $output->info(sprintf('The config file "%s" already exists', $configFile));
        }

        if (false === file_put_contents($configFile, $content)) {
            $output->info(sprintf('The config file "%s" could not be written to "%s"', $configFile,$configPath));
        }

        $output->writeln('create addons config ok');
    }

}
