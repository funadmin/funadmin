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
 * Date: 2019/8/2
 */
namespace app\backend\controller\sys;
use app\common\controller\Backend;
use app\common\model\Config as ConfigModel;
use app\common\model\ConfigGroup as ConfigGroupModel;
use app\common\model\FieldType;
use app\common\traits\Curd;
use think\App;
use think\facade\Db;
use think\facade\View;
class Config extends Backend {
    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ConfigModel();
    }

    /**
     * @return \think\response\View
     * 设置
     */
    public function set(){
        if ($this->request->isPost()) {
            $data = $this->request->param();
            foreach ($data as $k=>$v){
                $res = $this->modelClass->where('code',$k)->update(['value'=>$v]);
            }
            $this->success(lang('Save Success'));
        }

        $group =  ['site','email','upload','sms'];
        $list = Db::name('config')
            ->where('group','in',$group)
            ->field('code,value')
            ->column('value','code');
        View::assign('formData',$list);
        return view();

    }
    //添加配置
    public function add(){
        if($this->request->isPost()){
            $data = $this->request->param();
            $rule = ['code|编码'=>"require|unique:config"];
            $this->validate($data, $rule);
            if($this->modelClass->save($data)){
                $this->success(lang('edit success'));
            }else{
                $this->error(lang('edit fail'));
            }

        }
        $list = '';
        $configGroup = Db::name('config_group')->select();
        $fieldType = FieldType::select();
        $view = ['title'=>lang('Add'),'formData'=>$list,'configGroup'=>$configGroup,'fieldType'=>$fieldType,];
        View::assign($view);
        return view();
    }
    //添加配置
    public function edit($id){
        if($this->request->isPost()){
            $list = $this->modelClass->find($id);
            if(empty($list)) $this->error(lang('Data is not exist'));
            if ($this->request->isPost()) {
                $post = $this->request->post();
                $rule = [];
                $this->validate($post, $rule);
                try {
                    $save = $list->save($post);
                } catch (\Exception $e) {
                    $this->error(lang('Save Failed'));
                }
                $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
            }
        }
        $list = $this->modelClass->find($this->request->param('id'));
        $configGroup = Db::name('config_group')->select();
        $fieldType = FieldType::select();
        $view = ['title'=>lang('Add'),'formData'=>$list,'configGroup'=>$configGroup,'fieldType'=>$fieldType,];
        View::assign($view);
        return view('add');
    }

    public function setValue(){


        
    }

}