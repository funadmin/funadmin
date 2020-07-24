<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */

namespace app\common\controller;

use app\BaseController;
use app\common\traits\Jump;
use think\App;
use think\captcha\facade\Captcha;
use think\exception\ValidateException;

class CommonController extends BaseController
{
    use Jump;

    public function __construct(App $app)
    {
        parent::__construct($app);
    }


    public function enlang()
    {
        $lang = input('langset');
        switch ($lang) {
            case 'zh-cn':
                cookie('think_lang', 'zh-cn');
                break;
            case 'en-us':
                cookie('think_lang', 'en-us');
                break;
            default:
                cookie('think_lang', 'zh-cn');
                break;
        }
        $this->success('切换成功');
    }

    /**
     * @return \think\Response
     * 验证码
     */
    public function verify()
    {
        return Captcha::create();
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
}