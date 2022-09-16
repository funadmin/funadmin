<?php
error_reporting(0);

use \think\facade\Db;
use app\cms\model\Category;

/**
 * 获取栏目
 */
if(!function_exists('getCategory')) {
    function getCategory($catid, $field = '', $newCache = false)
    {
        return Category::getCategory($catid, $field, $newCache);
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
/**
 * 获取附件类型
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

if(!function_exists('getTopcateid')) {
    function getTopcateid($cateid)
    {
        $top_cateid=null;
        if($cateid){
            $category = Category::cache(true)->find($cateid);
            if($category && $category->pid == 0){
                $top_cateid = $cateid;
            }else{
                $parentids = explode(',',$category->arrpid);
                $top_cateid = isset($parentids[1]) ? $parentids[1] : $cateid;
            }
        }
        return $top_cateid;

    }
}
/**
 * 获取栏目类型
 */
if(!function_exists('getCategory')) {
    function getCategory($catid, $field = '', $newCache = false)
    {
        return Category::getCategory($catid, $field, $newCache);
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