<?php


namespace addons\bbs\common\model;
use app\common\model\BaseModel;


class BbsCollect extends BaseModel {
    protected $name = 'addons_bbs_collect';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
