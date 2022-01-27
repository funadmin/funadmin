<?php

namespace fun\helper;

class TreeHelper
{
    /**
     * @param array $arr
     * @param int $pid
     * @return array
     */
    public static function getTree($arr,$title='title',$pid=0,$parentField='pid'){
        $list =array();
        foreach ($arr as $k=>$v){
            if ($v[$parentField] == $pid){
                $v[$title] = lang($v[$title]);
                $v['children'] = self::getTree($arr,$title,$v['id'],$parentField);
                $list[] = $v;
            }
        }
        return $list;
    }
    /**
     * 无限分类-权限
     * @param array $cate         栏目
     * @param string $lefthtml 分隔符
     * @param int $pid         父ID
     * @param int $level         层级
     * @return array
     */
    public static function cateTree($cate ,$name='title', $lefthtml = '|— ' , $pid = 0 , $level = 0 ){
        $arr = array();
        foreach ($cate as $v){
            if ($v['pid'] == $pid) {
                $v['level']      = $level + 1;
                $v['lefthtml'] = str_repeat($lefthtml,$level);
                $v['l'.$name]   = $v['lefthtml'].lang($v[$name]);
                $arr[] = $v;
                $arr = array_merge($arr, self::cateTree($cate,$name, $lefthtml, $v['id'], $level+1));
            }
        }
        return $arr;
    }

}