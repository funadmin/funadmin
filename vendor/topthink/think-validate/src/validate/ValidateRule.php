<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\validate;

/**
 * Class ValidateRule
 * @package think\validate
 * @method static ValidateRule confirm(mixed $rule, string $msg = '') 验证是否和某个字段的值一致
 * @method static ValidateRule different(mixed $rule, string $msg = '') 验证是否和某个字段的值是否不同
 * @method static ValidateRule egt(mixed $rule, string $msg = '') 验证是否大于等于某个值
 * @method static ValidateRule gt(mixed $rule, string $msg = '') 验证是否大于某个值
 * @method static ValidateRule elt(mixed $rule, string $msg = '') 验证是否小于等于某个值
 * @method static ValidateRule lt(mixed $rule, string $msg = '') 验证是否小于某个值
 * @method static ValidateRule eg(mixed $rule, string $msg = '') 验证是否等于某个值
 * @method static ValidateRule in(mixed $rule, string $msg = '') 验证是否在范围内
 * @method static ValidateRule notIn(mixed $rule, string $msg = '')  验证是否不在某个范围
 * @method static ValidateRule between(mixed $rule, string $msg = '')  验证是否在某个区间
 * @method static ValidateRule notBetween(mixed $rule, string $msg = '')  验证是否不在某个区间
 * @method static ValidateRule length(mixed $rule, string $msg = '')  验证数据长度
 * @method static ValidateRule max(mixed $rule, string $msg = '')  验证数据最大长度
 * @method static ValidateRule min(mixed $rule, string $msg = '')  验证数据最小长度
 * @method static ValidateRule after(mixed $rule, string $msg = '')  验证日期
 * @method static ValidateRule before(mixed $rule, string $msg = '')  验证日期
 * @method static ValidateRule expire(mixed $rule, string $msg = '')  验证有效期
 * @method static ValidateRule allowIp(mixed $rule, string $msg = '')  验证IP许可
 * @method static ValidateRule denyIp(mixed $rule, string $msg = '')  验证IP禁用
 * @method static ValidateRule regex(mixed $rule, string $msg = '')  使用正则验证数据
 * @method static ValidateRule token(mixed $rule='__token__', string $msg = '')  验证表单令牌
 * @method static ValidateRule is(mixed $rule, string $msg = '')  验证字段值是否为有效格式
 * @method static ValidateRule isRequire(mixed $rule = null, string $msg = '')  验证字段必须
 * @method static ValidateRule isNumber(mixed $rule = null, string $msg = '')  验证字段值是否为数字
 * @method static ValidateRule isArray(mixed $rule = null, string $msg = '')  验证字段值是否为数组
 * @method static ValidateRule isInteger(mixed $rule = null, string $msg = '')  验证字段值是否为整形
 * @method static ValidateRule isFloat(mixed $rule = null, string $msg = '')  验证字段值是否为浮点数
 * @method static ValidateRule isMobile(mixed $rule = null, string $msg = '')  验证字段值是否为手机
 * @method static ValidateRule isIdCard(mixed $rule = null, string $msg = '')  验证字段值是否为身份证号码
 * @method static ValidateRule isChs(mixed $rule = null, string $msg = '')  验证字段值是否为中文
 * @method static ValidateRule isChsDash(mixed $rule = null, string $msg = '')  验证字段值是否为中文字母及下划线
 * @method static ValidateRule isChsAlpha(mixed $rule = null, string $msg = '')  验证字段值是否为中文和字母
 * @method static ValidateRule isChsAlphaNum(mixed $rule = null, string $msg = '')  验证字段值是否为中文字母和数字
 * @method static ValidateRule isDate(mixed $rule = null, string $msg = '')  验证字段值是否为有效格式
 * @method static ValidateRule isBool(mixed $rule = null, string $msg = '')  验证字段值是否为布尔值
 * @method static ValidateRule isAlpha(mixed $rule = null, string $msg = '')  验证字段值是否为字母
 * @method static ValidateRule isAlphaDash(mixed $rule = null, string $msg = '')  验证字段值是否为字母和下划线
 * @method static ValidateRule isAlphaNum(mixed $rule = null, string $msg = '')  验证字段值是否为字母和数字
 * @method static ValidateRule isAccepted(mixed $rule = null, string $msg = '')  验证字段值是否为yes, on, true, 或是 1
 * @method static ValidateRule isDeclined(mixed $rule = null, string $msg = '')  验证字段值是否为no, off, false, 或是 0
 * @method static ValidateRule isEmail(mixed $rule = null, string $msg = '')  验证字段值是否为有效邮箱格式
 * @method static ValidateRule isUrl(mixed $rule = null, string $msg = '')  验证字段值是否为有效URL地址
 * @method static ValidateRule activeUrl(mixed $rule, string $msg = '')  验证是否为合格的域名或者IP
 * @method static ValidateRule ip(mixed $rule, string $msg = '')  验证是否有效IP
 * @method static ValidateRule fileExt(mixed $rule, string $msg = '')  验证文件后缀
 * @method static ValidateRule fileMime(mixed $rule, string $msg = '')  验证文件类型
 * @method static ValidateRule fileSize(mixed $rule, string $msg = '')  验证文件大小
 * @method static ValidateRule image(mixed $rule, string $msg = '')  验证图像文件
 * @method static ValidateRule method(mixed $rule, string $msg = '')  验证请求类型
 * @method static ValidateRule dateFormat(mixed $rule, string $msg = '')  验证时间和日期是否符合指定格式
 * @method static ValidateRule unique(mixed $rule, string $msg = '')  验证是否唯一
 * @method static ValidateRule behavior(mixed $rule, string $msg = '')  使用行为类验证
 * @method static ValidateRule filter(mixed $rule, string $msg = '')  使用filter_var方式验证
 * @method static ValidateRule acceptedIf(mixed $rule, string $msg = '')  验证某个字段等于指定的值，则验证中的字段必须为 yes、on、1 或 true
 * @method static ValidateRule declinedIf(mixed $rule, string $msg = '')  验证某个字段等于指定的值，则验证中的字段必须为 no、off、0 或 false
 * @method static ValidateRule requireIf(mixed $rule, string $msg = '')  验证某个字段等于某个值的时候必须
 * @method static ValidateRule requireCallback(mixed $rule, string $msg = '')  通过回调方法验证某个字段是否必须
 * @method static ValidateRule requireWith(mixed $rule, string $msg = '')  验证某个字段有值的情况下必须
 * @method static ValidateRule must(mixed $rule = null, string $msg = '')  必须验证
 */
