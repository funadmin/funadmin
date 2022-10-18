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

class AuthRule extends BackendModel
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


    //获取所有子权限id 集合
    public static function getAuthChildIds($id){
        $ids = AuthRule::where('pid',$id)->column('id');
        $list = $ids;
        if($ids){
            foreach ($ids as $k=>$v){
                $ids =  self::getAuthChildIds($v);
                $list = array_merge($ids,$list);
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
    public static function cateTree($cate ,$name='title', $lefthtml = '|---- ' , $pid = 0 , $level = 0 ){
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