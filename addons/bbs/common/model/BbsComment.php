<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;
use app\common\model\Member;

class BbsComment extends BaseModel {

    protected $name = 'addons_bbs_comment';
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function member(){

        return $this->belongsTo(Member::class,'member_id','id');
    }

    public function bbs(){

        return $this->belongsTo(Bbs::class,'bbs_id','id');
    }

    public function commLikes(){

        return $this->hasMany(BbsCommentLike::class,'comment_id','id');
    }


}
