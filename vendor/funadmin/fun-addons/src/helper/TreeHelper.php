<?php

namespace fun\helper;

class TreeHelper
{
    /**
     * @param $list
     * @param $title
     * @param $pid
     * @param $parentField
     * @param $child
     * @param $pk
     * @return array
     */
    public static function getTree($list,$title = 'title',$pid=0, $parentField = 'pid',  $child = 'children',$pk = 'id') {
        $tree = array();// 创建Tree
        if(is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] =& $list[$key];
            }
            foreach ($list as $key => $data) {
                $list[$key][$title] = lang($list[$key][$title]);
                // 判断是否存在parent
                $parentId = $data[$parentField];
                if ($pid == $parentId) {
                    $tree[$data[$pk]] =& $list[$key];
                    $tree[$data[$pk]]['isParent'] = true;
                    $tree[$data[$pk]]['parentId'] = 0;
                }else{
                    if (isset($refer[$parentId])) {
                        $parent =& $refer[$parentId];
                        $list[$key]['parentId'] = $parentId;
                        $parent[$child][] =& $list[$key];
                    }
                }
            }
        }
        return $tree;
    }

    /**
     * @param $arr
     * @param $title
     * @param $pid
     * @param $parentField
     * @param $children
     * @return array
     */
    public static function listTotree($arr,$title='title',$pid=0,$parentField='pid',$children= "children"){
        $list =array();
        foreach ($arr as $k=>$v){
            if ($v[$parentField] == $pid){
                $v[$title] = lang($v[$title]);
                $v[$children] = self::getTree($arr,$title,$v['id'],$parentField);
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
