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
namespace app\cms\controller;

use app\cms\model\Field;
use app\common\model\Addon;
use app\cms\model\Category;
use app\cms\model\Module;
use app\cms\model\Tags;
use app\cms\model\Forum;
use app\cms\model\Diyform;
use think\App;
use think\facade\View;
use think\facade\Db;

class Index extends Base {
    public $tablename = null;
    public $module = null;
    public $moduleid = null;
    public $category = null;
    public $cateid = null;
    public $top_cateid = null;
    protected $fieldList = ['id'=>'','content'=>''];
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->cateid = $cateid = $this->request->param('cateid/d');
        if($cateid){
            $this->category = Category::cache(true)->find($cateid);
            if(!$this->category){
                redirect(addons_url('error/404'));
            }
            $this->moduleid = $this->category ->moduleid;
            $this->module = Module::find( $this->category ->moduleid);
            $this->tablename =   $this->module ->tablename;
            if($this->category->pid == 0){
                $this->top_cateid = $this->cateid;
            }else{
                $parentids = explode(',',$this->category->arrpid);
                $this->top_cateid = isset($parentids[1]) ? $parentids[1] : $this->cateid;
            }
        }
        $list = ['cateid' => $cateid,
            'top_cateid' => $this->top_cateid,
            'moduleid' => $this->moduleid,
            'category' => $this->category
        ];
        View::assign($list);
    }
    //首页
    public function index(){
        return view('index_index');
    }

    /**
     * 列表页
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function lists(){
        //单页
        if($this->category->type==2){
            $list = Forum::where('status',1)->where('cateid',$this->cateid)->find();
            if(!$list) $this->redirect(addons_url('error/notice',['message'=>'没有内容']));
            $template = $this->category->template_show;
            //更新点击量
            Forum::where('status',1)->where('id', $list->id)->inc('hits')->update();
            $tableContent = Db::name($this->tablename)->find($list['id']);
            if($tableContent){
                $field = Field::where('moduleid',$this->moduleid)->column('field','field');
                $field = array_merge($this->fieldList,$field);
                $list  = array_merge($list->getData(),array_intersect_key($tableContent, $field));
                $list['content'] =htmlspecialchars_decode($list['content']);
            }
            $seo = ['title'=>$list['title'],'keywords'=>$list['keywords'],'description'=>$list['intro']];
        }else{ //列表
            $page = $this->category->page_size;
            $list = Forum::where('status',1)->whereIn('cateid',$this->category->arrchildid)->cache(3600)
                ->order('sort asc,id desc')->paginate(['page'=>$page, 'list_rows'=>$this->category->page_size, 'query' => $this->request->param()]);
            $template = $this->category->template_list;
            $seo = ['title'=>$this->category->title,'keywords'=>$this->category->keywords,'description'=>$this->category->description];
        };
        $template = substr($template,0,strlen($template)-5);
        $view = ['list'=>$list,'seo'=>$seo,'top_cateid'=>$this->top_cateid];
        return view($template,$view);
    }
    //单页
    public function show(){
        $id = $this->request->param('id/d');
        $list = Forum::where('status',1)->cache(3600)->find($id);
        if(!$list) $this->redirect(addons_url('error/notice',['message'=>'没有内容']));
        if(!$this->tablename) $this->redirect(addons_url('error/notice',['message'=>'没有内容']));
        $tableContent = Db::name($this->tablename)->find($list['id']);
        if($tableContent){
            $field = Field::where('moduleid',$this->moduleid)->column('field','field');
            $field = array_merge($this->fieldList,$field);
            $list  = array_merge($list->getData(),array_intersect_key($tableContent, $field));
            $list['content'] =htmlspecialchars_decode($list['content']);
        }
        if($list['titlestyle']!='|' || $list['titlestyle']==''){
            $titlestyle = explode('|',  $list['titlestyle']);
            if($titlestyle[0]!=''){
                $list['titlestyle'] = 'color:'.$titlestyle[0].';';
            }
            if($titlestyle[1]!=''){
                $list['titlestyle'] .= 'font-weight:'.$titlestyle[1].';';
            }
        }
        Forum::where('status',1)->where('id',$list['id'])->inc('hits')->update();
        $this->category = Category::find($this->cateid);
        $template = $this->category->template_show;
        $seo = ['title'=>$list['title'],'keywords'=>$list['keywords'],'description'=>$list['intro']];
        $template = substr($template,0,strlen($template)-5);
        //上一篇
        $front=Forum::where('status', 1)
            ->where('id','>',$id)
            ->where('cateid','=',$this->cateid)
            ->order('id asc')
            ->limit(1)->find();
        //下一篇
        $after=Forum::where('status', 1)
            ->where('cateid','=',$this->cateid)
            ->where('id','<',$id)
            ->order('id desc')
            ->limit(1)
            ->find();
        $view = ['list'=>$list,'seo'=>$seo,'top_cateid'=>$this->top_cateid,'front'=>$front,'after'=>$after];
        return view($template,$view);
    }

    //表单
    public function diyform(){
        $diyid = $this->request->param('diyid');
        $diyid = $diyid?$diyid:1;
        $model = Diyform::find($diyid);
        if($this->request->isPost()){
            $data = $this->request->post();
            foreach ($data as $k=>$v){
                if(empty($v)){
                    $this->redirect(addons_url('error/notice',['message'=>'信息不能为空']));
                }
            }
            if(session('member.id')){
                $data['member_id'] = session('member.id');
            }
            unset($data['diyid']);
            $data['create_time'] = time();
            $save = Db::name($model->tablename)->save($data);
            $save?$this->success(lang('提交成功')):$this->error('提交失败');
        }
        $template = $model->template?$model->template:'diyform_default.html';
        $template = substr($template,0,strlen($template)-5);
        $fieldList = Field::getfield($diyid,2)->toArray();
        $view = ['fieldList'=>$fieldList,'top_cateid'=>$this->top_cateid,'diyid'=>$diyid];
        return view($template,$view);
    }
    //搜索
    public function search()
    {
        $keys = urldecode($this->request->param('keys'));
        $flag = urldecode($this->request->param('flag'));
        if(!$flag){
            $search = 'title|keywords|intro';
        }else{
            $search = 'tags';
        }
        $page = $this->category->page_size;
        $list = Forum::where($search, 'like', '%' . $keys . '%')->cache(3600)
            ->order('sort asc,id desc')
            ->paginate(['page'=>$page, 'list_rows'=>$this->category->page_size, 'query' => $this->request->param()]);
        $view = ['list' => $list, 'keys' => $keys];
        return view('index_search', $view);
    }
    public function download(){
        $id = $this->request->param('id');
        $forum = Forum::find($id);
        $model = Category::find($forum->cateid);
        $data = Db::name($model->tablename)->find($id);
        if($data){
            Db::name($model->tablename)->where('id',$id)->inc('download_num')->update();
            $this->success('开始下载','',['url'=>$data['download']]);
        }else{
            $this->error('下载失败');

        }
    }

}