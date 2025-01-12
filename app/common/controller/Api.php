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
 * Date: 2019/9/21
 */

namespace app\common\controller;

use app\BaseController;
use app\common\middleware\ApiAuth;
use app\common\traits\ApiTraits;
use think\App;
use think\exception\ValidateException;
use think\facade\Lang;
use think\helper\Str;

class Api extends BaseController
{
    use ApiTraits;

    protected $middleware =[];

    protected array $noAuth = [];
    protected array $needAuth = [];
    /**
     * @var
     * 模型
     */
    protected $modelClass;

    /**
     * @var
     * 页面大小
     */
    protected $pageSize = 15;
    /**
     * @var
     * 页数
     */
    protected $page = 1 ;

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';
    /**
     * 下拉选项条件
     * @var string
     */
    protected $selectMap =[];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    protected $allowModifyFields = [
        'status',
        'sort',
        'title',
    ];
    /**
     * 关联join搜索
     * @var array
     */
    protected $joinSearch = [];

    /**
     * selectpage 字段
     * @var string[]
     */
    protected $selectpageFields = ['*'];

    /**
     * 隐藏字段
     * @var array
     */
    protected $hiddenFields = [];

    /**
     * 可见字段
     * @var array
     */
    protected $visibleFields = [];

    /**
     * 是否开启数据限制
     * 表示按权限判断/仅限个人
     */
    protected $dataLimit = false;


    protected $dataLimitField = 'member_id';

    /**
     * 导出字段
     * @var string[]
     */

    protected $exportFields = ['*'];
    /**
     * 导入字段
     * @var string[]
     */
    protected $importFields = ['*'];


    public function __construct(App $app)
    {
        parent::__construct($app);
        //过滤参数
        $this->pageSize = input('limit', 15);
        $this->page = input('page', 1);
        $auth = [];
        if(!empty($this->noAuth) && $this->noAuth!=['*']){
            $auth['expect'] = $this->noAuth;
        }
        if(!empty($this->needAuth) && $this->needAuth!=['*']){
            $auth['only'] = $this->needAuth;
        }
        if(!empty($auth)){
            $this->middleware = [
                ApiAuth::class=>$auth,
            ];
        }
    }


    //自动加载语言
    protected function loadlang($name,$app)
    {
        $lang = cookie(config('lang.cookie_var'));
        if(!empty($lang) && Str::contains($lang,'../')){
            return false;
        }
        if($app){
            $res =  Lang::load([
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang  . '.php'
            ]);
        }else{
            $res = Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
                $this->app->getAppPath() . 'lang' . DS . $lang . '.php'
            ]);
        }
        return $res;
    }




}
