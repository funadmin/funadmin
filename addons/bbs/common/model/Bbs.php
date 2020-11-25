<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/26
 */
namespace app\common\model;

use app\common\model\Common;

class Bbs extends Common {

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    //用户表
    public function user(){

        return  $this->belongsTo('User','user_id','id');
    }
    //文章关联栏目表
    public function cate(){

        return  $this->belongsTo('BbsCate','pid','id');
    }
    
    //文章关联评论
    public function comment()
    {
        return $this->hasMany('BbsComment','bbs_id','id');
    }


}
