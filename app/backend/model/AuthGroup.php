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
 * Date: 2017/8/2
 */


namespace app\backend\model;


use think\model\concern\SoftDelete;

class AuthGroup extends BackendModel
{

    /**
     * @var bool
     */
    use SoftDelete;


    
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function getAllIdsBypid($pid)
    {
        $res = self::where('pid','in', $pid)->where('status', 1)->select();
        $str = '';
        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $str .= "," . $v['id'];
                $str .= $this->getAllIdsBypid($v['id']);
            }
        }
        return $str;
    }

}