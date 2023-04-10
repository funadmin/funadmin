## ThinkPHP6 自动生成用于IDE提示的注释

### 安装

~~~
composer require topthink/think-ide-helper
~~~

### 1.模型注释

~~~
//所有模型
php think ide-helper:model

//指定模型
php think ide-helper:model app\\model\\User app\\model\\Post
~~~

#### 可选参数
~~~
--dir="models" [-D] 指定自动搜索模型的目录,相对于应用基础目录的路径，可指定多个，默认为app/model

--ignore="app\\model\\User,app\\model\\Post" [-I] 忽略的模型，可指定多个

--overwrite [-O] 强制覆盖已有的属性注释

--reset [-R] 重置模型的所有的注释
~~~
