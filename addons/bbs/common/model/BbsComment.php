<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsComment extends BaseModel {


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function user(){

        return $this->belongsTo('User','user_id','id');
    }

    public function bbs(){

        return $this->belongsTo('Bbs','bbs_id','id');
    }

    public function commLikes(){

        return $this->hasMany('BbsCommentLike','comment_id','id');
    }

    public function commLike()
    {
            return $this->hasMany('BbsCommentLike','comment_id','id');
    }




}
