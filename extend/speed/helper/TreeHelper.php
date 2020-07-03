<?php

namespace speed\helper;

class TreeHelper
{
    /**
     * 无线分类
     */
    public static function getTree($arr,$pid=0){

        $list =array();
        foreach ($arr as $k=>$v){
            if ($v['pid'] == $pid){
                $v['child'] = self::getTree($arr,$v['id']);
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * 无限分类-权限
     * @param $cate            栏目
     * @param string $lefthtml 分隔符
     * @param int $pid         父ID
     * @param int $lvl         层级
     * @return array
     */
    public static function cateTree($cate , $lefthtml = '|— ' , $pid = 0 , $level = 0 ){
        $arr = array();
        foreach ($cate as $v){
            if ($v['pid'] == $pid) {
                $v['level']      = $level + 1;
                $v['lefthtml'] = str_repeat($lefthtml,$level);
                $v['ltitle']   = $v['lefthtml'].$v['title'];
                $arr[] = $v;
                $arr = array_merge($arr, self::cateTree($cate, $lefthtml, $v['id'], $level+1));
            }
        }
        return $arr;
    }

    //分类树
    public static function categoryTree($cate , $lefthtml = '|— ' , $pid = 0 , $level = 0 ){
        $arr = array();
        foreach ($cate as $v){
            if ($v['pid'] == $pid) {
                $v['level']      = $level + 1;
                $v['lefthtml'] = str_repeat($lefthtml,$level);
                $v['lcatename']   = $v['lefthtml'].$v['catename'];
                $arr[] = $v;
                $arr = array_merge($arr, self::categoryTree($cate, $lefthtml, $v['id'], $level+1));
            }
        }
        return $arr;
    }

    /**
     * 传递一个父级分类ID返回所有子分类
     * @param $cate
     * @param $pid
     * @return array
     */
    public static function getChildsRule($rules, $pid)
    {
        $arr = [];
        foreach ($rules as $v) {
            if ($v['pid'] == $pid) {
                $arr[] = $v;
                $arr[] = $v;
                $arr = array_merge($arr, self::getChildsRule($rules, $v['id']));
            }
        }
        return $arr;
    }

    /**
     * 权限设置选中状态
     * @param $cate  栏目
     * @param int $pid 父ID
     * @param $rules 规则
     * @return array
     */
    public static function authChecked($cate , $pid = 0,$rules){
        $list = [];
        $rulesArr = explode(',',$rules);
        foreach ($cate as $v){
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                if(self::authChecked($cate,$v['id'],$rules)){
                    $v['children'] =self::authChecked($cate,$v['id'],$rules);
                }else{
                    if (in_array($v['id'], $rulesArr)) {
                        $v['checked'] = true;
                    }
                }
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * 权限多维转化为二维
     * @param $cate  栏目
     * @param int $pid 父ID
     * @param $rules 规则
     * @return array
     */
    public static function authNormal($cate){
        $list = [];
        foreach ($cate as $v){
            $list[]['id'] = $v['id'];
//        $list[]['title'] = $v['title'];
//        $list[]['pid'] = $v['pid'];
            if (!empty($v['children'])) {
                $listChild =  self::authNormal($v['children']);
                $list = array_merge($list,$listChild);
            }
        }
        return $list;
    }


}