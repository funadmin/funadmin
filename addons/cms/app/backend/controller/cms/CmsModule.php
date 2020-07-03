<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */

namespace app\backend\controller\cms;

use app\common\controller\Backend;
use app\common\model\CmsModule as CM;
use app\common\model\CmsField;
use app\common\model\FieldType;
use app\common\traits\Curd;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class CmsModule extends Backend
{
    use Curd;
    public $prefix = '';
    public function initialize()
    {
        parent::initialize();
        $this->prefix = Config::get('database.connections.mysql.prefix');
        //取得当前内容模型模板存放目录
        $this->filepath = app()->getRootPath().'app/cms/view' . DIRECTORY_SEPARATOR;
        //取得栏目频道模板列表
//        $this->_column = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'column*'));
        //取得栏目列表模板列表
        $this->_list = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'list*'));
        //取得内容页模板列表
        $this->_show = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'show*'));

    }

    // 模型列表
    public function index(){
        //全局查询条件
        if(Request::isPost()){

            $keys = Request::post('keys', '', 'trim');
            $page = Request::post('page') ? Request::post('page') : 1;
            $list = CM::where('name|title', 'like', '%' . $keys . '%')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];

        }
        return view();
    }

    // 模型添加
    public function add()
    {
        if (Request::isPost()) {
            //获取数据库所有表名
            $tables = Db::getConnection()->getTables();
            $tablename = $this->prefix . Request::param('name');
            if (in_array($tablename, $tables)) {
                $this->error(lang('table is already exist'));
            }
            $data = Request::except(['emptytable']);
            try {
                $this->validate($data, 'CmsModule');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $data['template']=isset($data['template'])? serialize($data['template']): "";
            $module = CM::create($data);

            $moduleid = $module->id;
            if (empty($moduleid)) {
                $this->error('添加模型失败！');
            }
            $emptytable = Request::post('emptytable');
            CM::addTable($tablename,$this->prefix,$moduleid,$emptytable);
            $this->success(lang('add success'), url('index'));
        }
        $view =[
            'title'=>lang('add'),
            'info' => null,
//            '_column'=>$this->_column,
            '_list'=>$this->_list,
            '_show'=>$this->_show,
            ''
        ];

        View::assign($view);
        return view();
    }


    // 模型修改
    public function edit(){
        if (Request::isPost()) {
            $data = Request::post();
            $result = $this->validate($data, 'CmsModule');
            if (true !== $result) {
                // 验证失败 输出错误信息
                $this->error($result);
            } else {
                if (CM::update($data) !== false) {
                    $this->success('修改成功!', url('index'));
                } else {
                    $this->error('修改失败！');
                }
            }
        }

        $id     = Request::param('id');
        $info   = CM::find($id);
        $view = [
            'title'=>lang('edit'),
            'info' => $info,
//            '_column'=>$this->_column,
            '_list'=>$this->_list,
            '_show'=>$this->_show,
        ];
        View::assign($view);
        return view('add');
    }

    // 模型状态
    public function state(){
        if (Request::isPost()) {
            $id = Request::param('id');
            $status = CM::where('id='.$id)
                ->value('status');
            $status = $status == 1 ? 0 : 1;
            if (CM::where('id',$id)->update(['status'=>$status]) !== false) {
                $this->success(lang('edit success'));
            }else{
                $this->error(lang('edit fail'));
            }
        }
    }

    // 模型删除
    public function delete(){
        if (Request::isPost()) {
            $ids = Request::param('ids');
            $info = CM::find($ids[0]);
            $tables = $this->prefix.$info->name;
            $res = CM::destroy($ids[0]);
            if($res){
                Db::execute("DROP TABLE IF EXISTS `".$tables."`");
                CmsField::where('moduleid',$info->id)->delete();
                $this->success(lang('delete success'));
            }else{
                $this->error(lang('delete fail'));
            }

        }
    }


    /****************************模型字段管理******************************/

    /*
     * 字段列表
     */
    public function field(){

        if(Request::isPost()){
            //不可控字段
            $sysfield = array('cateid','title','keywords','description','hits','status','create_time','update_time','template');
            $list = CmsField::where("moduleid",'=',Request::param('id'))
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
            'moduleid' => Request::param('id')
        ];
        View::assign($view);
        return view();
    }


    // 添加字段
    public function fieldAdd(){
        if (Request::isPost()) {
            //增加字段
            $data = Request::param();
            try{
                $result = $this->validate($data, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $res = CmsField::addField($data);
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
    public function fieldEdit(){
        if (Request::isPost()) {
            //增加字段
            $data = Request::param();
            try{
                $result = $this->validate($data, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $fieldid  = $data['id'];
                $res = CmsField::editField($data,$fieldid);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang('add success'));

        }

        $id = Request::param('id');
        $fieldInfo = CmsField::where('id','=',$id)
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
    public function fieldDel() {
        $ids = Request::param('ids');
        $f  = Db::name('field')->find($ids[0]);
        //删除字段表中的记录
        CmsField::destroy($ids[0]);
        $moduleid = $f['moduleid'];
        $field    = $f['field'];
        $name   = CM::where('id','=',$moduleid)->value('name');
        $tablename = $this->prefix.$name;
        //实际查询表中是否有该字段
        if(CmsField::isset_field($tablename,$field)){
            Db::name($tablename)->execute("ALTER TABLE `$tablename` DROP `$field`");
        }
        $this->success(lang('删除成功'));
    }

    // 字段排序
    public function fieldSort(){
        $data = Request::post();
        if (CmsField::update($data) !== false) {
            $this->success(lang('edit success'));
        } else {
            $this->error(lang('edit fail'));
        }
    }

    // 字段状态
    public function fieldState(){
        if (Request::isPost()) {
            $id = Request::param('id');
            $status = CmsField::where('id','=',$id)
                ->value('status');
            $status = $status == 1 ? 0 : 1;
            if (CmsField::where('id','=',$id)->update(['status'=>$status]) !== false) {
                $this->success(lang('edit success'));
            } else {
                $this->error(lang('edit fail'));
            }
        }
    }




}
