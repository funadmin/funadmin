<?php
namespace app\backend\controller\member;

use app\backend\model\MemberGroup as MemberGroupModel;
use app\common\controller\Backend;
use app\common\traits\Curd;
use think\App;
use think\Exception;
class MemberGroup extends Backend{



    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new MemberGroupModel();
    }

    /**---------------用户等级--------------------**/

    public function add(){
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $rule = ['name|组名'=>'require|unique:member_group'];
            $this->validate($post, $rule);
            try {
                $save = $this->modelClass->save($post);
            } catch (Exception $e) {
                $this->error(lang('Save Failed'));
            }
            $save ? $this->success(lang('Save Success')) : $this->error(lang('Save Failed'));
        }
        $view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('',$view);
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     */
    public function delete()
    {
        $ids =  $this->request->param('ids')?$this->request->param('ids'):$this->request->param('id');

        $list = $this->modelClass->where('id','in', $ids)->select();
        if($ids ==1 || is_array($ids) and in_array(1,$ids)){
            $this->error(lang("Default Group Cannot Delete"));
        }
        if(empty($list))$this->error('Data is not exist');
        try{
            $save = $list->delete();
        } catch (\Exception $e) {
            $this->error(lang("operation failed"));
        }

        $save ? $this->success(lang('operation success')) :  $this->error(lang("operation failed"));
    }


}