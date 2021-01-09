<h1 align="center"> 为梦想而创作：FunAdmin开发框架系统</h1>

<p align="center">
	<a href="http://www.funadmin.com/">
	    <img src="https://img.shields.io/hexpm/l/plug.svg" />
	</a>
	<a href="https://www.layui.com/">
        <img src="https://img.shields.io/badge/layui-2.5.7-blue.svg" alt="layui">
    </a>
	<a href='https://gitee.com/funadmin/funadmin/stargazers'>
	    <img src='https://gitee.com/funadmin/funadmin/badge/star.svg?theme=white' alt='star'></img>
	</a>
	<a href='https://gitee.com/funadmin/funadmin/members'>
	    <img src='https://gitee.com/funadmin/funadmin/badge/fork.svg?theme=white' alt='fork'></img>
	</a>
</p>

### 如果对您有帮助，您可以点右上角 "Star" 支持一下 谢谢！
### 如果您想获悉项目实时更新信息，您可以点右上角感谢您的支持！
### 官方网址：http://www.funadmin.com/
### 帮助文档：http://docs.funadmin.com/
### QQ群：[775616363](https://jq.qq.com/?_wv=1027&k=GOakxsp6)

## 项目介绍
FunAdmin 基于thinkphp6 +Layui2.5.7+requirejs 开发权限(RBAC)管理框架，框架中集成了权限管理、模块管理、插件管理、数据库管理、后台支持多主题切换、配置管理、会员管理等常用功能模块，以方便开发者快速构建自己的应用。框架专注于为中小企业提供最佳的行业基础后台框架解决方案，执行效率、扩展性、稳定性值得信赖，操作体验流畅，使用非常优化，欢迎大家使用及进行二次开发。

 + 模块化：全新的架构和模块化的开发机制，便于灵活扩展和二次开发。
 + 这是一个有趣的后台管理系统   
 + 这是一款快速、高效、便捷、灵活敏捷的应用开发框架。
 + 系统采用最新版TinkPHP6框架开发，底层安全可靠，数据查询更快，运行效率更高，网站速度更快, 后续随官网升级而升级
 + 密码动态加密,相同密码入库具有唯一性，用户信息安全牢固,告别简单md5加密
 + 自适应前端，桌面和移动端访问界面友好简洁，模块清晰
 + 兼容ie11 + firefox + Chrome +360 等浏览器
 + 强大的表单管理，只需要使用函数即可成就表单 
 + layui采用最新layui2.5.7 框架
 + 适用范围：可以开发OA、ERP、BPM、CRM、WMS、TMS、MIS、BI、电商平台后台、物流管理系统、快递管理系统、教务管理系统等各类管理软件。
 + require.js 模块化开发 一个命令即可打包js,css ; node r.js -o backend-build.js
 + restful api 接口
 + ...更多功能尽请关注

## 环境要求:
* PHP >= 7.2
* PDO PHP Extension
* MBstring PHP Extension
* CURL PHP Extension
* 开启静态重写
* 要求环境支持pathinfo
* Mysql 5.5及以上
* Apache 或 Nginx

### 功能特性
- **严谨规范：** 提供一套有利于团队协作的结构设计、编码、数据等规范。
- **高效灵活：** 清晰的分层设计，解耦设计更能灵活应对需求变更。
- **严谨安全：** 清晰的系统执行流程，严谨的异常检测和安全机制，详细的日志统计，为系统保驾护航。
- **组件化：** 完善的组件化设计，丰富的表单组件，让开发列表和表单更得心应手。无需前端开发，省时省力。
- **简单上手快：** 结构清晰、代码规范、在开发快速的同时还兼顾性能的极致追求。
- **自身特色：** 权限管理、组件丰富、第三方应用多、分层解耦化设计和先进的设计思想。
- **高级进阶：** 分布式、负载均衡、集群、Redis、分库分表。
- **命令行：** 命令行功能，一键管理应用扩展。


## 开发者信息
* 系统名称：FunAdmin管理框架
* 作者：FunAdmin
* 作者QQ：99492909
* 官网网址：[http://www.funadmin.com/](http://www.funadmin.com/)
* 文档网址：[http://docs.funadmin.com/](http://docs.funadmin.com/)
* 开源协议：Apache 2.0

## 后台演示（用户名:admin 密码:123456）

- 演示地址：[http://demo.funadmin.com/](http://demo.funadmin.com/)


## 鸣谢以下开源项目以及项目中用到的其他开源项目 （排名不分先后，）
- [Thinkphp](http://thinkphp.cn)
- [JQuery](http://jquery.com)
- [Layui](http://www.layui.com)
- [Requirejs](https://requirejs.org)
- [Bootstrap](http://www.bootstrap.com)

## 版权信息
FunAdmin 方便二次开发，您可以方便的使用到自己或企业的项目中,你可以免费学习或者使用

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2018-2020 by FunAdmin [www.FunAdmin.com](https://www.FunAdmin.com)

All rights reserved。

## 若此项目能得到你的青睐，支持开源项目，可以捐赠支持作者持续开发与维护。

![image](docs/images/pay.png)

## 问题反馈
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




