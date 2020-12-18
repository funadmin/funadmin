<?php

namespace addons\bbs\common\model;

use app\common\model\BaseModel;
use app\common\model\Member;

class Bbs extends BaseModel
{

    protected $name = 'addons_bbs';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    //用户表
    public function member()
    {

        return $this->belongsTo(Member::class, 'user_id', 'id');
    }

    //文章关联栏目表
    public function category()
    {

        return $this->belongsTo(BbsCategory::class, 'pid', 'id');
    }

    //文章关联评论
    public function comment()
    {
        return $this->hasMany(BbsComment::class, 'bbs_id', 'id');
    }


}
