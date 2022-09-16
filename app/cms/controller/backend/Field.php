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

namespace app\cms\controller\backend;

use app\cms\model\Field as FieldModel;
use app\common\model\FieldType;

use think\App;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\View;

class Field extends CmsBackend
{

    protected $sysfield = [];
    protected $moduleid = '';
    protected $diyformid = '';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new FieldModel();
        $this->sysfield = (new \app\cms\model\Module())->getTableColumn('addons_cms_forum','COLUMN_NAME,COLUMN_COMMENT');
        $this->moduleid = $this->request->param('moduleid/d')?$this->request->param('moduleid/d'):$this->request->get('moduleid/d');
        $this->diyformid = $this->request->param('diyformid/d')?$this->request->param('diyformid/d'):$this->request->get('diyformid/d');
    }
    /*
     * 字段列表
     */
    public function index(){
        if($this->request->isAjax()){
            //不可控字段
            if($this->moduleid){
                $list = Cache::get('forum_field'.$this->moduleid);
                if(!$list){
                    $arrfield = $this->modelClass->where("moduleid", $this->moduleid)
                        ->order('sort asc,id asc')
                        ->select()->toArray();
                    $arr = [];
                    foreach ($this->sysfield as $k=>$v){
                        $arr[$k]['field'] = $v['COLUMN_NAME'];
                        $arr[$k]['name'] = $v['COLUMN_COMMENT'];
                    }
                    $sysfield_content = [['field'=>"content",'name'=>'内容']];
                    $list = array_merge($arrfield,$arr,$sysfield_content);
                    Cache::tag('forum_field')->set('forum_field'.$this->moduleid,$list,7200);
                }
            }else{
                $list = Cache::get('diy_field'.$this->diyformid);
                if(!$list) {
                    $list = $this->modelClass->where("diyformid", $this->diyformid)
                        ->order('sort asc,id asc')
                        ->select()->toArray();
                    Cache::tag('forum_field')->set('diy_field'.$this->diyformid,$list,7200);
                }
            }
            foreach ($list as $k=>$v){
                if(in_array($v['field'],$this->sysfield)){
                    $list[$k]['del']=0;
                }else{
                    $list[$k]['del']=1;
                }
            }
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list, 'count' => count($list)];
        }
        $view = [
            'moduleid' => $this->moduleid,
            'diyformid' => $this->diyformid,
        ];
        View::assign($view);
        return view();
    }
    // 添加字段
    public function add(){
        if ($this->request->isAjax()) {
            //增加字段
            $post = $this->request->param();
            try{
                if($this->moduleid){
                    validate(\app\cms\validate\Field::class)
                        ->scene('module')
                        ->check($post);
                }else{
                    validate(\app\cms\validate\Field::class)
                        ->scene('diyform')
                        ->check($post);
                }
            }catch (ValidateException $e){
                $this->error($e->getMessage());
            }
            try {
                $this->modelClass->addField($post);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('add success'));
        }
        $view = [
            'moduleid'  => $this->moduleid,
            'diyformid'  => $this->diyformid,
            'formData'      => null,
            'title'=>lang('add'),
            'fieldType'=>FieldType::select(),
        ];
        View::assign($view);
        return view('add');
    }

    // 编辑字段
    public function edit(){
        $id = $this->request->param('id');
        if ($this->request->isAjax()) {
            //增加字段
            $post = $this->request->param();
            if($this->moduleid){
                validate(\addons\cms\backend\validate\Field::class)
                    ->scene('module')
                    ->check($post);
            }else{
                validate(\addons\cms\backend\validate\Field::class)
                    ->scene('diyform')
                    ->check($post);
            }
            try {
                $fieldid  = $post['id'];
                $this->modelClass->editField($post,$fieldid);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('add success'));
        }
        $formData =$this->findModel($id);
        $view = [
            'moduleid'  => $this->moduleid,
            'diyformid'  => $this->diyformid,
            'fieldType'=>FieldType::select(),
            'formData'      => $formData,
            'title'=>lang('edit'),
        ];
        View::assign($view);
        return view('add');
    }

    // 删除字段
    public function delete() {
        $fieldid  = $this->request->param('id');
        try {
            $this->modelClass->deleteField($fieldid);
        }catch(Exception $e){
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang('operation success'));
    }

}
