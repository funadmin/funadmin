<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\controller;

use app\backend\middleware\CheckRole;
use app\backend\middleware\SystemLog;
use app\backend\middleware\ViewNode;
use app\backend\model\AuthRule;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use app\common\model\Attach as AttachModel;
use app\common\model\Config;
use app\common\service\UploadService;
use fun\helper\FileHelper;
use think\App;
use think\Exception;
use think\facade\Cache;

class Ajax extends Backend
{
    protected $middleware = [
        CheckRole::class=>['only'=>[]],
        ViewNode::class,
        SystemLog::class
    ];
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
            $result = $upload->uploads(0,session('admin.id'));
            return json($result);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 刷新菜单
     */
    public function refreshmenu()
    {
        $cate = AuthRule::where('menu_status', 1)
            ->where('type',1)
            ->order('sort asc')
            ->select()->toArray();
        $menulsit = (new AuthService())->menuhtml($cate);
        $this->success('ok','',$menulsit);
    }
    /**
     * @return \think\response\Jsonp
     * 自动加载语言函数
     */
    public function lang()
    {
        header('Content-Type: application/javascript');
        $name = $this->request->get("controllername");
        $name = strtolower(parse_name($name, 1));
        $app = $this->request->get("app");
        return jsonp($this->loadlang($name, $app))->code(200)->options([
            'var_jsonp_handler' => 'callback',
            'default_jsonp_handler' => 'jsonpReturn',
            'json_encode_param' => JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE,
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
            $where = [];
            if(input('original_name')){
                $where[] =['original_name|id','like','%'.input('original_name').'%'];
            }
            $count = $this->modelClass
                ->where($where)
                ->order($sort)
                ->count();
            $list = $this->modelClass->where($where)
                ->where($where)
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => $count];
            return ($result);
        }
    }
    /*
     * 清除缓存
    */
    public function clearcache()
    {
        $type = $this->request->param('type');
        $frontpath = app()->getRootPath().'runtime'.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR;
        try {
            switch ($type) {
                case 'all':
                    FileHelper::delDir(runtime_path());
                    FileHelper::delDir($frontpath);
                    break;
                case 'backend':
                    FileHelper::delDir(runtime_path());
                    break;
                case 'frontend':
                    FileHelper::delDir($frontpath);
                    break;
            }
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
        FileHelper::delDir(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'temp');
        Cache::clear() ? $this->success('清除成功') : $this->error('清除失败');
    }

    public function setConfig()
    {
        $config = Config::where('code',input('code'))->find();
        $result = $config?$config->save(['value'=>input('value')]):'';
        Cache::clear();
        $result?$this->success(lang('operation success')):$this->error(lang('operation failed'));
    }

}
