<?php


namespace app\common\model;


class BbsLink extends Common {

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public static function getlink(){

        $link = self::where('status',1)->order('id desc,sort desc')->cache(3600)->select();
        return $link;
    }
}
