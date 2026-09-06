<BR>
<h3 align="center">FunAdmin7.X版本, 全新的AI开发框架，MCP工具让您的开发过程起飞</h3>
<h3 align="center"> <a href="https://www.gitee.com/funadmin/funadmin-docker">
funadmin docker 版本请移步docker仓库
    </a></h3>
<h3 align="center"> <a href="https://www.gitee.com/funadmin/webmanadmin">
funadmin前端和webman开发的后台管理系统
    </a></h3>

-  ✨ 核心特性
-   🚀 智能代码生成：AI自动创建完整的CRUD模板
-   🔍 智能文件搜索：自动定位相关文件并提供精准修改建议
-   🎯 自动化流程：一键生成API接口和菜单配置
-   🧠 上下文理解：AI深度理解项目架构，提供更准确的代码联动
-🛠️ AI编辑工具配置
- 支持的AI编辑工具
```txt
    Trae （尽量使用 trae.ai 国外版）
    Cursor
    Claude Code
    Windsurf
    Codebubby
    其他支持MCP协议的AI编辑器
``` 
```txt   
配置步骤
第一步：启动FunAdmin项目
确保你的项目正在运行 执行下面的命令
```
```
php mcp-server.php

```


第二步：配置AI编辑器
在你的AI编辑工具的配置文件中添加以下MCP配置：
```json
{
"mcpServers": {
        "funadmin": {
            "url":"http://127.0.0.1:8080/mcp"
        }
    }
}
```

<h3 align="center">为梦想而创作：FunAdmin开发框架系统 V8.X最低支持PHP8.1</h3>

<h4 align="center">用爱发电，开源不易，您先点右上角 "Star" 支持一下 谢谢！</h4>
<p align="center">
    <a href="http://www.funadmin.com/">
        <img src="https://img.shields.io/badge/license-Apache2.0-success.svg" />
    </a>
    <a href="https://vuejs.org/">
        <img src="https://img.shields.io/badge/Vue-3.5-42b883.svg" alt="Vue 3">
    </a>
    <a href="http://www.funadmin.com/">
        <img src="https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg" alt="PHP Version">
    </a>
    <a href='https://gitee.com/funadmin/funadmin'>
        <img src='https://img.shields.io/badge/Mysql-%3E%3D5.7-green' alt='MYSQL'></img>
    </a>
    <a href='https://gitee.com/funadmin/funadmin'>
        <img src='https://gitee.com/funadmin/funadmin/badge/star.svg?theme=dark' alt='star'></img>
    </a>
    <a href='https://gitee.com/funadmin/funadmin'>
        <img src='https://gitee.com/funadmin/funadmin/badge/fork.svg?theme=white' alt='fork'></img>
    </a>

</p>

### 用爱发电，开源不易，请先点击右上角 "Star" 支持一下 谢谢！

### 如果您想获悉项目实时更新信息，您可以点右上角"Watch"

### 如果您想参与项目的开发，您可以点右上角"Fork"！

### Github：https://github.com/funadmin/funadmin

### 官方网址：http://www.funadmin.com/

### 插件市场 https://www.funadmin.com/frontend/plugins

### 帮助文档：https://doc.funadmin.com 正在持续更新中...

### 后台演示 加群获取 演示地址：[http://fundemo.funadmin.com/](http://fundemo.funadmin.com/backend) 

### QQ群1：[775616363](https://jq.qq.com/?_wv=1027&k=GOakxsp6)

### QQ群2：[1048893269](https://jq.qq.com/?_wv=1027&k=2pyFqDv3)

## 项目介绍

FunAdmin 是基于 ThinkPHP 8、Vue 3、TypeScript、Vite 与 Element Plus 的前后端分离权限（RBAC）管理框架，集成权限管理、模块管理、插件管理、配置管理、会员管理和 CRUD Workbench 等常用功能，帮助开发者快速构建可维护的管理应用。

