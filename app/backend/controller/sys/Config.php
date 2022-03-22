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
use app\common\model\FieldVerify;
use app\common\traits\Curd;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation('配置')
 * Class Config
 * @package app\backend\controller\sys
 */
class Config extends Backend {


    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ConfigModel();
    }

    /**
     * @NodeAnnotation(title="设置")
     * @return \think\response\View
     *
     */
    public function set(){
        if ($this->request->isPost()) {
            $post = $this->request->param();
            foreach ($post as $k=>$v){
                $res = $this->modelClass->where('code',$k)->update(['value'=>$v]);
            }
            Cache::clear();
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

    /**
     * @NodeAnnotation(title="添加")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add(){
        if($this->request->isPost()){
            $post = $this->request->param();
            $rule = ['code|编码'=>"require|unique:config"];
            $this->validate($post, $rule);
            if($this->modelClass->save($post)){
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('edit fail'));
            }

        }
        $list = '';
        $configGroup = Db::name('config_group')->select();
        $fieldType = FieldType::select()->toArray();
        $fieldVerify = FieldVerify::select()->toArray();
        $view = ['title'=>lang('Edit'),'fieldVerify'=>$fieldVerify,'formData'=>$list,'configGroup'=>$configGroup,'fieldType'=>$fieldType,];
        View::assign($view);
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑配置")
     * @param $id
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     */
    public function edit(){
        $id  = $this->request->get('id');
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
        $configGroup = ConfigGroupModel::select();
        $fieldType = FieldType::select()->toArray();
        $fieldVerify = FieldVerify::select()->toArray();
        $view = ['title'=>lang('Add'),'fieldVerify'=>$fieldVerify,'formData'=>$list,'configGroup'=>$configGroup,'fieldType'=>$fieldType,];
        View::assign($view);
        return view('edit');
    }

    /**
     * @NodeAnnotation(title="设置值")
     * @param $id
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function setValue($id){

        if($this->request->isPost()){
            $list = $this->modelClass->find($id);
            if(empty($list)) $this->error(lang('Data is not exist'));
            if ($this->request->isPost()) {
                $post = $this->request->post();
                $rule = [];
                $this->validate($post, $rule);
                $post['value'] = $this->buildValue($list,$post);
                try {
                    $save = $list->save($post);
                } catch (\Exception $e) {
                    $this->error(lang('Save Failed'));
                }
                Cache::clear();
                $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
            }
        }
        $list = $this->modelClass->find($this->request->param('id'));
        $configGroup = ConfigGroupModel::select();
        $fieldType = FieldType::select()->toArray();
        $fieldVerify = FieldVerify::select()->toArray();
        $view = ['title'=>lang('Add'),'fieldVerify'=>$fieldVerify,'formData'=>$list,'configGroup'=>$configGroup,'fieldType'=>$fieldType,];
        View::assign($view);
        return view();
        
    }

    protected function buildValue($list,$post){
        switch ($list->type){
            case 'checkbox':
                $value = [];
                if(isset($post['value'])){
                    foreach ($post['value'] as $k => $v) {
                        $value[] = $k;
                    }
                    $value = implode("\n", $value);

                }
                break;
            case 'switch':
                if(isset($post['value']) && $post['value']== 'on') $value = 1;
                if(!isset($post['value'])) $value = 0;
                break;
            case 'array':
                $value = $post['value'];
                break;
            case 'datetime':
                $value = $post['value'];
                break;
            case 'range':
                $value = $post['value'];
                break;
            default:
                $value = $post['value'];
                break;
        }
        return $value;

    }

}