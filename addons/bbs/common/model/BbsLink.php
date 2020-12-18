<?php


namespace addons\bbs\common\model;

use app\common\model\BaseModel;

class BbsLink extends BaseModel {
    protected $name = 'addons_bbs_link';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public static function getlink(){

        $link = self::where('status',1)->order('id desc,sort desc')->cache(3600)->select();
        return $link;
    }
}
