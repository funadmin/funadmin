# 插件管理与插件业务同级分离

用户已确认：管理模块使用 `app/admin/plugin/`，安装后的后台插件业务使用同级 `app/admin/plugins/{code}/`，移除 `business` 层。源包继续镜像相同相对路径，即 `plugins/{code}/app/admin/plugins/{code}/`。

沿用当前 Webman 自动加载、原生路由扫描、资源发布、数据库迁移、卸载和显式清理机制，不引入根目录 plugins 直接运行的新加载系统。后台 URL 保持 `/admin/plugin/{code}/…`，权限码保持不变；权限资源规范化维持已有数据库资源标识。

同步脚手架、CRUD、Manifest、业务生成白名单、目录发布、资源登记、恢复日志、鉴权、执行代理及内置 market/example 和 CMS 源包。新原生资源标识为 `admin-plugins:{layer}:{code}`。旧 business 路径和登记明确拒绝，不自动清理数据库中的旧安装记录。

保留并行会话业务内容，文件移动只改变位置、命名空间及引用。前端目录、根路径部署和接口地址不变；不执行生产迁移或服务重载。

用户随后要求移除或迁移 BackendModel。该类为 BaseModel 的空继承，采用直接移除的方式：业务模型和生成模板统一使用 `app/common/model/BaseModel.php`，不增加新的空包装类。验证生成模型的持久化、JSON 转换和软删除恢复。
