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
     * 主键 id
     * @var string
     */
    protected $primaryKey = 'id';
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
     * 下拉选项条件
     * @var string
     */
    protected $selectMap =[];
    /**
     * 允许修改的字段
     */
    protected $allowModifyFields = [
        'status',
        'title',
        'sort',
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

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->request = Request::instance();
        //过滤参数
        $this->pageSize = input('limit/d', 15);
        $this->page = input('page/d', 1);
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
            $this->error(lang('Token verify error'), '', ['__token__' => $this->request->buildToken()]);
        }
    }
    //自动加载语言
    protected function loadlang($name)
    {
        $lang = cookie(config('lang.cookie_var'));
        return Lang::load([
            $this->addon_path.$this->module.DS . 'lang' . DS . $lang . DS . str_replace('.', '/', $name) . '.php',
            $this->addon_path.$this->module.DS . 'lang' . DS . $lang . '.php',
        ]);
    }

    /**
     * 刷新Token
     */
    protected function token()
    {
        return $this->request->buildToken();
    }


}