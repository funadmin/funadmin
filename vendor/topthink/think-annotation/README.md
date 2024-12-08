# think-annotation for ThinkPHP6

> PHP8版本

## 安装

> composer require topthink/think-annotation

## 配置

> 配置文件位于 `config/annotation.php`

## 使用方法

### 路由注解

~~~php
<?php

namespace app\controller;

use think\annotation\Inject;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Middleware;
use think\annotation\route\Resource;
use think\annotation\route\Route;
use think\Cache;
use think\middleware\SessionInit;

#[Group("bb")]
#[Resource("aa")]
#[Middleware([SessionInit::class])]
class IndexController
{

    #[Inject]
    protected Cache $cache;

    public function index()
    {
        //...
    }

    #[Route('GET','xx')]
    public function xx()
    {
        //...
    }
    
    #[Get('cc')]
    public function cc()
    {
        //...
    }
}

~~~

> 默认会扫描controller目录下的所有类  
> 可对个别目录单独配置

```php
//...
    'route'  => [
        'enable'      => true,
        'controllers' => [
            app_path('controller/admin') => [
                'name'       => 'admin/api',
                'middleware' => [],
            ],
            root_path('other/controller')
        ],
    ],
//...
```

### 模型注解

~~~php
<?php

namespace app\model;

use think\Model;
use think\annotation\model\relation\HasMany;

#[HasMany("articles", Article::class, "user_id")]
class User extends Model
{

    //...
}
~~~


