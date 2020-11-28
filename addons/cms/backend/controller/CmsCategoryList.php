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
use addons\cms\common\model\CmsCategory as CategoryModel;
use app\common\traits\Curd;
use fun\helper\ArrayHelper;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Config;

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
        $cate = cache('category_list');
        if(!$cate){
            $cate = $this->modelClass->field('id,pid,catename,type')->order('sort asc,id asc')->select()->toArray();
            $cate = $this->_cateTree($cate);
            cache('category_list',$cate);
        }
        $idList = $this->modelClass->column('id');
        sort($idList);
        return view('',['list'=>$cate,'idList'=>$idList]);

    }

    //内容页面
    public function list(){
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
            if($this->request->isAjax()){
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                $list = Db::name($module->modulename)->where($where)
                    ->where(['cateid'=>$cate->id])
                    ->order($sort)
                    ->paginate(['list_rows' => $this->pageSize, 'page' => $this->page])
                    ->toArray();
                return $result = ['code'=>0,'msg'=>lang('Get Data success'),'data'=>$list['data'],'count'=>$list['total']];
            }
            $view = 'list';
            return view($view,['title'=>$title,'formData'=>$formData,'cate'=>$cate]);

        }else{
            //添加单页内容
            if($this->request->isPost()) {
                $post = input('post.');
                $cate = $this->modelClass->find($cateId);
                $module = CmsModule::find( $cate['moduleid']);
                $post['create_time'] = time();
                unset($post['file']);
                if($post['id']){
                    $id= $post['id'];
                    unset($post['__token__']);
                    if(isset($post['file'])) unset($post['file']);
                    $res = Db::name($module->tablename)->save($post);
                }else{
                    unset($post['__token__']);
                    $res = $id =  Db::name($module->tablename)->insertGetId($post);
                }
                if($res){
                    if(isset($post['tags']) and $post['tags']){
                        if($module->tablename=='addons_cms_article'){
                            $tagModel = new CmsTags();
                            $tagModel->addTags($post['tags'],$id);
                        }
                    }
                    $this->success(lang('Edit Success'));
                }else{
                    $this->error(lang('Edit Failed'));
                }
            }
            $formData =  Db::name($module->tablename)->where('cateid',$cateId)->find();
            $view = 'page';
            return view($view,['title'=>$title,'formData'=>$formData,'cate'=>$cate,]);
        }

    }
    //面板
    public function board(){
        $formData['category'] = $this->modelClass->count();
        $formData['module'] = CmsModule::count();

        return view('board',['formData'=>$formData]);
    }
    //添加信息
    public function add(){
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
                $res =  Db::name($module->tablename)->where('id', $id)->update($post);
            }else{
                $post['create_time'] = time();
                unset($post['__token__']);
                $res = $id =  Db::name($module->tablename)->insertGetId($post);
            }
            if($res){
                if(isset($post['tags']) and $post['tags']){
                    if($module->tablename=='addons_cms_article'){
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
            $formData =  Db::name($module->tablename)->find($id);
            foreach ($fieldList as $k=>$v){
                $fieldList[$k]['value'] = $formData[$v['field']];
            }
        }
        return view('',['cate'=>$cate,'fieldList'=>$fieldList,'title'=>$title,'formData'=>$formData,]);
    }

    // 状态修改
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
            $this->error(lang("Edit fail"));
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
            if($res) $this->success(lang('edit success'));
            $this->error("edit fail");
        }
    }

    // 刷新缓存
    public function flashCache()
    {

        $this->modelClass->flashCache() ? $this->success(lang('Clear Success')) : $this->error(lang('Clear Fail'));
    }

    //树形组件分类
    protected function _cateTree($cate, $pid = 0)
    {

        $list = [];
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                $v['title'] = $v['catename'];
                $v['href'] = (string)addons_url('list', ['cateid' => $v['id']]);
                if ($this->_cateTree($cate, $v['id'])) {
                    $v['children'] = $this->_cateTree($cate, $v['id']);
                }
                $list[] = $v;
            }
        }
        return $list;

    }


}