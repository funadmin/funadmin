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
use app\BaseController;
use app\common\traits\Jump;
use app\common\traits\Curd;
use think\App;
use think\captcha\facade\Captcha;
use think\exception\ValidateException;
use think\facade\Cookie;
use think\facade\Lang;

class Backend extends BaseController
{
    use Jump;
    use Curd;
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
     * 模板布局,
     * @var string|bool
     */
    protected $layout = 'layout/main';
    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';

    protected $allowModifyFileds = [
        'status',
        'sort',
        'title',
        'auth_verify',
    ];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;
    public function __construct(App $app)
    {
        parent::__construct($app);
        //模板管理
        $this->layout && $this->app->view->engine()->layout($this->layout);
        $controller = parse_name($this->request->controller(),1);
        $controller = strtolower($controller);
        if($controller!=='ajax'){
            $this->loadlang($controller,'');
        }
        //过滤参数
        $this->pageSize = input('limit', 15);
        //加载语言包
        $this->loadlang($controller,'');
    }
    public function enlang()
    {
        $lang = $this->request->get('lang');
        switch ($lang) {
            case 'zh-cn':
                Cookie::set('think_lang', 'zh-cn');
                break;
            case 'en-us':
                Cookie::set('think_lang', 'en-us');
                break;
            default:
                Cookie::set('think_lang', 'zh-cn');
                break;
        }
        $this->success(lang('Change Success'));
    }

    /**
     * @return \think\Response
     * 验证码
     */
    public function verify()
    {
        return Captcha::create();
    }
    //自动加载语言
    protected function loadlang($name,$addon)
    {
        $lang = Cookie::get('think_lang');
        if($addon){
            $res = Lang::load([
                app()->getRootPath().'addons'.DS.$addon .DS.'backend'.DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
                app()->getRootPath().'addons'.DS.$addon .DS.'backend'.DS . 'lang' . DS . $lang .'.php'
            ]);
        }else{
            $res = Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php'
            ]);
        }
        return $res;
    }
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        try {
            parent::validate($data, $validate, $message, $batch);
            $this->checkToken();
        } catch (ValidateException $e) {
            $this->error($e->getMessage(),'',['token'=>$this->request->buildToken()]);
        }
        return true;
    }

    /**
     * 检测token 并刷新
     *
     */
    protected function checkToken()
    {
        $check = $this->request->checkToken('__token__', $this->request->param());
        if (false === $check) {
            $this->error(lang('Token verify error'), '', ['token' => $this->request->buildToken()]);
        }
    }
    /**
     * 组合参数
     * @param null $searchfields
     * @param null $relationSearch
     * @return array
     */
    protected function buildParames($searchfields=null,$relationSearch=null)
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
        $ops = htmlspecialchars_decode(iconv('GBK','utf-8',$ops));
        $ops = json_decode($ops,true);
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
            switch (strtoupper($op)) {
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
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '>=', ($begin)];
                    }
                    if($end){
                        $where[] = [$key, '<=', ($end)];
                    }
                    break;
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '<=', ($begin)];
                    }
                    if($end){
                        $where[] = [$key, '>=', ($end)];
                    }
                    break;
                case 'RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    if($begin){
                        $where[] = [$key, '>=', strtotime($begin)];
                    }
                    if($end){
                        $where[] = [$key, '<=', strtotime($end)];
                    }

                    break;
                case 'NOT RANGE':
                    $val = str_replace(' - ', ',', $val);
                    $arr = array_slice(explode(',', $val), 0, 2);
                    if (stripos($val, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    [$begin, $end] = [$arr[0],$arr[1]];
                    //当出现一边为空时改变操作符
                    if ($begin !== '') {
                        $where[] = [$key, '<=', strtotime($begin)];
                    } elseif ($end === '') {
                        $where[] = [$key, '>=', strtotime($begin)];
                    }
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
        $where[]=['status','<>',-1];
        return [$page, $limit,$sort,$where];
    }


    /**
     * 刷新Token
     */
    protected function token()
    {
        return $this->request->buildToken('__token__','sha1');
    }






}