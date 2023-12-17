<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\frontend\controller;

use app\common\controller\Frontend;
use app\common\model\Attach;
use app\common\service\UploadService;
use think\App;
use think\Exception;
use think\facade\Lang;

class Ajax extends Frontend
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new Attach();

    }

    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 文件上传总入口 集成qiniu ali tenxunoss
     */
    public function uploads()
    {
        try {
            $upload = UploadService::instance();
            $result = $upload->uploads(session('member.id'),0);
            return json($result);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 获取附件列表
     */
    public function getAttach()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $where = [];
            if(input('original_name')){
                $where[] =['original_name|id','like','%'.input('original_name').'%'];
            }
            $count = $this->modelClass
                ->where($where)
                ->order($sort)
                ->count();
            $list = $this->modelClass
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return json($result);
        }
    }
    /**
     * @return \think\response\Jsonp
     * 自动加载语言函数
     */
    public function lang()
    {
        header('Content-Type: application/javascript');
        $controllername = $this->request->get("controllername");
        $controllername = strtolower(parse_name($controllername,1));
        $app = $this->request->param('app');
        //默认只加载了控制器对应的语言名，你还根据控制器名来加载额外的语言包
        $this->loadlang($controllername,$app);
        return jsonp(Lang::get())->code(200)->options([
            'var_jsonp_handler'     => 'callback',
            'default_jsonp_handler' => 'jsonpReturn',
            'json_encode_param'     => JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE,
        ])->allowCache(true)->expires(7200);
    }
}
