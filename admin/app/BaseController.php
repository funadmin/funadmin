<?php
declare (strict_types = 1);

namespace app;

use LogicException;
use support\Request;
use Webman\Validation\Validator;


/**
 * 控制器基础类
 */
abstract class BaseController
{
    protected Request $request {
        get => request() ?? throw new LogicException('当前请求上下文不存在');
    }

    /**
     * 是否批量验证
     * @var bool
     */
    protected bool $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected array $middleware = [];

    public function __construct()
    {
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  mixed $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws \Webman\Validation\ValidationException
     */
    protected function validateData(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $validator = Validator::make($data, $validate, $message);
        } else {
            [$class, $scene] = array_pad(explode('.', (string) $validate, 2), 2, null);
            if (!is_subclass_of($class, Validator::class)) {
                throw new LogicException('验证器必须继承 Webman Validator');
            }
            $validator = $class::make($data);
            if ($scene !== null) {
                $validator = $validator->withScene($scene);
            }
        }
        $validator->validate();
        return true;
    }


}
