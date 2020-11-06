<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller;

use app\backend\model\AuthRule;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use app\common\model\Attach as AttachModel;
use app\common\service\UploadService;
use app\common\traits\Curd;
use fun\helper\FileHelper;
use GuzzleHttp\Psr7\Request;
use think\App;
use think\Exception;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Lang;

class Ajax extends Backend
{

    use Curd;

    public function __construct(App $app)
    {
        $this->modelClass = new AttachModel();
        parent::__construct($app);

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
            $result = $upload->uploads();
            return json($result);

        }catch(Exception $e){
            $this->error($e->getMessage());
        }

    }


    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 刷新菜单
     */
    public function refreshmenu(){
        $cate = AuthRule::where('menu_status', 1)->order('sort asc')->select()->toArray();
        $menulsit = (new AuthService())->menuhtml($cate);
        $this->success($menulsit);

    }
    /**
     * @return \think\response\Jsonp
     * 自动加载语言函数
     */
    public function lang()
    {
        header('Content-Type: application/javascript');
        $name = $this->request->get("controllername");
        $name = strtolower(parse_name($name,1));
        $addon = $this->request->get("addons");
        //默认只加载了控制器对应的语言名，你还根据控制器名来加载额外的语言包
        return jsonp($this->loadlang($name,$addon))->code(200)->options([
                    'var_jsonp_handler'     => 'callback',
                    'default_jsonp_handler' => 'jsonpReturn',
                    'json_encode_param'     => JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE,
                ])->allowCache(true)->expires(7200);
    }

    /**
     * @return \think\response\Json
     * 获取图片列表
     */
    public function getList()
    {
        $path = $this->request->param('path', 'uploads');
        $paths = app()->getRootPath() . 'public/storage/' . $path;
        $type = $this->request->param('type', 'image');
        $list = FileHelper::getFileList($paths, $type);
        $post = ['state' => 'SUCCESS', 'start' => 0, 'total' => count($list), 'list' => []];
        $attach = AttachModel::where('mime', 'like', '%' . 'image' . '%')->select()->toArray();
        if ($list) {
            foreach ($list[0] as $k => $v) {
                $post['list'][$k]['url'] = str_replace(app()->getRootPath() . 'public', '', $v);
                $post['list'][$k]['mtime'] = mime_content_type($v);
            }
        }
        $post['list'] = array_merge($post['list'], $attach);
        return json($post);
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
            $count = $this->modelClass
                ->where($where)
                ->order($sort)
                ->count();
            $list = $this->modelClass->where($where)
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

    }

    /*
     * 清除缓存
    */
    public function clearcache()
    {
        Cache::clear();
        $this->success('清除成功');
    }

}