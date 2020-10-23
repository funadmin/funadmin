<?php

namespace fun\helper;

class TreeHelper
{
    /**
     * 无限分类
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


}