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
 * Date: 2019/9/21
 */

namespace app\common\controller;
use app\common\traits\Curd;
use app\common\traits\Jump;
use fun\addons\Controller;
use think\App;
use think\exception\ValidateException;
use think\facade\Cookie;
use think\facade\Lang;
use think\facade\Request;
use think\facade\View;

class AddonsController extends Controller
{
    use Jump;
    use Curd;
    /**
     * @var
     * 入口
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
    protected $layout = false;
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
    protected $allowModifyFields = [
        'status',
        'title',
        'sort',
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->request = Request::instance();
        //过滤参数
        $this->pageSize = input('limit/d', 15);
        //加载语言包
        $this->loadlang(strtolower($this->controller));
        View::assign('addon',$this->addon);
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
     * @param bool $withStatus
     * @return array
     */
    protected function buildParames($searchFields=null,$relationSearch=null)
    {
        header("content-type:text/html;charset=utf-8"); //设置编码
        $searchFields = is_null($searchFields) ? $this->searchFields : $searchFields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $searchName = $this->request->get("searchName", $searchFields);
        $page = $this->request->param('page/d',1);
        $limit = $this->request->param('limit/d',15) ;
        $filters = $this->request->get('filter','{}') ;
        $ops = $this->request->param('op','{}') ;
        $sort = $this->request->get("sort", !empty($this->modelClass) && $this->modelClass->getPk() ? $this->modelClass->getPk() : 'id');
        $order = $this->request->get("order", "DESC");
//        $filters = htmlspecialchars_decode(iconv('GBK','utf-8',$filters));
        $filters = htmlspecialchars_decode($filters);
        $filters = json_decode($filters,true);
        $ops = htmlspecialchars_decode(iconv('GBK','utf-8',$ops));
        $ops = json_decode($ops,true);
        $tableName = '';
        $where = [];
        if ($relationSearch) {
            if (!empty($this->modelClass)) {
                $name = $this->modelClass->getTable();
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) .' '.$order : $item .' '. $order;
            }
            unset($item);
            $sort= implode(',', $sortArr);
        }else{
            $sort = ["$sort"=>$order];
        }
        if ($search) {
            $searcharr = is_array($searchName) ? $searchName : explode(',', $searchName);
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