class ValidateRule
{
    // 验证字段的名称
    protected $title;

    // 当前验证规则
    protected $rule = [];

    // 验证提示信息
    protected $message = [];

    /**
     * 添加验证因子
     * @access protected
     * @param  string    $name  验证名称
     * @param  mixed     $rule  验证规则
     * @param  string    $msg   提示信息
     * @return $this
     */
    protected function addItem(string $name, $rule = null, string $msg = '')
    {
        if ($rule || 0 === $rule) {
            $this->rule[$name] = $rule;
        } else {
            $this->rule[] = $name;
        }

        $this->message[] = $msg;

        return $this;
    }

    /**
     * 添加验证因子集
     * @access protected
     * @param  array     $rules  验证规则
     * @param  array    $msg   提示信息
     * @return ValidateRuleSet
     */
    public static function ruleSet(array $rules, array $msg = [])
    {
        return ValidateRuleSet::rules($rules, $msg);
    }

    /**
     * 获取验证规则
     * @access public
     * @return array
     */
    public function getRule(): array
    {
        return $this->rule;
    }

    /**
     * 获取验证字段名称
     * @access public
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?: '';
    }

    /**
     * 获取验证提示
     * @access public
     * @return array
     */
    public function getMsg(): array
    {
        return $this->message;
    }

    /**
     * 设置验证字段名称
     * @access public
     * @return $this
     */
    public function title(string $title)
    {
        $this->title = $title;

        return $this;
    }

    public function __call($method, $args)
    {
        if ('is' == strtolower(substr($method, 0, 2))) {
            $method = substr($method, 2);
        }

        array_unshift($args, lcfirst($method));

        return call_user_func_array([$this, 'addItem'], $args);
    }

    public static function __callStatic($method, $args)
    {
        $rule = new static();

        if ('is' == strtolower(substr($method, 0, 2))) {
            $method = substr($method, 2);
        }

        array_unshift($args, lcfirst($method));

        return call_user_func_array([$rule, 'addItem'], $args);
    }
}
