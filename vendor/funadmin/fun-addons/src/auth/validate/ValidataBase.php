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
 * Date: 2017/8/2
 */
namespace fun\auth\validate;
use fun\auth\Send;

/**
 * 公共验证码方法
 * Class Common
 * @package app\auth\validate
 */
class ValidataBase
{
    use Send;
    /**
     * 默认支持验证规则
     * 更多验证规则请使用原生验证器
     * @var array
     */
    public  $dataRule = ['require','int','mobile'];

    /**
     * 接口参数公共验证方法
     * @param array $rule
     * @param array $data
     */
    function validateCheck($rule = [],$data = []){
        if(is_array($rule) && is_array($data)){
            foreach ($rule as $k => $v){
                if(!in_array($v,$this->dataRule)){
                    $this->error('验证规则只支持require，int',[],401);
                }
                if(!isset($data[$k]) || empty($data[$k])){
                    $this->error($k.'不能为空',[],401);
                }else{
                    if($v == 'int'){
                        if(!is_numeric($data[$k])){
                            $this->error($k.'类型必须为'.$v,[],401,);
                        }
                    }elseif ($v == 'mobile'){
                        if(!preg_match('/^1[3-9][0-9]\d{8}$/',$data[$k])){
                            $this->error($k.'手机号格式错误',[],401);
                        }
                    }
                }
            }
        }else{
            $this->error('验证数据格式为数组',[],401,);
        }

    }
}