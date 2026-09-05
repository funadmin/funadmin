<?php

namespace think\annotation;

class Service extends \think\Service
{
    use  InteractsWithInject, InteractsWithRoute, InteractsWithModel;

    protected Reader $reader;

    public function boot(Reader $reader)
    {
        $this->reader = $reader;

        //自动注入
        $this->autoInject();

        //注解路由
        $this->registerAnnotationRoute();

        //模型注解方法提示
        $this->detectModelAnnotations();
    }

}
