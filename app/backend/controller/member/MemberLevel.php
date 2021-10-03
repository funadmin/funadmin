<?php
namespace app\backend\controller\member;

use app\backend\model\MemberLevel as MemberLevelModel;
use app\common\controller\Backend;
use think\App;
use app\common\annotation\NodeAnnotation;
use app\common\annotation\ControllerAnnotation;

/**
 * @ControllerAnnotation (title="会员等级")
 * Class MemberLevel
 * @package app\backend\controller\member
 */
class MemberLevel extends Backend{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new MemberLevelModel();
    }
    /**
     * @NodeAnnotation (title="添加")
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
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