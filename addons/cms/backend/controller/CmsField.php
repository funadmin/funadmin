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

namespace addons\cms\backend\controller;
use app\common\controller\AddonsBackend;
use addons\cms\common\model\CmsField as CmsFieldModel;
use app\common\model\FieldType;
use app\common\traits\Curd;
use think\App;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class CmsField extends AddonsBackend
{
    use Curd;
    public $musterfield = [];
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsFieldModel();
        (new \addons\cms\common\model\CmsModule())->getTableColumn('addons_cms_muster');
    }
    /*
     * 字段列表
     */
    public function index(){
        $moduleid = $this->request->param('id/d');
        if($this->request->isAjax()){
            //不可控字段

            $sysfield = array('cateid','title','keywords','description','hits','status','create_time','update_time','template');
            $list = $this->modelClass->where("moduleid",$moduleid)
                ->order('sort asc,id asc')
                ->select()->toArray();
            foreach ($list as $k=>$v){
                if(in_array($v['field'],$sysfield)){
                    $list[$k]['del']=0;
                }else{
                    $list[$k]['del']=1;
                }
            }
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list, 'count' => count($list)];
        }
        $view = [
            'moduleid' => $moduleid,
        ];
        View::assign($view);
        return view();
    }


    // 添加字段
    public function add(){
        if ($this->request->isAjax()) {
            //增加字段
            $post = Request::param();
            try{
                $result = $this->validate($post, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $res = $this->modelClass->addField($post);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('add success'));

        }

        $view = [
            'moduleid'  => Request::param('moduleid'),
            'info'      => null,
            'title'=>lang('add'),
            'fieldType'=>FieldType::select(),
        ];
        View::assign($view);
        return view('field_add');
    }



    // 编辑字段
    public function edit(){
        if ($this->request->isAjax()) {
            //增加字段
            $post = Request::param();
            try{
                $result = $this->validate($post, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $fieldid  = $post['id'];
                $res = $this->modelClass->editField($post,$fieldid);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('add success'));

        }

        $id = Request::param('id');
        $fieldInfo = $this->modelClass->where('id','=',$id)
            ->find();

        $view = [
            'moduleid'  => $fieldInfo['moduleid'],
            'fieldType'=>FieldType::select(),
            'info'      => $fieldInfo,
            'title'=>lang('edit'),
        ];
        View::assign($view);
        return view('field_add');
    }

    // 删除字段
    public function delete() {
        $ids = $this->request->post('id')?$this->request->post('id'):$this->request->post('ids');
        $f  = Db::name('field')->find($ids[0]);
        //删除字段表中的记录
        $this->modelClass->destroy($ids[0]);
        $moduleid = $f['moduleid'];
        $field    = $f['field'];
        $name   = $this->modelClass->where('id',$moduleid)->value('tablename');
        $tablename = $this->prefix.$name;
        //实际查询表中是否有该字段
        if($this->modelClass->isset_field($tablename,$field)){
            Db::name($tablename)->execute("ALTER TABLE `$tablename` DROP `$field`");
        }
        $this->success(lang('Dlete Success'));
    }

}
