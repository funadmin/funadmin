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
 * Date: 2019/9/21
 */

namespace app\common\controller;
use app\backend\service\AuthService;
use app\common\service\AdminLogService;
use app\common\traits\Jump;
use app\common\traits\Curd;
use fun\addons\Controller;
use think\App;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Lang;
use think\facade\View;
use think\helper\Str;
class AddonsBackend extends Controller
{
    use Jump;
    use Curd;
    /**
     * @var
     * 后台入口
     */
    protected $entrance;
    /**
     * @var
     * 模型
     */
    protected $modelClass;
    /**
     * @var
     * 页面大小
     */
    protected $pageSize;

    /**
     * @var
     * 页数
     */
    protected $page;
    /**
     * 模板布局, false取消
     * @var string|bool
     */
    protected $layout = '../app/backend/view/layout/main.html';

    /**
     * 主题
     * @var
     */
    protected $theme;

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';
    /**
     * 允许修改的字段
     */
    protected $allowModifyFileds = [
        'status',
        'title',
        'auth_verify'
    ];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->entrance = config('entrance.backendEntrance');
        (new AuthService())->checkNode();
        $this->pageSize = request()->param('limit', 15);
        //加载语言包
        $this->loadlang(strtolower($this->controller));
        $this->_initialize();
        $this->theme();
        View::assign('addon',$this->addon);
    }

    /**
     * 获取主题路径
     */
    public function theme(){
        $theme = cache($this->addon.'_theme');
        if($theme){
            $this->theme = $theme;
        }else{
            $view_config_file = $this->addon_path.'frontend'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'view.php';
            if(file_exists($view_config_file)){
                $view_config = include_once($this->addon_path.'frontend'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'view.php');
                $this->prefix = Config::get('database.connections.mysql.prefix');
                $theme = $view_config['view_base'];
                $addonsconfig = get_addons_config($this->addon);
                if(isset($addonsconfig['theme']) && $addonsconfig['theme']['value']){
                    $theme = $addonsconfig['theme']['value'];
                }
                $this->theme = $theme?$theme.DIRECTORY_SEPARATOR:'';
                cache($this->addon.'_theme',$this->theme);
            }
        }
    }

    public function _initialize()
    {
        [$modulename, $controllername, $actionname] = [$this->module, $this->controller, $this->action];
        $controllername = str_replace('\\','.',$controllername);
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = strtolower(parse_name($vo,1)) : $jsname .= '/' . strtolower(parse_name($vo,1));
        }
        $controllername = strtolower(Str::camel(parse_name($controllername,1)));
        $actionname = strtolower(Str::camel(parse_name($actionname,1)));
        $requesturl = strtolower("addons/{$this->addon}/{$modulename}/{$controllername}/{$actionname}");
        $autojs = file_exists(app()->getRootPath()."public".DS."static".DS.'addons'.DS."{$this->addon}".DS."{$modulename}".DS."js".DS."{$jsname}.js") ? true : false;
        $jspath ="addons/{$this->addon}/{$modulename}/js/{$jsname}.js";
        $auth = new AuthService();
        $authNode = $auth->nodeList();
        $config = [
            'entrance'    => $this->entrance,//入口
            'modulename'    => $modulename,
            'addonname'    => $this->addon,
            'moduleurl'    => rtrim(url("/{$modulename}", [], false), '/'),
            'controllername'       =>$controllername,
            'actionname'           => $actionname,
            'requesturl'          => $requesturl,
            'jspath' => "{$jspath}",
            'autojs'           => $autojs,
            'authNode'           => $authNode,
            'superAdmin'           => session('admin.id')==1,
            'lang'           =>  strip_tags( Lang::getLangset()),
            'site'           =>  syscfg('site'),
            'upload'           =>  syscfg('upload'),

        ];
        //保留日志
        $logdata = [
            'module'=>$this->module,
            'controller'=>$this->controller,
            'action'=>$this->action,
            'addons'=>$this->addon,
            'url'=>$requesturl,
        ];
        AdminLogService::instance()->saveaddonslog($logdata);
        View::assign('config',$config);
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    //自动加载语言
    protected function loadlang($name)
    {
        $lang = Cookie::get('think_lang');
        return Lang::load([
            $this->addon_path.$this->module.DS . 'lang' . DS . $lang . DS . str_replace('.', '/', $name) . '.php',
            $this->addon_path.$this->module.DS . 'lang' . DS . $lang . '.php',
        ]);
    }

    /**
     * 组合参数
     * @param null $searchfields
     * @param null $relationSearch
     * @return array
     */
    protected function buildParames($searchfields=null,$relationSearch=null,$withStatus=true)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $page = $this->request->param('page',1);
        $limit = $this->request->param('limit',15) ;
        $filters = $this->request->get('filter','{}') ;
        $ops = $this->request->param('op','{}') ;
        $sort = $this->request->get("sort", !empty($this->modelClass) && $this->modelClass->getPk() ? $this->modelClass->getPk() : 'id');
        $order = $this->request->get("order", "DESC");
        $filters = htmlspecialchars_decode(iconv('GBK','utf-8',$filters));
        $filters = json_decode($filters,true);
        $ops = json_decode($ops, true);
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->modelClass)) {
                $name = $this->modelClass->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) : $item;
            }
            unset($item);
            $sort= implode(',', $sortArr);
        }else{
            $sort = ["$sort"=>$order];
        }
        $where = [];
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        foreach ($filters as $key => $val) {
            $op = isset($ops[$key]) && !empty($ops[$key]) ? $ops[$key] : '%*%';
            $key =stripos($key, ".") === false ? $tableName . $key :$key;
            switch (strtolower($op)) {
                case '=':
                    $where[] = [$key, '=', $val];
                    break;
                case '%*%':
                    $where[] = [$key, 'LIKE', "%{$val}%"];
                    break;
                case '*%':
                    $where[] = [$key, 'LIKE', "{$val}%"];
                    break;
                case '%*':
                    $where[] = [$key, 'LIKE', "%{$val}"];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $op = $op == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $op = $op == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$key, $op, $arr];
                    break;
                case 'RANGE':
                    [$beginTime, $endTime] = explode(' - ', $val);
                    $where[] = [$key, '>=', strtotime($beginTime)];
                    $where[] = [$key, '<=', strtotime($endTime)];
                    break;
                case 'NOT RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $op = $op == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $op = $op == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$key, str_replace('RANGE', 'BETWEEN', $op) . ' time', $arr];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$key, strtolower(str_replace('IS ', '', $op))];
                    break;
                default:
                    $where[] = [$key, $op, "%{$val}%"];
            }

        }
        if($withStatus)$where[]=['status','<>',-1];
        return [$page, $limit,$sort,$where];
    }
    /**
     * 刷新Token
     */
    protected function token()
    {
        $check = $this->request->checkToken('__token__', $this->request->param());
        if (false === $check) {
            $this->error(lang('Token verification error'), '', ['__token__' => $this->request->buildToken()]);
        }
        //刷新Token
        $this->request->buildToken();
    }





}