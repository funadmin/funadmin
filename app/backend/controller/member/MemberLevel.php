<?php
namespace app\backend\controller\member;

use app\backend\model\MemberLevel as MemberLevelModel;
use app\common\controller\Backend;
use app\common\traits\Curd;
use think\App;
class MemberLevel extends Backend{

    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new MemberLevelModel();
    }

    /**---------------用户等级--------------------**/

    public function add(){
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $rule = [
                'name|等级名称' => [
                    'require' => 'require',
                    'max'     => '255',
                    'unique'  => 'member_level',
                ],
                'description|描述' => [
                    'max' => '255',
                ],
            ];
            $this->validate($post, $rule);
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang('Save failed'));
            }
            $save ? $this->success(lang('Save success')) : $this->error(lang('Save failed'));
        }
        $view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('',$view);
    }




}