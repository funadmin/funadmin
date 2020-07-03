<?php
// +----------------------------------------------------------------------
// | 应用公共文件
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 
// +----------------------------------------------------------------------

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use \think\facade\Db; 
use app\common\model\CmsCategory;
error_reporting(0);


/**
 * 获取http类型
 */
if(!function_exists('getAttach')) {
    function getAttach($ids)
    {
        if(strpos($ids,',')===true || is_numeric($ids)) {
            $attach =  Db::name('attach')->field('id,thumb')->where('id','in',$ids)->select();
        }else{
            return false;
        }

    }
}


/**
 * 获取栏目类型
 */
if(!function_exists('getCategory')) {
    function getCategory($catid, $field = '', $newCache = false)
    {
        return CmsCategory::getCategory($catid, $field, $newCache);
    }
}


/**
 * 返回栏目成绩
 * @param $cateid 栏目id
 * @param $symbol 栏目间隔符
 */
if(!function_exists('cateStep')) {
    function cateStep($cateid, $symbol = ' &gt; ')
    {
        if (getCategory($cateid) == false) {
            return '';
        }
        //获取当前栏目的 父栏目列表
        $arrparentid = array_filter(explode(',', getCategory($cateid, 'arrpid') . ',' . $cateid));
        foreach ($arrparentid as $childid) {
            $url = buildUrl(['cateid' => $childid]);
            $arr[] = '<a title="' . getCategory($childid, 'catename') . '" href="' . $url . '" >' . getCategory($childid, 'catename') . '</a>';
        }
        $str = implode($symbol, $arr);
        return $str;
    }
}
if(!function_exists('buildUrl')) {

    function buildUrl($arr = [], $path = 'index/lists')
    {
        return url($path, $arr);
    }
}