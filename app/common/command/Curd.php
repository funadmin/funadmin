<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */


namespace app\common\command;

use app\common\lib\BuildCurd;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;

class Curd extends Command
{
    protected function configure()
    {
        $this->setName('curd')
            ->addOption('table', 't', Option::VALUE_REQUIRED, 'table name without prefix', null)
            ->addOption('controller', 'c', Option::VALUE_REQUIRED, 'controller name', null)
            ->addOption('model', 'm', Option::VALUE_REQUIRED, 'model name', null)
            ->addOption('fields', 'i', Option::VALUE_OPTIONAL, 'model visible fields', null)
            ->addOption('force', 'f', Option::VALUE_REQUIRED, 'force override or force delete', 0)
            ->addOption('local', 'l', Option::VALUE_OPTIONAL, 'local model', 1)
            ->addOption('db', null, Option::VALUE_OPTIONAL, 'database config name', 'database')
            ->addOption('checkboxFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '复选框字段后缀', null)
            ->addOption('enumRadioFieldSuffix', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'automatically generate radio component with suffix', null)
            ->addOption('radioFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '单选框字段后缀', null)
            ->addOption('imageFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '单图片字段后缀', null)
            ->addOption('imagesFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '多图片字段后缀', null)
            ->addOption('fileFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '单文件字段后缀', null)
            ->addOption('filesFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '多文件字段后缀', null)
            ->addOption('dateFieldSuffix', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '时间字段后缀', null)
            ->addOption('switchFields', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '开关的字段', null)
            ->addOption('selectFileds', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '下拉的字段', null)
            ->addOption('editorFields', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '富文本的字段', null)
            ->addOption('sortFields', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '排序的字段', null)
            ->addOption('ignoreFields', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '忽略的字段', null)
            ->addOption('menu', 'u', Option::VALUE_OPTIONAL, 'create menu when CRUD completed', null)
            ->addOption('relationTable', 'r', Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'relation table name without prefix', null)
            ->addOption('relationmodel', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, 'relation model name', null)
            ->addOption('relationforeignkey', 'k', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation foreign key', null)
            ->addOption('relationprimarykey', 'p', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'relation primary key', null)  ->addOption('relationOnlyFileds', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联模型中只显示的字段', null)
            ->addOption('relationBindSelect', null, Option::VALUE_REQUIRED | Option::VALUE_IS_ARRAY, '关联模型中的字段用于主表外键的表单下拉选择', null)
            ->addOption('delete', 'd', Option::VALUE_REQUIRED, 'delete by CRUD', 0)
            ->addOption('db', null, Option::VALUE_OPTIONAL, 'database config name', 'database')
            ->setDescription('CURD SERVICE');
    }

    protected function execute(Input $input, Output $output)
    {
        //数据库
        $table = $input->getOption('table') ?? '';
        if (!$table) {
            throw new Exception('table name can\'t empty');
        }
        $local = $input->getOption('local');
        //是否生成菜单
        $menu = $input->getOption("menu");
        $controller = $input->getOption('controller')??"";
        $model = $input->getOption('model')??"";
        $fields = $input->getOption('fields')??"";
        $checkboxFieldSuffix = $input->getOption('checkboxFieldSuffix')??"";
        $enumRadioFieldSuffix = $input->getOption('enumRadioFieldSuffix')??"";
        $radioFieldSuffix = $input->getOption('radioFieldSuffix')??"";
        $imageFieldSuffix = $input->getOption('imageFieldSuffix')??"";
        $imagesFieldSuffix = $input->getOption('imagesFieldSuffix')??"";
        $fileFieldSuffix = $input->getOption('fileFieldSuffix')??"";
        $filesFieldSuffix = $input->getOption('filesFieldSuffix');
        $dateFieldSuffix = $input->getOption('dateFieldSuffix');
        $switchFields = $input->getOption('switchFields');
        $selectFileds = $input->getOption('selectFileds');
        $sortFields = $input->getOption('sortFields');
        $ignoreFields = $input->getOption('ignoreFields');
        $relationTable = $input->getOption('relationTable');
        $relationmodel = $input->getOption('relationmodel');
        $relationforeignkey = $input->getOption('relationforeignkey');
        $relationprimarykey = $input->getOption('relationprimarykey');
        $relationOnlyFileds = $input->getOption('relationOnlyFileds');
        $relationBindSelect = $input->getOption('relationBindSelect');

        $force = $input->getOption('force');
        $delete = $input->getOption('delete');

        $relations = [];
        foreach ($relationTable as $key => $val) {
            $relations[] = [
                'table'         => $relationTable[$key],
                'foreignKey'    => isset($relationforeignkey[$key]) ? $relationforeignkey[$key] : null,
                'primaryKey'    => isset($relationprimarykey[$key]) ? $relationprimarykey[$key] : null,
                'model' => isset($relationmodel[$key]) ? $relationmodel[$key] : null,
                'onlyFileds'    => isset($relationOnlyFileds[$key]) ? explode(",", $relationOnlyFileds[$key]) : [],
                'relationBindSelect' => isset($relationBindSelect[$key]) ? $relationBindSelect[$key] : null,
            ];
        }


        try {
            $build = (new BuildCurd())
                ->setTable($table)
                ->setForce($force);

            !empty($controller) && $build = $build->setcontroller($controller);
            !empty($model) && $build = $build->setmodel($model);
            !empty($fields) && $build = $build->se($fields);

            !empty($checkboxFieldSuffix) && $build = $build->setCheckboxFieldSuffix($checkboxFieldSuffix);
            !empty($radioFieldSuffix) && $build = $build->setRadioFieldSuffix($radioFieldSuffix);
            !empty($imageFieldSuffix) && $build = $build->setImageFieldSuffix($imageFieldSuffix);
            !empty($imagesFieldSuffix) && $build = $build->setImagesFieldSuffix($imagesFieldSuffix);
            !empty($fileFieldSuffix) && $build = $build->setFileFieldSuffix($fileFieldSuffix);
            !empty($filesFieldSuffix) && $build = $build->setFilesFieldSuffix($filesFieldSuffix);
            !empty($dateFieldSuffix) && $build = $build->setDateFieldSuffix($dateFieldSuffix);
            !empty($switchFields) && $build = $build->setSwitchFields($switchFields);
            !empty($selectFileds) && $build = $build->setSelectFileds($selectFileds);
            !empty($sortFields) && $build = $build->setSortFields($sortFields);
            !empty($ignoreFields) && $build = $build->setIgnoreFields($ignoreFields);

            foreach ($relations as $relation) {
                $build = $build->setRelation($relation['table'], $relation['foreignKey'], $relation['primaryKey'], $relation['model'], $relation['onlyFileds'],$relation['relationBindSelect']);
            }

            $build = $build->render();
            $fileList = $build->getFileList();

            if (!$delete) {
                $result = $build->create();
                if($force){
                    $output->info(">>>>>>>>>>>>>>>");
                    foreach ($fileList as $key => $val) {
                        $output->info($key);
                    }
                    $output->info(">>>>>>>>>>>>>>>");
                    $output->info("确定强制生成上方所有文件? 如果文件存在会直接覆盖。 请输入 'yes' 按回车键继续操作: ");
                    $line = fgets(defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
                    if (trim($line) != 'yes') {
                        throw new Exception("取消文件CURD生成操作");
                    }
                }
                CliEcho::success('自动生成CURD成功');
            } else {
                $output->info(">>>>>>>>>>>>>>>");
                foreach ($fileList as $key => $val) {
                    $output->info($key);
                }
                $output->info(">>>>>>>>>>>>>>>");
                $output->info("确定删除上方所有文件?  请输入 'yes' 按回车键继续操作: ");
                $line = fgets(defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
                if (trim($line) != 'yes') {
                    throw new Exception("取消删除文件操作");
                }
                $result = $build->delete();
                CliEcho::success('>>>>>>>>>>>>>>>');
                CliEcho::success('删除自动生成CURD文件成功');
            }
            CliEcho::success('>>>>>>>>>>>>>>>');
            foreach ($result as $vo) {
                CliEcho::success($vo);
            }
        } catch (\Exception $e) {
            CliEcho::error($e->getMessage());
            return false;
        }
    }


}