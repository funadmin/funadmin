<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use think\facade\Db;
use think\App;
use addons\cms\common\model\CmsDiyform as CmsDiyformModel;
use think\Validate;

class CmsDiyform extends AddonsBackend
{
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsDiyformModel();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                "tablename|数据表名" => 'require|unique:addons_cms_diyform',
                "name|表单名" => 'require',
                "template|模板" => 'require',
            ];
            try {
                $this->validate($post, $rule);
            }catch (\ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            $tablename = $this->modelClass->get_addonstablename($this->request->param('tablename/s'),$this->addon);
            if(Db::query("SHOW TABLES LIKE '{$tablename}'")) $this->error(lang('table is already exist'));
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('operation failed'));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = [
            'formData' => '',
        ];
        return view('',$view);
    }

    public function field(){

        return view();
    }

    public function data(){


        return view();
    }
}