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
use app\common\traits\ApiAuthentication;
use app\common\traits\JsonResponse;
use think\App;
use think\exception\ValidateException;
use think\facade\Lang;
use think\helper\Str;

class Api extends BaseController
{
    use ApiAuthentication;
    use JsonResponse;

    protected $middleware =[];

    protected array $noNeedLogin = [];
    protected array $noNeedRight = [];
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
        $this->registerApiAuthentication();
    }

    //自动加载语言
    protected function loadlang($name,$app)
    {
        $lang = cookie(config('lang.cookie_var'));
        // 安全检查: 防止路径遍历
        if(!empty($lang) && !preg_match('/^[a-zA-Z0-9_-]+$/', $lang)){
            return false;
        }
        // 安全检查: $name 只允许字母、数字、下划线、点
        if(!empty($name) && !preg_match('/^[a-zA-Z0-9_.]+$/', $name)){
            $name = '';
        }
        // 安全检查: $app 只允许字母、数字、下划线，防止路径遍历
        if(!empty($app) && !preg_match('/^[a-zA-Z0-9_]+$/', $app)){
            $app = '';
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