+ 支持ThinkPHP 持续升级框架底层;跟随官网脚步
+ 这是一个有趣的后台管理系统，这是可以让你节约时间的系统
+ 这是一款快速、高效、便捷、灵活敏捷的应用开发框架。
+ 系统采用最新版TinkPHP8框架开发，底层安全可靠，数据查询更快，运行效率更高，网站速度更快, 后续随官网升级而升级
+ 密码动态加密,相同密码入库具有唯一性，用户信息安全牢固,告别简单md5加密
+ 自适应前端，桌面和移动端访问界面友好简洁，模块清晰
+ 兼容ie11 + firefox + Chrome +360 等浏览器
+ Vue 3 与 Element Plus 组件化界面，统一使用 TypeScript 开发
+ 内置 `CRUD` 命令行与 CRUD Workbench，帮助您快速开发系统
+ CRUD 命令包括 `crud:inspect`、`crud:validate`、`crud:preview` 和 `crud:generate`；生成前默认预览，已有文件默认拒绝覆盖
+ CRUD 可生成 ThinkPHP Model、Validate、Service、Controller、迁移与 PHP 测试，以及 Vue API、列表、表单、详情与 Vitest 测试
+ 模块化：后端服务与 Admin Web 源码插件边界清晰，插件前端源码安装到 `admin-web/src/modules`，安装或升级返回 `rebuildRequired` 并要求重新构建
+ 数据库统一采用 Laravel 风格公共字段：`id`、`created_at`、`updated_at`、可选 `deleted_at`，排序字段使用 `sort_order`
+ 旧字段删除只能通过维护模式命令 `maintenance:contract-migrate` 执行，并要求备份确认与 checksum 校验
+ Vite 提供开发、类型检查和生产构建能力；提交前运行 `npm --prefix admin-web test`、`npm --prefix admin-web run type-check` 和 `npm --prefix admin-web audit --audit-level=high`
+ 验证构建请显式使用隔离输出目录，例如 `npm --prefix admin-web run build -- --outDir /tmp/funadmin-admin-web-build --emptyOutDir`，避免默认写入 `public/admin-web`
+ 适用范围：可以开发OA、ERP、BPM、CRM、WMS、TMS、MIS、BI、电商平台后台、物流管理系统、快递管理系统、教务管理系统等各类管理软件。
+ restful api 接口,接口使用jwt接口验证等
+ ...更多功能尽请关注

## 环境要求:

* 开启静态重写 (必须)
* PHP >= 8.1
* PDO PHP Extension
* MBstring PHP Extension
* CURL PHP Extension
* ZIP PHP Extension
* Fininfo Extension
* 要求环境支持pathinfo
* Mysql 5.7及以上
* Apache 或 Nginx

### 功能特性

- **严谨规范：** 提供一套有利于团队协作的结构设计、编码、数据等规范。
- **高效灵活：** 清晰的分层设计，解耦设计更能灵活应对需求变更。
- **严谨安全：** 清晰的系统执行流程，严谨的异常检测和安全机制，详细的日志统计，为系统保驾护航。
- **组件化：** 完善的组件化设计，丰富的表单组件，让开发列表和表单更得心应手。无需前端开发，省时省力。
- **简单上手快：** 结构清晰、代码规范、在开发快速的同时还兼顾性能的极致追求。
- **自身特色：** 权限管理、组件丰富、第三方应用多、分层解耦化设计和先进的设计思想。
- **高级进阶：** 分布式、负载均衡、集群、Redis、分库分表。
-

### 插件

- ** CMS内容管理插件（免费）
- ** BBS社区插件
- ** 编辑器插件
- ** 微信管理插件（免费）
- ** 自动生成API接口文档（免费）
- ** 更多请查看 [插件列表](https://www.funadmin.com/plugins)

## 开发者信息

* 系统名称：FunAdmin开发系统框架
* 作者：FunAdmin
* 官网网址：[http://www.funadmin.com/](http://www.funadmin.com/)
* 文档网址：[http://doc.funadmin.com/](http://doc.funadmin.com)
* 开源协议：Apache 2.0

## 鸣谢以下开源项目以及项目中用到的其他开源项目 （排名不分先后，）

- [ThinkPHP](https://www.thinkphp.cn/)
- [Vue](https://vuejs.org/)
- [Element Plus](https://element-plus.org/)
- [Vite](https://vite.dev/)

## 版权信息

FunAdmin 方便二次开发，您可以方便的使用到自己或企业的项目中,你可以免费学习或者使用

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2018-2030 by FunAdmin [www.FunAdmin.com](http://www.funadmin.com)

All rights reserved。

## 若此项目能得到你的青睐，支持开源项目，可以捐赠支持作者持续开发与维护。

![image](doc/images/pay.png)

## 问题反馈

在使用中有任何问题，欢迎反馈给我，可以用以下联系方式跟我交流
QQ群：[775616363](https://jq.qq.com/?_wv=1027&k=GOakxsp6)

Gitee：https://gitee.com/funadmin/funadmin
Github：https://github.com/funadmin/funadmin

## 项目目录结构

初始的目录结构如下：

```
www  WEB部署目录（或者子目录）
├─plugins           插件目录
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
```
