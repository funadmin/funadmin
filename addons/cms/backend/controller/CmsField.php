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
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class CmsField extends AddonsBackend
{
    use Curd;
    protected $sysfield = [];
    protected $moduleid = '';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CmsFieldModel();
        $this->sysfield = (new \addons\cms\common\model\CmsModule())->getTableColumn('addons_cms_filing','COLUMN_NAME,COLUMN_COMMENT');
        $this->moduleid = $this->request->param('moduleid/d');
        $this->diyformid = $this->request->param('diyformid/d');
    }
    /*
     * 字段列表
     */
    public function index(){
        if($this->request->isAjax()){
            //不可控字段
            if($this->moduleid){
                $list = $this->modelClass->where("moduleid", $this->moduleid)
                    ->order('sort asc,id asc')
                    ->select()->toArray();
                $arr = Cache::get('filing_field');
                if(!$arr){
                    foreach ($this->sysfield as $k=>$v){
                        $arr[$k]['field'] = $v['COLUMN_NAME'];
                        $arr[$k]['name'] = $v['COLUMN_COMMENT'];
                    }
                    $sysfield_content = [['field'=>"content",'name'=>'内容']];
                    $arr = array_merge($list,$arr,$arr,$sysfield_content);
                    Cache::tag('filing_field')->set('filing_field',$arr,7200);
                }
                $list = array_merge($list,$arr,$arr);
            }else{
                $list = $this->modelClass->where("diyformid", $this->diyformid)
                    ->order('sort asc,id asc')
                    ->select()->toArray();
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
                    validate(\addons\cms\backend\validate\CmsField::class)
                        ->scene('module')
                        ->check($post);
                }else{
                    validate(\addons\cms\backend\validate\CmsField::class)
                        ->scene('diyform')
                        ->check($post);
                }
//               $this->validate($post, \addons\cms\backend\validate\CmsField::class);

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
            try{
                $this->validate($post, \addons\cms\backend\validate\CmsField::class);
            }catch (ValidateException $e){
                $this->error($e->getMessage());
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
