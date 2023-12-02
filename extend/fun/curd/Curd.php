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
class Curd extends Command
{
    protected function configure()
    {
        $this->setName('curd')
            ->addOption('driver', '', Option::VALUE_OPTIONAL, '数据库', 'mysql')
            ->addOption('table', 't', Option::VALUE_REQUIRED, '表名', null)
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('model', 'm', Option::VALUE_OPTIONAL, '模型名', null)
            ->addOption('fields', 'i', Option::VALUE_OPTIONAL|Option::VALUE_IS_ARRAY, '显示字段', null)
            ->addOption('fieldslist', '', Option::VALUE_OPTIONAL|Option::VALUE_IS_ARRAY, '字段属性数组', null)
            ->addOption('validate', 'l', Option::VALUE_OPTIONAL, '验证器', null)
            ->addOption('joinTable', 'j', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表名', null)
            ->addOption('joinModel', 'o', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联模型', null)
            ->addOption('joinName', 'e', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联模型名字', null)
            ->addOption('joinMethod', 'w', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关系表方式 hasone or belongsto等', null)
            ->addOption('joinPrimaryKey', 'p', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联主键', null)
            ->addOption('joinForeignKey', 'k', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联外键', null)
            ->addOption('joinFields', 's', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表显示字段', null)
            ->addOption('selectFields', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '关联表下拉显示字段', null)
            ->addOption('imageSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图片后缀', null)
            ->addOption('fileSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '文件后缀', null)
            ->addOption('timeSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '时间后缀', null)
            ->addOption('switchSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '开关后缀', null)
            ->addOption('radioSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '单选后缀', null)
            ->addOption('checkboxSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '多选后缀', null)
            ->addOption('citySuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '城市后缀', null)
            ->addOption('iconSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '图标后缀', null)
            ->addOption('jsonSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'json后缀', null)
            ->addOption('selectSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉选择后缀', null)
            ->addOption('selectsSuffix', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '下拉多选后缀', null)
            ->addOption('sortField', '', Option::VALUE_OPTIONAL, '排序字段', null)
            ->addOption('statusField', '', Option::VALUE_OPTIONAL, '状态字段', null)
            ->addOption('ignoreFields', 'g', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '忽略的字段', null)
            ->addOption('formFields', '', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '表单字段', null)
            ->addOption('method', '', Option::VALUE_OPTIONAL, '方法', null)
            ->addOption('page', '', Option::VALUE_OPTIONAL, '是否页', null)
            ->addOption('limit', '', Option::VALUE_OPTIONAL , '分页大小', null)
            ->addOption('addon', 'a', Option::VALUE_OPTIONAL, '插件名', '')
            ->addOption('menu', 'u', Option::VALUE_OPTIONAL, '菜单', 0)
            ->addOption('menuid', '', Option::VALUE_OPTIONAL, '上级菜单', 0)
            ->addOption('menuname', '', Option::VALUE_OPTIONAL, '菜单名称', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->addOption('jump', '', Option::VALUE_OPTIONAL, '跳过重复文件', 1)
            ->addOption('app', '', Option::VALUE_OPTIONAL, '是否是APP', 'backend') //暂时无效
            ->addOption('title', '', Option::VALUE_OPTIONAL, '插件标题', '') 
            ->addOption('author', '', Option::VALUE_OPTIONAL, '插件作者', '') 
            ->addOption('description', '', Option::VALUE_OPTIONAL, '插件描述', '')
            ->addOption('ver', '', Option::VALUE_OPTIONAL, '插件版本', '')
            ->addOption('requires', '', Option::VALUE_OPTIONAL, '插件需求版本', '')
            ->setDescription('Curd Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['driver'] = $input->getOption('driver');
        $param['table'] = $input->getOption('table');
        $param['controller'] = $input->getOption('controller');
        $param['page'] = $input->getOption('page');
        $param['limit'] = $input->getOption('limit');
        $param['model'] = $input->getOption('model');
        $param['validate']  = $input->getOption('validate');
        $param['method']  = $input->getOption('method');
        $param['addon'] = $input->getOption('addon');        //区块 。插件名字
        $param['fields'] = $input->getOption('fields');//自定义显示字段
        $param['fieldslist'] = $input->getOption('fieldslist');//所有字段以及属性//wu
        $param['checkboxSuffix'] = $input->getOption('checkboxSuffix');
        $param['radioSuffix'] = $input->getOption('radioSuffix');
        $param['imageSuffix'] = $input->getOption('imageSuffix');
        $param['fileSuffix'] = $input->getOption('fileSuffix');
        $param['timeSuffix'] = $input->getOption('timeSuffix');
        $param['iconSuffix'] = $input->getOption('iconSuffix');
        $param['switchSuffix'] = $input->getOption('switchSuffix');
        $param['addon'] = $input->getOption('addon');
        $param['selectsSuffix'] = $input->getOption('selectsSuffix');
        $param['sortField'] = $input->getOption('sortField');
        $param['ignoreFields'] = $input->getOption('ignoreFields');
        $param['formFields'] = $input->getOption('formFields');
        $param['joinTable'] = $input->getOption('joinTable');
        $param['joinName'] = $input->getOption('joinName');
        $param['joinModel'] = $input->getOption('joinModel');
        $param['joinMethod'] = $input->getOption('joinMethod');
        $param['joinForeignKey'] = $input->getOption('joinForeignKey');
        $param['joinPrimaryKey'] = $input->getOption('joinPrimaryKey');
        $param['selectFields'] = $input->getOption('selectFields');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $param['menu'] = $input->getOption('menu');
        $param['menuname'] = $input->getOption('menuname');
        $param['menuid'] = $input->getOption('menuid');
        $param['jump'] = $input->getOption('jump');
        $param['app'] = $input->getOption('app');
        $param['title'] = $input->getOption('title');
        $param['author'] = $input->getOption('author');
        $param['description'] = $input->getOption('description');
        $param['version'] = $input->getOption('ver');
        $param['requires'] = $input->getOption('requires');
        if (empty($param['table'])) {
            $output->info("主表不能为空");
            return false;
        }
        $curdService = new CurdService($param);
        try {
            $curdService->maker();
            $output->info('make success');
        }catch (\Exception $e){
            $output->writeln('----------------');
            $output->error($e->getMessage());
            $output->writeln('----------------');
        }
    }
}
