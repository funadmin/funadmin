<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
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
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="配置组")
 * Class ConfigGroup
 * @package app\backend\controller\sys
 */
class ConfigGroup extends Backend {

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ConfigGroupModel();

    }

    /**
     * @NodeAnnotation(title="添加")
     * @return \think\response\View
     */
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
     * @NodeAnnotation(title="删除")
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
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
                try {
                    $list->force()->delete();
                } catch (\Exception $e) {
                    $this->error(lang("operation failed"));
                }
            }
        }
        $this->success(lang('operation success'));
    }

}