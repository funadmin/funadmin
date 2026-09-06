<?php


namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class Plugin extends BaseModel {

    /**
     * @var bool
     */
    use LaravelSoftDelete;


}
