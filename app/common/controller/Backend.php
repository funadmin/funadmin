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
use app\BaseController;
use app\common\model\Languages;
use app\common\traits\Jump;
use app\common\traits\Curd;
use think\App;
use think\captcha\facade\Captcha;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Lang;
use app\backend\middleware\CheckRole;
use app\backend\middleware\ViewNode;
use app\backend\middleware\SystemLog;

class Backend extends BaseController
{
    use Jump,Curd;

    protected $middleware = [
        CheckRole::class =>['except'=>['enlang','verify']],
        ViewNode::class,
        SystemLog::class
    ];

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
    /**
     * 下拉选项条件
     * @var string
     */

    protected $selectMap =[];

    protected $allowModifyFields = [
        'status',
        'sort',
        'title',
        'auth_verify',
    ];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

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
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        //模板管理
        $this->layout && $this->app->view->engine()->layout($this->layout);
        $controller = parse_name($this->request->controller(),1);
        $controller = strtolower($controller);
        if($controller!=='ajax'){
            $this->loadlang($controller,app()->http->getName());
        }
        //过滤参数
        $this->pageSize = input('limit', 15);
        $this->page = input('page', 1);
    }

    public function enlang()
    {
        $lang = $this->request->get('lang');
        $language = Languages::where('name',$lang)->find();
        if(!$language) $this->error(lang('please check language config'));
        if(strtolower($lang)=='zh-cn' || !$lang){
            Cookie::set('think_lang', 'zh-cn');
        }else{
            Cookie::set('think_lang', $lang);
        }
        Cache::clear();
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
    protected function loadlang($name,$app)
    {
        $lang = cookie(config('lang.cookie_var'));
        if($app && $app!=='backend'){
            $res =  Lang::load([
                $this->app->getBasePath() .'backend'. DS . 'lang' . DS . $lang . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang  . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
            ]);
       }else{
            $res = Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . '.php',
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
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
            $this->error($e->getMessage());
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
            $this->error(lang('Token verify error'));
        }
    }

    /**
     * 建立Token
     */
    protected function token()
    {
        return $this->request->buildToken();
    }

}