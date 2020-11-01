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
use addons\cms\common\model\CmsModule as CmsModuleModel;
use addons\cms\common\model\CmsField;
use app\common\model\FieldType;
use app\common\traits\Curd;
use think\App;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class CmsModule extends AddonsBackend
{
    use Curd;
    public $prefix = '';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsModuleModel();
        $this->prefix = Config::get('database.connections.mysql.prefix');
        //取得当前内容模型模板存放目录
        $this->filepath = $this->addon_path.'view'.DIRECTORY_SEPARATOR.'frontend' . DIRECTORY_SEPARATOR;
        //取得栏目频道模板列表
//        $this->_column = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'column*'));
        //取得栏目列表模板列表
        $this->_list = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'list*'));
        //取得内容页模板列表
        $this->_show = str_replace($this->filepath . DIRECTORY_SEPARATOR, '', glob($this->filepath . DIRECTORY_SEPARATOR . 'show*'));

    }
    // 模型添加
    public function add()
    {

        if ($this->request->isAjax) {
            //获取数据库所有表名
            $tables = Db::getConnection()->getTables();
            $tablename = $this->prefix . Request::param('name');
            if (in_array($tablename, $tables)) {
                $this->error(lang('table is already exist'));
            }
            $post = Request::except(['emptytable']);
            $rule =  $rule = [
                'title|模型名称' => [
                    'require' => 'require',
                    'max'     => '100',
                    'unique'  => 'cms_module',
                ],
                'name|表名' => [
                    'require' => 'require',
                    'max'     => '50',
                    'unique'  => 'cms_module',
                ],
                'listfields|列表页字段' => [
                    'require' => 'require',
                    'max'     => '255',
                ],
                'description|描述' => [
                    'max' => '200',
                ],
                'sort|排序' => [
                    'require' => 'require',
                    'number'  => 'number',
                ]
            ];
            try {
                $this->validate($post, $rule);
            } catch (\ValidateException $e) {
                $this->error($e->getMessage());
            }
            $post['template']=isset($post['template'])? serialize($post['template']): "";
            $module = $this->modelClass->save($post);

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
        if ($this->request->isAjax) {
            $post = Request::post();
            $result = $this->validate($post, 'CmsModule');
            if (true !== $result) {
                // 验证失败 输出错误信息
                $this->error($result);
            } else {
                if (CM::update($post) !== false) {
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
        if ($this->request->isAjax) {
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
        if ($this->request->isAjax) {
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

        if($this->request->isAjax){
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
        if ($this->request->isAjax) {
            //增加字段
            $post = Request::param();
            try{
                $result = $this->validate($post, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $res = CmsField::addField($post);
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
        if ($this->request->isAjax) {
            //增加字段
            $post = Request::param();
            try{
                $result = $this->validate($post, 'CmsField');

            }catch (\Exception $e){
                $this->error($e->getMessage());

            }
            try {
                $fieldid  = $post['id'];
                $res = CmsField::editField($post,$fieldid);
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
        $post = Request::post();
        if (CmsField::update($post) !== false) {
            $this->success(lang('edit success'));
        } else {
            $this->error(lang('edit fail'));
        }
    }

    // 字段状态
    public function fieldState(){
        if ($this->request->isAjax) {
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
