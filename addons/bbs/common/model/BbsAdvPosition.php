<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsAdvPosition extends BaseModel {
    protected $name = 'addons_bbs_adv_pos';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}
