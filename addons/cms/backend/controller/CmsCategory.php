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
 * Date: 2019/10/12
 */
namespace  addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsField;
use addons\cms\common\model\CmsTags;
use addons\cms\common\model\CmsModule;
use addons\cms\common\model\CmsCategory as CModel;
use app\common\traits\Curd;
use fun\helper\ArrayHelper;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class CmsCategory extends AddonsBackend
{
    use Curd;
    public $filepath;
    public $_category;
    public $_list;
    public $_show;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CModel();
        //取得当前内容模型模板存放目录
        $this->filepath = $this->addon_path.'view'.DIRECTORY_SEPARATOR.'frontend' . DIRECTORY_SEPARATOR;
        //取得栏目频道模板列表
        $this->_category = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'category*'));
        //取得栏目列表模板列表
        $this->_list = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'list*'));
        //取得内容页模板列表
        $this->_show = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'show*'));

    }

    /*-----------------------栏目管理----------------------*/
    // 栏目列表
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            $count = $this->modelClass
                ->where($where)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page,$this->pageSize)
                ->select()->toArray();
            $list = TreeHelper::cateTree($list,'catename');
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        $this->modelClass->fixCate();
        return view();

    }

    // 添加栏目
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $moduleid = $this->request->post('moduleid');
            $module = CmsModule::find($moduleid);
            if($module){
                $post['module'] = $module->name;
            }else{
                $this->error('模型不存在');
            }

            //添加
            $result = CModel::create($post);
            CModel::fixCate();

            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $formData = '';
            $colGroup = $this->modelClass->select()->toArray();
            $colGroup = TreeHelper::cateTree($colGroup,'catename');
            $moduleGroup = CmsModule::select()->toArray();
            foreach($moduleGroup as $k=>$v){
                $moduleGroup[$k]['lay_is_open']=false;
            }
            $params['name'] = 'container';
            $params['content'] = '';
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
                '_category'=>$this->_category,
                '_list'=>$this->_list,
                '_show'=>$this->_show,
                'ueditor'=>build_ueditor($params),
            ];

            View::assign($view);
            return view();
        }
    }

    /**
     * 栏目修改
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $moduleid = $this->request->post('moduleid');
            $module = CmsModule::find($moduleid);
            if($module){
                $post['module'] = $module->name;
            }else{
               $this->error(lang('module is not exist'));
            }
            if(CModel::update($post)){
                $this->modelClass->fixCate();
                $this->success(lang('edit success'), url('index'));
            }else{
                $this->error(lang('edit fail'));
            }

        }
        $id = $this->request->param('id');
        if ($id) {
            $colGroup = $this->modelClass->select()->toArray();
            $colGroup = TreeHelper::cateTree($colGroup,'catename');
            $moduleGroup = CmsModule::select()->toArray();
            $formData = CModel::find($id);
            $params['name'] = 'container';
            $params['content'] = $formData['content']?$formData['content']:'';
            $view = [
                'formData' => $formData,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
                'title' => '编辑',
                '_category'=>$this->_category,
                '_list'=>$this->_list,
                '_show'=>$this->_show,
                'ueditor'=>build_ueditor($params),
            ];
            View::assign($view);
            return view('add');
        }

    }


    // 栏目删除
    public function delete()
    {
        $id = $this->request->post('id');
        CModel::destroy($id);
        CModel::fixCate();
        $this->success(lang('delete success'));

    }

    // 栏目状态修改
    public function state()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $field = $this->request->post('field');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $formData = CModel::find($id);
            $formData->$field = $formData->$field?0:1;
            $formData->save();
            $this->success(lang('edit success'));
        }
    }



    /**--------------------------------------------------------栏目内容管理----------------------------------------------------**/
    public function lists(){
        $cate = cache('category_list');
        if(!$cate){
            $cate = CModel::field('id,pid,catename,type')->order('sort asc,id asc')->select()->toArray();
            $cate = $this->_cateTree($cate);
            cache('category_list',$cate);
        }
        $idList = CModel::column('id');
        sort($idList);
        return view('',['list'=>$cate,'idList'=>$idList]);

    }

    //内容页面
    public function content(){
        $cateId = input('cateid');
        if(!$cateId){
            $view = 'board';
        }
        $cate = $this->modelClass->find($cateId);
        $moduleid = $cate['moduleid'];
        $module = CmsModule::find($moduleid);
        $title =$cate->catename;
        $formData = [];
        if($cate->type==0){//列表
            if($this->request->isPost()){
                $keys = $this->request->post('keys','','trim');
                $page = $this->request->post('page') ? $this->request->param('page') : 1;
                $formData = Db::name($module->name)->where('title','like','%'.$keys.'%')
                    ->order('id desc,sort desc')
                    ->where('cateid',$cate->id)
                    ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                    ->toArray();
                return $result = ['code'=>0,'msg'=>lang('get formData success'),'data'=>$formData['data'],'count'=>$formData['total']];
            }
            $view = 'content';
            return view($view,['title'=>$title,'formData'=>$formData,'cate'=>$cate]);

        }else{//单页
            //添加单页内容
            if($this->request->isPost()) {
                $post = input('post.');
                $cate = $this->modelClass->find($post['cateid']);
                $module = CmsModule::find( $cate['moduleid']);
                $post['create_time'] = time();
                unset($post['file']);
                if(!$post['id']){
                    $id= $post['id'];
                    unset($post['id']);
                   $res = Db::name($module->name)->save($post);
                }else{
                    $res = $id =  Db::name($module->name)->insertGetId($post);
                }
                if($res){
                    if(isset($post['tags']) and $post['tags']){
                        if($module->name=='addons_cms_article'){
                            $tagModel = new CmsTags();
                            $tagModel->addTags($post['tags'],$id);
                        }

                    }
                    $this->success('添加成功');
                }else{
                    $this->error('添加失败');
                }
            }
            $formData =  $formData = Db::name($module->name)->where('cateid',$cateId)->find();
            $view = 'page';
            $params['name'] = 'container';
            $params['content'] = $formData['content']?$formData['content']:'';
            return view($view,['title'=>$title,'formData'=>$formData,'cate'=>$cate, 'ueditor'=>build_ueditor($params),]);
        }

    }

    //添加信息
    public function addformData(){
        $cateId = input('cateid');
        $id = input('id');
        $cate = $this->modelClass->find($cateId);
        $moduleid = $cate['moduleid'];
        $module = CmsModule::find($moduleid);
        $title =$cate->catename;
        if($this->request->isPost()){
            $post = input('post.');
            if(isset($post['file'])) unset($post['file']);
            if($id){
                unset($post['id']);
                $post['update_time'] = time();
                $res =  Db::name($module->name)->where('id', $id)->update($post);
            }else{
                $post['create_time'] = time();
                $res = $id =  Db::name($module->name)->insertGetId($post);
            }
           if($res){
               if(isset($post['tags']) and $post['tags']){
                   if($module->name=='cms_article'){
                       $tagModel = new CmsTags();
                       $tagModel->addTags($post['tags'],$id);
                   }
               }
               $this->success('操作成功');
           }else{
               $this->error('操作失败');
           }

        }
        $fieldList = CmsField::where('moduleid',$moduleid)->cache(3600)->select()->toArray();
        foreach ($fieldList as $k=>$v){
            if($fieldList[$k]['option']){
                $fieldList[$k]['option'] = ArrayHelper::parseToarr( $fieldList[$k]['option'] );
            }
        }
        $formData = [];
        if($id){
            $formData =  Db::name($module->name)->find($id);
            $params['name'] = 'container';
            $params['content'] = isset($formData['content'])?$formData['content']:'';
            foreach ($fieldList as $k=>$v){
                $fieldList[$k]['value'] = $formData[$v['field']];
//                $fieldList[$k]['create_time'] = date('Y-m-d H:i:s', $formData['create_time']);
//                $fieldList[$k]['update_time'] = date('Y-m-d H:i:s', $formData[$v['update_time']]);
            }
        }else{
            $params['name'] = 'container';
            $params['content'] = isset($formData['content'])?$formData['content']:'';

        }
        return view('',['cate'=>$cate,'fieldList'=>$fieldList,'title'=>$title,'formData'=>$formData,'ueditor'=>build_ueditor($params)]);
    }

    //面板
    public function board(){
        $formData['category'] = $this->modelClass->count();
        $formData['module'] = CmsModule::count();

        return view('board',['formData'=>$formData]);
    }
    // 状态修改
    public function contentState()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $field = $this->request->param('field');
            $cateId = $this->request->param('cateid');
            $cate = $this->modelClass->find($cateId);
            $moduleid = $cate['moduleid'];
            $module = CmsModule::find($moduleid);
            $formData =  Db::name($module->name)->find($id);
            $post[$field] = $formData[$field]?0:1;
            $res =  Db::name($module->name)->where('id', $id)->update($post);
            if($res) $this->success(lang('edit success'));
            $this->error("edit fail");
        }
    }

    public function contentDel()
    {
        if ($this->request->isPost()) {
            $id = $this->request->param('id');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $field = $this->request->param('field');
            $cateId = $this->request->param('cateid');
            $cate = $this->modelClass->find($cateId);
            $moduleid = $cate['moduleid'];
            $module = CmsModule::find($moduleid);
            $res =  Db::name($module->name)->delete($id);
            if($res) $this->success(lang('edit success'));
            $this->error("edit fail");
        }
    }

    // 刷新缓存
    public function flashCache(){

        $this->modelClass->flashCache()? $this->success(lang('Clear Success')) : $this->error(lang('Clear Fail'));
    }


    //树形组件分类
    protected function _cateTree($cate,$pid=0){
        $list = [];
        foreach ($cate as $v){
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                $v['title'] = $v['catename'];
                $v['href'] = (string)url('content',['cateid'=>$v['id']]);
                if($this->_cateTree($cate,$v['id'])){
                    $v['children'] =$this->_cateTree($cate,$v['id']);
                }
                $list[] = $v;
            }
        }
        return $list;

    }

}