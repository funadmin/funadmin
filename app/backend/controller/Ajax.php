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

namespace app\backend\controller;

use app\backend\model\AdminMenu;
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

    public function __construct(App $app)
    {
        $this->model = new AttachModel();
        parent::__construct($app);
    }

    /**
     * @return \think\response\Json
     * 文件上传总入口 集成qiniu ali tenxunoss
     */
    public function uploads()
    {
        try {
            $upload = UploadService::instance();
            $result = $upload->uploads(0,session('admin.id'));
            return json($result);
        } catch (\Exception $e) {
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
        $cate = AdminMenu::query()
            ->where('status', 1)
            ->order('sort asc')
            ->select()->toArray();
        $menuList = AuthService::instance()->menuhtml($cate);
        $this->success('ok','',$menuList);
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
        // 安全检查: 过滤非法字符，只允许字母、数字、下划线、点
        if(!empty($name) && !preg_match('/^[a-zA-Z0-9_.]+$/', $name)){
            $name = '';
        }
        $app = $this->request->get("app");
        // 安全检查: $app 只允许字母、数字、下划线
        if(!empty($app) && !preg_match('/^[a-zA-Z0-9_]+$/', $app)){
            $app = '';
        }
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
        $path = trim((string) $this->request->param('path', 'uploads'), '/\\');
        $storageRoot = realpath(app()->getRootPath() . 'public/storage');
        $paths = $storageRoot ? realpath($storageRoot . DIRECTORY_SEPARATOR . $path) : false;
        if (!$storageRoot || !$paths || ($paths !== $storageRoot && !str_starts_with($paths, $storageRoot . DIRECTORY_SEPARATOR))) {
            $this->error(lang('Invalid data'));
        }
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
            $this->page = max(1, (int) $this->request->param('page', 1));
            $this->pageSize = min(100, max(1, (int) $this->request->param('limit', 15)));
            $sortField = (string) $this->request->param('sort', 'id');
            $sortField = in_array($sortField, ['id', 'original_name', 'created_at'], true) ? $sortField : 'id';
            $sortDirection = strtolower((string) $this->request->param('order', 'desc')) === 'asc' ? 'asc' : 'desc';
            $sort = [$sortField => $sortDirection];
            $where = [];
            if(input('original_name')){
                $where[] =['original_name|id','like','%'.input('original_name').'%'];
            }
            if (!AuthService::instance()->isSuperAdmin()) {
                $where[] = ['admin_id', '=', (int) session('admin.id')];
            }
            $count = $this->model
                ->where($where)
                ->order($sort)
                ->count();
            $list = $this->model->where($where)
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
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
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
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $config = Config::where('code', input('code'))->find();
        $result = $config?$config->save(['value'=>input('value')]):'';
        Cache::clear();
        $result?$this->success(lang('operation success')):$this->error(lang('operation failed'));
    }

}
