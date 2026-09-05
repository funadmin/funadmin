<?php

declare(strict_types=1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Language extends BaseModel
{
    use SoftDelete;

    protected $name = 'language';
}
