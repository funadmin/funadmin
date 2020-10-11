<?php
/**
 * FunAadmin
 * ============================================================================
 * 版权所有 2017-2028 FunAadmin，并保留所有权利。
 * 网站地址: https://www.FunAadmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller\sys;
use app\common\controller\Backend;
use app\common\model\Config as ConfigModel;
use app\common\model\ConfigGroup as ConfigGroupModel;
use app\common\model\FieldType;
use app\common\traits\Curd;
use think\App;
use think\facade\View;

class ConfigGroup extends Backend {
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ConfigGroupModel();

    }

//    配置分组
    public function add(){
        if($this->request->isPost()){
            $post = $this->request->param();
            $rule = ['name|组名'=>'unique:config_group'];
            $this->validate($post, $rule);
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save failed'));
            }
            $save ? $this->success(lang('Save success')) : $this->error(lang('Save failed'));
        }
        $view = ['title'=>lang('Config Group'),'formData'=>''];
        View::assign($view);
        return view('add');

    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 删除
     */
    public function delete(){
        $id = $this->request->param('id')?$this->request->param('id'):$this->request->param('ids');
        $lists = $this->modelClass->where('id','in',$id)->select();
        if(!$lists){
            $this->error(lang('Data is not exist'));
        }
        foreach ($lists as $k=>$list){
            $config = ConfigModel::where('type',$list->name)->find();
            if($config){
                $this->error(lang('Group has config'));
            }else{
                if ($list->delete()) {
                    $this->success(lang('delete success'));
                }else{
                    $this->error(lang('delete fail'));

                }
            }
        }

    }

//
}