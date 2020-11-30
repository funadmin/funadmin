### 如果对你有帮助，请帮忙点个赞 star 一下谢谢

+ FunAdmin v1.0

+ QQ群：[775616363](https://jq.qq.com/?_wv=1027&k=f7p5ic6W)

### 前言
FunAdmin v1.0管理系统：为二次开发而生，让开发变得更简单
 + 这是一个有趣的后台管理系统   
 + 这是一款快速、高效、便捷、灵活敏捷的应用开发框架。
 + 系统采用最新版TinkPHP6框架开发，底层安全可靠，数据查询更快，运行效率更高，网站速度更快, 后续随官网升级而升级
 + 密码动态加密,相同密码入库具有唯一性，用户信息安全牢固,告别简单md5加密
 + 自适应前端，桌面和移动端访问界面友好简洁，模块清晰。
 + 强大的表单管理，只需要使用函数即可成就表单 
 + layui采用最新layui2.5.6 框架
 + require.js 模块化开发 一个命令即可打包js,css
 + restful api 接口
 + ...更多功能尽请关注


### 版权信息

FunAdmin 方便二次开发，您可以方便的使用到自己或企业的项目中,你可以免费学习或者使用

本项目包含的第三方源码和二进制文件之版权信息另行标注。
版权所有Copyright © 2018-2020 by FunAdmin [www.FunAdmin.com](https://www.FunAdmin.com) All rights reserved。


若此项目能得到你的青睐，支持开源项目，可以捐赠支持作者持续开发与维护。

![image](docs/images/pay.png)

### 问题反馈
在使用中有任何问题，欢迎反馈给我，可以用以下联系方式跟我交流
QQ群：[775616363](https://jq.qq.com/?_wv=1027&k=GOakxsp6)

Github：https://github.com/FunAdmin/FunAdmin


## 项目目录结构

初始的目录结构如下：

~~~
www  WEB部署目录（或者子目录）
├─addons           插件目录
├─app           目录
│  ├─backend      应用目录
│  ├───controller      控制器目录
│  ├───model      model目录
│  ├───config      config目录
│  ├───route      route目录
│  ├───view      视图目录
│  ├─api      应用目录
│  ├───controller      控制器目录
│  ├───model      model目录
│  ├─ ...            更多类库目录
│  │
│  ├─frontend      应用目录
│  ├───controller      控制器目录
│  ├───model      model目录
│  ├─ ...            更多类库目录
│  ├─common.php         公共函数文件
│  └─event.php          事件定义文件
│
├─config                应用配置目录
│  ├─app_name           应用配置目录
│  │  ├─database.php    数据库配置
│  │  ├─cache           缓存配置
│  │  └─ ...            
│  │
│  ├─app.php            应用配置
│  ├─cache.php          缓存配置
│  ├─cookie.php         Cookie配置
│  ├─database.php       数据库配置
│  ├─log.php            日志配置
│  ├─route.php          路由和URL配置
│  ├─session.php        Session配置
│  ├─template.php       模板引擎配置
│  └─trace.php          Trace配置
│
├─view                 视图目录
│  ├─app_name          应用视图目录
│  └─ ...   
│
├─route                 路由定义目录
│  │  ├─route.php       路由定义文件
│  │  └─ ...   
│
├─public                WEB目录（对外访问目录）
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件




