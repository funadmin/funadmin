<?php

namespace app\common\service;

use think\facade\Event;

class OssService extends AbstractService
{
    /**
     * @param $driver 驱动
     * @param $object 远程地址
     * @param $path 本地地址
     * @param $save 本地是否保存
     * @return mixed
     */
    public function uploads($driver,$object, $path,$save)
    {
        $param = [
            'osspath'=>$object,
            'localpath'=>$path,
            'save'=>$save,
        ];
        return Event::trigger('OssUpload', $param, true);
    }
}