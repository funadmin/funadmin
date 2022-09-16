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

use app\cms\controller\backend\CmsBackend;
use app\cms\model\Topic;
use app\cms\model\Forum  as ForumModel;
use app\backend\model\Admin;
use app\cms\model\Field;
use app\cms\model\Module;
use app\cms\model\Category as CategoryModel;

use Exception;
use fun\helper\ArrayHelper;
use fun\helper\TreeHelper;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Cache;
use think\facade\View;

class Forum extends CmsBackend
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ForumModel();
        View::assign('addon',$this->addon);

    }
    /**--------------------------------------------------------栏目内容管理----------------------------------------------------**/
    public function index(){
        $cateid = $this->request->param('cateid');
        if($this->request->isAjax()){
//            var_dump($where);
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            if($cateid){
                $cate = CategoryModel::find($cateid);
                $where[] = ['cateid','in',$cate->arrchildid];
            }
            $list =$this->modelClass->where($where)
                ->where('cateid','>',0)
                ->order($sort)
                ->with('category')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $this->page])
                ->toArray();
            return $result = ['code'=>0,'msg'=>lang('operation success'),'data'=>$list['data'],'count'=>$list['total']];
        }
        $cate =  json_decode(Cache::get('category_list'));
        if(!$cate){
            $cate = CategoryModel::field('id,pid,catename,type')
                ->order('sort asc,id asc')
                ->select()->toArray();
            $cate = $this->_cateTree($cate);
            Cache::set('category_list',json_encode($cate));
        }
        return view('',['list'=>$cate,'cateid'=>$cateid]);

    }

    public function recycle(){
        $cateid = $this->request->param('cateid');
        if($this->request->isAjax()){
            list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
            if($cateid){
                $cate = CategoryModel::find($cateid);
                $where[] = ['cateid','in',$cate->arrchildid];
            }
            $list =$this->modelClass->onlyTrashed()->where($where)
                ->where('cateid','>',0)
                ->order($sort)
                ->with('category')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $this->page])
                ->toArray();
            return $result = ['code'=>0,'msg'=>lang('operation success'),'data'=>$list['data'],'count'=>$list['total']];
        }
        $cate =  json_decode(Cache::get('category_list'));
        if(!$cate){
            $cate = CategoryModel::field('id,pid,catename,type')
                ->order('sort asc,id asc')
                ->select()->toArray();
            $cate = $this->_cateTree($cate);
            Cache::set('category_list',json_encode($cate));
        }
        return view('index',['list'=>$cate,'cateid'=>$cateid]);

    }

    /**
     * 面板
     * @return \think\response\View
     */
    public function board(){
        $formData['category'] = CategoryModel::count();
        $formData['module'] = Module::count();
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
        $cateid = $this->request->param('cateid');
        if($this->request->isPost()){
            $post = $this->request->post();
            $rule = [];
            $this->validate($post, $rule);
            $post['publish_time'] = $post['publish_time']?strtotime($post['publish_time']):time();
            $post['title_color'] =  isset($post['title_color'])?$post['title_color']:'';
            $post['title_bold'] =  isset($post['title_bold'])?$post['title_bold']:'';
            $post['titlestyle'] =  $post['title_color'].'|'. $post['title_bold'];
            try {
                $save = $this->modelClass->save($post);
            }catch(Exception $e){
                $this->error($e->getMessage());

            }
            $save ? $this->success(lang('operation failed')):$this->error(lang('operation failed'));
        }
        $catelist = CategoryModel::where('status',1)->where('is_release',1)->select()->toArray();
        $catelist = TreeHelper::cateTree($catelist);
        $topiclist = Topic::where('status',1)->column('title','id');
        $adminlist = Admin::where('status',1)->column('username','id');
        $fieldList = [];
        if($cateid){
            $cate = CategoryModel::find($cateid);
            $fieldList = Field::where('moduleid',$cate->moduleid)->cache(3600)->select()->toArray();
            foreach ($fieldList as $k=>$v){
                if($fieldList[$k]['options']){
                    $fieldList[$k]['options'] = ArrayHelper::parseToarr( $fieldList[$k]['options'] );
                }
            }
        }

        $formData = [];
        return view('',['adminlist'=>$adminlist,'cateid'=>$cateid,'catelist'=>$catelist,'fieldList'=>$fieldList,'formData'=>$formData,'topiclist'=>$topiclist]);
    }


    /**编辑
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function edit(){
        $cateid = $this->request->get('cateid');
        if($this->request->isPost()){
            $post = $this->request->post();
            $category = CategoryModel::find($cateid);
            if($cateid){
                $list = $this->modelClass->where('cateid',$cateid)->find();
            }else{
                $id = $this->request->param('id');
                $list = $this->modelClass->find($id);
            }
            $rule = [];
            try {
                $this->validate($post, $rule);
            }catch(ValidateException $e){
                $this->error(lang($e->getMessage()));
            }
            $post['publish_time'] =  $post['publish_time']?strtotime($post['publish_time']):time();
            $post['title_color'] =  isset($post['title_color'])?$post['title_color']:'';
            $post['title_bold'] =  isset($post['title_bold'])?$post['title_bold']:'';
            $post['titlestyle'] =  $post['title_color'].'|'. $post['title_bold'];
            try {
                if($category->type==1 || $category->type==2){
                    if(empty($list)){
                        $post['cateid'] = (int)$cateid;
                        unset($post['__token__']);
                        unset($post['title_bold']);
                        unset($post['title_color']);
                        $save = $this->modelClass->save();
                    }else{
                        $save = $list->save($post);
                    }
                }else{
                    $save = $list->save($post);
                }
            }catch (Exception $e){
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation failed')):$this->error(lang('operation failed'));
        }
        if($cateid){
            $formData =  $this->modelClass->where('cateid',$cateid)->find();
            $category = CategoryModel::find($cateid);
            $module = Module::find($category->moduleid);
            $fieldList = [];
        }else{
            $id = input('id');
            $formData =  $this->modelClass->find($id);
            $category = CategoryModel::find($formData->cateid);
            $moduleid = $category->moduleid ;
            $module = Module::find($category->moduleid);
            $fieldList = Field::where('moduleid',$moduleid)->cache(3600)->select()->toArray();
            foreach ($fieldList as $k=>$v){
                if($fieldList[$k]['options']){
                    $fieldList[$k]['options'] = ArrayHelper::parseToarr( $fieldList[$k]['options'] );
                }
            }
        }
        if($formData){
            $formData->publish_time = date('Y-m-d H:i:s');
            $data  = Db::name($module->tablename)->find($formData->id);
            $style = $formData->titlestyle?explode('|',$formData->titlestyle):['',''];
            $data['title_color'] =$style[0];
            $data['title_bold'] = $style[1];
            $data['content'] = htmlspecialchars_decode($data['content']);
            $formData->appendData($data);
        }
        foreach ($fieldList as $k=>$v){
            $fieldList[$k]['value'] = $formData[$v['field']];
        }
        $catelist = CategoryModel::where('status',1)->where('is_release',1)->select()->toArray();
        $catelist = TreeHelper::cateTree($catelist);
        $topiclist = Topic::where('status',1)->column('title','id');
        $adminlist = Admin::where('status',1)->column('username','id');
        return view('edit',['cateid'=>$cateid,'adminlist'=>$adminlist,'catelist'=>$catelist,'fieldList'=>$fieldList,'formData'=>$formData,'topiclist'=>$topiclist]);
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 获取字段列表
     */
    public function getfield(){
        app()->view->engine()->layout(false);
        $id = $this->request->get('id');
        $moduleid = CategoryModel::where('id',$id)->value('moduleid');
        $fieldList = Field::where('moduleid',$moduleid)->cache(3600)->select()->toArray();
        foreach ($fieldList as $k=>$v){
            if($fieldList[$k]['options']){
                $fieldList[$k]['options'] = ArrayHelper::parseToarr( $fieldList[$k]['options'] );
            }
        }
        return view('field',['fieldList'=>$fieldList]);
    }

    /**
     * 删除内容
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete()
    {
        if ($this->request->isPost()) {
            $ids =  $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');
            if($ids=='all'){
                $list = $this->modelClass->select();
            }else{
                $list = $this->modelClass->where('id','in', $ids)->select();
            }
            if(empty($list))$this->error('Data is not exist');
            try {
                $save = $list->delete();
            } catch (\Exception $e) {
                $this->error(lang("operation failed"));
            }

            $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
        }
    }

    //刷新缓存
    public function flashCache()
    {
        CategoryModel::flashCache() ? $this->success(lang('operation Success')) : $this->error(lang('operation failed'));
    }

    //树形组件分类
    protected function _cateTree($cate, $pid = 0)
    {
        $list = [];
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                $v['title'] = $v['catename'];
                if($v['type']==1 || $v['type']==2){ //1 列表2 单页，3 外联，4 封面
                    $v['href'] = (string)addons_url('edit', ['cateid' => $v['id']]);
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