<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsCommentLike extends BaseModel {

    protected $name = 'addons_bbs_comment_like';
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }



}
