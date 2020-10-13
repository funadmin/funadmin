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
namespace  addons\cms\backend\controller\cms;

use app\common\controller\Backend;
use app\common\model\CmsField;
use app\common\model\CmsTags;
use app\common\traits\Curd;
use lemo\helper\ArrayHelper;
use lemo\helper\TreeHelper;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use app\common\model\CmsCategory as CModel;

class CmsCategory extends Backend
{
    use Curd;
    public $filepath;
    public $_category;
    public $_list;
    public $_show;
    public function initialize()
    {
        parent::initialize();
        //取得当前内容模型模板存放目录
        $this->filepath = app()->getRootPath().'app/cms/view' . DIRECTORY_SEPARATOR;
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
        if(Request::isPost()){
            $keys = Request::post('keys','','trim');
            $page = Request::post('page') ? Request::post('page') : 1;
            $list = CModel::where('title','like','%'.$keys.'%')
                ->order('id desc,sort desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            foreach($list['data'] as $k=>$v){
                $list['data'][$k]['lay_is_open']=true;
            }
            return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$list['data'],'count'=>$list['total']];
        }
        CModel::fixCate();
        return view();

    }

    // 添加栏目
    public function add()
    {
        if (Request::isPost()) {
            $data = Request::post();
            $moduleid = Request::post('moduleid');
            $module = \app\common\model\CmsModule::find($moduleid);
            $moduleid = Request::post('moduleid');
            $module = \app\common\model\CmsModule::find($moduleid);
            if($module){
                $data['module'] = $module->name;
            }else{
                $this->error('模型不存在');
            }

            //添加
            $result = CModel::create($data);
            CModel::fixCate();

            if ($result) {
                $this->success(lang('add success'), url('index'));
            } else {
                $this->error(lang('add fail'));
            }
        } else {
            $info = '';
            $colGroup = CModel::select();
            $colGroup = TreeHelper::categoryTree($colGroup);
            $moduleGroup = \app\common\model\CmsModule::select()->toArray();
            foreach($moduleGroup as $k=>$v){
                $moduleGroup[$k]['lay_is_open']=false;
            }
            $params['name'] = 'container';
            $params['content'] = '';
            $view = [
                'info' => $info,
                'colGroup' => $colGroup,
                'moduleGroup' => $moduleGroup,
                'title' => '添加',
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
        if (Request::isPost()) {
            $data = Request::post();
            $moduleid = Request::post('moduleid');
            $module = \app\common\model\CmsModule::find($moduleid);
            if($module){
                $data['module'] = $module->name;
            }else{
               $this->error(lang('module is not exist'));
            }
            if(CModel::update($data)){
                CModel::fixCate();
                $this->success(lang('edit success'), url('index'));
            }else{
                $this->error(lang('edit fail'));
            }

        }
        $id = Request::param('id');
        if ($id) {
            $colGroup = CModel::select();
            $colGroup = TreeHelper::categoryTree($colGroup);
            $moduleGroup = \app\common\model\CmsModule::select()->toArray();
            $info = CModel::find($id);
            $params['name'] = 'container';
            $params['content'] = $info['content']?$info['content']:'';
            $view = [
                'info' => $info,
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
        $id = Request::post('id');
        CModel::destroy($id);
        CModel::fixCate();
        $this->success(lang('delete success'));

    }

    // 栏目状态修改
    public function state()
    {
        if (Request::isPost()) {
            $id = Request::post('id');
            $field = Request::post('field');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $info = CModel::find($id);
            $info->$field = $info->$field?0:1;
            $info->save();
            $this->success(lang('edit success'));
        }
    }



    /**--------------------------------------------------------栏目内容管理----------------------------------------------------**/
    public function list(){
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
        $cate = CModel::find($cateId);
        $moduleid = $cate['moduleid'];
        $module = \app\common\model\CmsModule::find($moduleid);
        $title =$cate->catename;
        $info = [];
        if($cate->type==0){//列表
            if($this->request->isPost()){
                $keys = Request::post('keys','','trim');
                $page = Request::post('page') ? Request::param('page') : 1;
                $info = Db::name($module->name)->where('title','like','%'.$keys.'%')
                    ->order('id desc,sort desc')
                    ->where('cateid',$cate->id)
                    ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                    ->toArray();
                return $result = ['code'=>0,'msg'=>lang('get info success'),'data'=>$info['data'],'count'=>$info['total']];
            }
            $view = 'content';
            return view($view,['title'=>$title,'info'=>$info,'cate'=>$cate]);

        }else{//单页
            //添加单页内容
            if($this->request->isPost()) {
                $data = input('post.');
                $cate = CModel::find($data['cateid']);
                $module = \app\common\model\CmsModule::find( $cate['moduleid']);
                $data['create_time'] = time();
                unset($data['file']);
                if(!$data['id']){
                    $id= $data['id'];
                    unset($data['id']);
                   $res = Db::name($module->name)->save($data);
                }else{
                    $res = $id =  Db::name($module->name)->insertGetId($data);
                }
                if($res){
                    if(isset($data['tags']) and $data['tags']){
                        if($module->name=='cms_article'){
                            $tagModel = new CmsTags();
                            $tagModel->addTags($data['tags'],$id);
                        }

                    }
                    $this->success('添加成功');
                }else{
                    $this->error('添加失败');
                }
            }
            $info =  $info = Db::name($module->name)->where('cateid',$cateId)->find();
            $view = 'page';
            $params['name'] = 'container';
            $params['content'] = $info['content']?$info['content']:'';
            return view($view,['title'=>$title,'info'=>$info,'cate'=>$cate, 'ueditor'=>build_ueditor($params),]);
        }

    }

    //添加信息
    public function addinfo(){
        $cateId = input('cateid');
        $id = input('id');
        $cate = CModel::find($cateId);
        $moduleid = $cate['moduleid'];
        $module = \app\common\model\CmsModule::find($moduleid);
        $title =$cate->catename;
        if($this->request->isPost()){
            $data = input('post.');
            if(isset($data['file'])) unset($data['file']);
            if($id){
                unset($data['id']);
                $data['update_time'] = time();
                $res =  Db::name($module->name)->where('id', $id)->update($data);
            }else{
                $data['create_time'] = time();
                $res = $id =  Db::name($module->name)->insertGetId($data);
            }
           if($res){
               if(isset($data['tags']) and $data['tags']){
                   if($module->name=='cms_article'){
                       $tagModel = new CmsTags();
                       $tagModel->addTags($data['tags'],$id);
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
        $info = [];
        if($id){
            $info =  Db::name($module->name)->find($id);
            $params['name'] = 'container';
            $params['content'] = isset($info['content'])?$info['content']:'';
            foreach ($fieldList as $k=>$v){
                $fieldList[$k]['value'] = $info[$v['field']];
//                $fieldList[$k]['create_time'] = date('Y-m-d H:i:s', $info['create_time']);
//                $fieldList[$k]['update_time'] = date('Y-m-d H:i:s', $info[$v['update_time']]);
            }
        }else{
            $params['name'] = 'container';
            $params['content'] = isset($info['content'])?$info['content']:'';

        }
        return view('',['cate'=>$cate,'fieldList'=>$fieldList,'title'=>$title,'info'=>$info,'ueditor'=>build_ueditor($params)]);
    }

    //面板
    public function board(){
        $info['category'] = CModel::count();
        $info['module'] =\app\common\model\CmsModule::count();

        return view('board',['info'=>$info]);
    }
    // 状态修改
    public function contentState()
    {
        if (Request::isPost()) {
            $id = Request::post('id');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $field = Request::param('field');
            $cateId = Request::param('cateid');
            $cate = CModel::find($cateId);
            $moduleid = $cate['moduleid'];
            $module = \app\common\model\CmsModule::find($moduleid);
            $info =  Db::name($module->name)->find($id);
            $data[$field] = $info[$field]?0:1;
            $res =  Db::name($module->name)->where('id', $id)->update($data);
            if($res) $this->success(lang('edit success'));
            $this->error("edit fail");
        }
    }

    public function contentDel()
    {
        if (Request::isPost()) {
            $id = Request::param('id');
            if (empty($id)) {
                $this->error('id' . lang('not exist'));
            }
            $field = Request::param('field');
            $cateId = Request::param('cateid');
            $cate = CModel::find($cateId);
            $moduleid = $cate['moduleid'];
            $module = \app\common\model\CmsModule::find($moduleid);
            $res =  Db::name($module->name)->delete($id);
            if($res) $this->success(lang('edit success'));
            $this->error("edit fail");
        }
    }

    // 刷新缓存
    public function flashCache(){

        CModel::flashCache();
        $this->success(lang('清理成功'));
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