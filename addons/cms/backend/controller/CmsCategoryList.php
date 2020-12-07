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

use addons\cms\common\model\CmsAlbum;
use addons\cms\common\model\CmsFiling;
use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsField;
use addons\cms\common\model\CmsTags;
use addons\cms\common\model\CmsModule;
use addons\cms\common\model\CmsCategory as CategoryModel;
use app\common\traits\Curd;
use fun\helper\ArrayHelper;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Cache;

class CmsCategoryList extends AddonsBackend
{
    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CategoryModel();
    }
    /**--------------------------------------------------------栏目内容管理----------------------------------------------------**/
    public function index(){
        if($this->request->isAjax()){
            $cateid = $this->request->param('cateid');
            if($this->request->isAjax()){
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                $list =CmsFiling::where($where)
//                    ->where(['cateid'=>$cate->id])
                    ->order($sort)
                    ->paginate(['list_rows' => $this->pageSize, 'page' => $this->page])
                    ->toArray();
                return $result = ['code'=>0,'msg'=>lang('operation success'),'data'=>$list['data'],'count'=>$list['total']];
            }
        }
        $cate =  json_decode(Cache::get('category_list'));
        if(!$cate){
            $cate = $this->modelClass->field('id,pid,catename,type')
                ->order('sort asc,id asc')
                ->select()->toArray();
            $cate = $this->_cateTree($cate);
            Cache::set('category_list',json_encode($cate));
        }
        $idList = $this->modelClass->column('id');
        sort($idList);
        return view('',['list'=>$cate,'idList'=>$idList]);

    }
    //内容页面
    public function list(){
        $cateId = input('cateid');
        if(!$cateId){$view = 'board';}
        $cate = $this->modelClass->find($cateId);
        $moduleid = $cate['moduleid'];
        $module = CmsModule::find($moduleid);
        $albumlist = CmsAlbum::where('status',1)->column('title','id');
        $formData = [];
        $cmsfilingModel =new CmsFiling();
        if($cate->type==1){//列表
            if($this->request->isAjax()){
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                $list =CmsFiling::where($where)
                    ->where(['cateid'=>$cate->id])
                    ->order($sort)
                    ->paginate(['list_rows' => $this->pageSize, 'page' => $this->page])
                    ->toArray();
                return $result = ['code'=>0,'msg'=>lang('operation success'),'data'=>$list['data'],'count'=>$list['total']];
            }
            $view = 'list';
        }elseif($cate->type==2 || $cate->type==4){//单页
            //添加单页内容
            if($this->request->isPost()) {
                $post = input('post.');
                $post['create_time'] = time();
                $res =  $cmsfilingModel->save($post);
                if($res){
                    if(isset($post['tags']) and $post['tags']){
                        if($module->tablename=='addons_cms_article'){
                            $tagModel = new CmsTags();
                            $tagModel->addTags($post['tags'],$res->id);
                        }
                    }
                    $this->success(lang('operation success'));
                }else{
                    $this->error(lang('operation failed'));
                }
            }
            $formData =  $cmsfilingModel->where('cateid',$cateId)->find();
            if($formData){
                $addonscontent = Db::name($module->tablename)->find($formData->id);
                $addonscontent?$formData->setData($addonscontent):'';
            }
            $view = 'page';
        }
        return view($view,['formData'=>$formData,'cate'=>$cate,'albumlist'=>$albumlist]);
    }

    /**
     * 面板
     * @return \think\response\View
     */
    public function board(){
        $formData['category'] = $this->modelClass->count();
        $formData['module'] = CmsModule::count();
        return view('board',['formData'=>$formData]);
    }

    /**
     * 添加信息
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add(){
        $cateId = input('cateid');
        $id = input('id');
        $cate = $this->modelClass->find($cateId);
        $moduleid = $cate['moduleid'];
        $module = CmsModule::find($moduleid);
        $title =$cate->catename;
        $albumlist = CmsAlbum::where('status',1)->column('title','id');
        if($this->request->isPost()){
            $post = input('post.');
            if(isset($post['file'])) unset($post['file']);
            if($id){
                unset($post['id']);
                $post['update_time'] = time();
                $rule = ['title'=>"require"];
                try {
                    $this->validate($post,$rule);
                }catch (ValidateException $e){
                    $this->error($e->getMessage());
                }
                unset($post['__token__']);
                $res =  CmsFiling::where('id', $id)->update($post);
            }else{
                $post['create_time'] = time();
                unset($post['__token__']);
                $res = $id =  CmsFiling::insertGetId($post);
            }
            if($res){
                if(isset($post['tags']) and $post['tags']){
                    $tagModel = new CmsTags();
                    $tagModel->addTags($post['tags'],$id);
                }
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('operation failed'));
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
            $formData =  CmsFiling::find($id);
            if($formData){
                $data  = Db::name($module->tablename)->find($id);
                $formData->setData($data);
            }
            foreach ($fieldList as $k=>$v){
                $fieldList[$k]['value'] = $formData[$v['field']];
            }
        }
        return view('',['cate'=>$cate,'fieldList'=>$fieldList,'title'=>$title,'formData'=>$formData,'albumlist'=>$albumlist]);
    }

    /**编辑
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit(){
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
                $rule = ['title'=>"require"];
                try {
                    $this->validate($post,$rule);
                }catch (ValidateException $e){
                    $this->error($e->getMessage());
                }
                unset($post['__token__']);
                $res =  CmsFiling::where('id', $id)->update($post);
            }else{
                $post['create_time'] = time();
                unset($post['__token__']);
                $res = $id =  CmsFiling::insertGetId($post);
            }
            if($res){
                if(isset($post['tags']) and $post['tags']){
                    $tagModel = new CmsTags();
                    $tagModel->addTags($post['tags'],$id);
                }
                $this->success(lang('operation success'));
            }else{
                $this->error(lang('operation failed'));
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
            $formData =  CmsFiling::find($id);
            if($formData){
                $data  = Db::name($module->tablename)->find($id);
                $formData->setData($data);
            }
            foreach ($fieldList as $k=>$v){
                $fieldList[$k]['value'] = $formData[$v['field']];
            }
        }
        return view('',['cate'=>$cate,'fieldList'=>$fieldList,'title'=>$title,'formData'=>$formData,]);
    }

    /**
     * 操作
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function modify()
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
            $formData =  Db::name($module->tablename)->find($id);
            $post[$field] = $formData[$field]?0:1;
            $res =  Db::name($module->tablename)->where('id', $id)->update($post);
            if($res) $this->success("Edit Success");
            $this->error(lang("operation failed"));
        }
    }

    public function delete()
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
            $res =  Db::name($module->tablename)->delete($id);
            if($res) $this->success(lang('operation success'));
            $this->error("operation failed");
        }
    }

    // 刷新缓存
    public function flashCache()
    {

        $this->modelClass->flashCache() ? $this->success(lang('operation Success')) : $this->error(lang('operation failed'));
    }

    //树形组件分类
    protected function _cateTree($cate, $pid = 0)
    {

        $list = [];
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                $v['title'] = $v['catename'];
                if($v['type']==1 ||$v['type']==2){ //1 列表2 单页，3 外联，4 封面
                    $v['href'] = (string)addons_url('list', ['cateid' => $v['id']]);
                }
                if ($this->_cateTree($cate, $v['id'])) {
                    $v['children'] = $this->_cateTree($cate, $v['id']);
                }
                $list[] = $v;
            }
        }
        return $list;



    }


}