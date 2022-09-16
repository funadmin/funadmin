<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */

namespace app\cms\taglib;

use think\template\TagLib;

class Ftag extends Taglib
{

    // 标签定义
    protected $tags = [
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'template'  => ['attr' => 'file', 'close' => 0],
        'debris'    => ['attr' => 'tid,id,cache,order,data', 'close' => 1, ],//碎片
        'fun'       => ['attr' => 'where,page,limit,cateid,id,sql,cache,order,page,field,data', 'close' => 1, ],//碎片
        'nav'       => ['attr' => 'order,cache,pid,data','close' => 1, ],//导航
        'tags'      => ['attr' => 'where,limit,cache,order,data','close' => 1, ],//导航
        'links'     => ['attr' => 'cache,order,limit,data', 'close' => 1, ],//碎片
        'adv'       => ['attr' => 'cache,order,pid,id,data', 'close' => 1, ],//碎片
        'category'  => ['attr' => 'where,field,order,cache,cateid,pid,data', 'close' => 1,],//分类表
        'query'     => ['attr' => 'table,sql,where,field,limit,page,order,cache,data', 'close' => 1, ],//万能标签
    ];
    public function tagTemplate($tag, $content)
    {
        $file      =  isset($tag['file']) && trim($tag['file']) ? trim($tag['file']) : '';
        if(!$file){
            return '';
        }
        $theme = 'default';
        $addonsconfig = get_addons_config('cms');
        if(isset($addonsconfig['theme']) && $addonsconfig['theme']['value']){
            $theme = $addonsconfig['theme']['value'];
        }
        //不是直接指定模板路径的
        if (strpos($file, config('view.view_suffix'))===false) {
            $file = app()->getBasePath() .'cms'.DS.'view'.DS.'index'.DS. $theme . DS . $file .'.'. config('view.view_suffix');
        } else {
            $file = app()->getBasePath() .'cms'.DS.'view'.DS.'index'.DS. $theme . DS . $file;
        }
        //判断模板是否存在
        if (!file_exists($file)) {
            $file = str_replace($theme . '/', 'default/', $file);
            if (!file_exists($file)) {
                return '';
            }
        }
        //读取内容
        $tplContent = file_get_contents($file);
        //解析模板
        $this->tpl->parse($tplContent);
        return $tplContent;
    }
    //碎片
    public function tagDebris($tag, $content){
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $cache   =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $tid     =  isset($tag['tid']) && trim($tag['tid']) ? trim($tag['tid']) : 0;
        $id      =  isset($tag['id']) && trim($tag['id']) ? trim($tag['id']) : 0;
        $order   =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $parseStr = <<<EOF
<?php 
        \$debrisModel = new \\app\\cms\\model\\CmsDebris();
        if ({$id}) {
            \${$data} = \$debrisModel->find({$id});
        } elseif ({$tid}) {
            \${$data} = \$debrisModel->where('status', 1)
                ->where('tid', {$tid})->order('{$order}')
                ->cache({$cache})->select();
        } else {
            \${$data} = \$debrisModel->where('status', 1)
                ->order('{$order}')->cache({$cache})->select();
        }
?>
{$content}
EOF;
        return $parseStr;
    }
    //标签
    public function tagTags($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量
        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $where =  isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';
        $limit   =  isset($tag['limit']) && trim($tag['limit']) ? trim($tag['limit']) : 5;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $parseStr = <<<EOF
<?php 
        \$CmsTags = new \\app\\cms\\model\\CmsTags();
        \${$data}  = \$CmsTags->where('{$where}')->order('{$order}')->limit('{$limit}')->cache({$cache})->select();
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>

EOF;
        return $parseStr;

    }
    //广告
    public function tagAdv($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $cache =  isset($tag['cache']) && (int)$tag['cache'] ? (int)($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id asc';
        $id =  isset($tag['id']) && trim($tag['id'])? trim($tag['id']): '0';
        $pid   = (isset($tag['pid'])) ? ((substr($tag['pid'], 0, 1) == '$') ? $tag['pid'] : (int) $tag['pid']) : 0;
        if(!$id){
            $parseStr = <<<EOF
<?php 
 \${$data} = \\app\\cms\\model\\CmsAdv::where('status',1)->where('pid',{$pid})->order('{$order}')->cache({$cache})->select();
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>
EOF;
        }else{
            $parseStr = <<<EOF
<?php 
 \${$data} = \\app\\cms\\model\\CmsAdv::where('status',1)->cache({$cache})->find({$id});
?>
{$content}
EOF;
        }
        return $parseStr;
    }
    //友情链接
    public function tagLinks($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $limit =  isset($tag['limit']) && trim($tag['limit'])? trim($tag['limit']): 0;
        $parseStr = <<<EOF
<?php 
\$linkModel = new \\app\\cms\\model\\CmsLink();
if('{$limit}'){
 \${$data}  = \$linkModel->where('status',1)->order('{$order}')->limit($limit)->cache({$cache})->select();

}else{
 \${$data}  = \$linkModel->where('status',1)->order('{$order}')->cache({$cache})->select();

}
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>

EOF;
        return $parseStr;
    }
    //导航
    public function tagNav($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $cache =  isset($tag['cache']) && (int)$tag['cache'] ? (int)($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id asc';
        $pid   = (isset($tag['pid'])) ? ((substr($tag['pid'], 0, 1) == '$') ? $tag['pid'] : (int) $tag['pid']) : 0;
        //	1普通列表2 单页，3外链 4栏目页
        $parseStr = <<<EOF
<?php 
    \${$data}  = \\app\\cms\\model\\CmsCategory::where('status',1)->where('is_menu',1)->where('pid',{$pid})->order('{$order}')->cache({$cache})->select()->toArray();
    if(\${$data}){
        foreach(\${$data} as \$k=>\$v){  
            if(\$v['type']==2 || \$v['type']==4){
                \${$data}[\$k]['url'] = addons_url('index/lists',['cateid'=>\$v['id']]);
            }elseif(\$v['type']==1){
                \${$data}[\$k]['url'] = addons_url('index/lists',['cateid'=>\$v['id'],'flag'=>\$v['cateflag']]);
            }
        }
    }
?>
{$content}
EOF;
        return $parseStr;
    }
    //分类
    public function tagCategory($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $id   = (isset($tag['id'])) ? ((substr($tag['id'], 0, 1) == '$') ? $tag['id'] : (int) $tag['id']) : 0;
        $pid   = (isset($tag['pid'])) ? ((substr($tag['pid'], 0, 1) == '$') ? $tag['pid'] : (int) $tag['pid']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $field =  isset($tag['field']) && trim($tag['field'])? trim($tag['field']): '*';
        $where =  isset($tag['where']) && trim($tag['where'])? trim($tag['where']): '';
        $limit =  isset($tag['limit']) && trim($tag['limit'])? trim($tag['limit']): '';
        $parseStr = <<<EOF
<?php 
   if(!empty('{$pid}')){
        if(!empty('{$limit}')){
            \${$data} = \\app\\cms\\model\\CmsCategory::where('{$where}')->where('pid',{$pid})->where('status',1)->order('{$order}')->field('{$field}')->cache({$cache})->limit({$limit})->select();
        }else{
            \${$data} = \\app\\cms\\model\\CmsCategory::where('{$where}')->where('pid',{$pid})->where('status',1)->order('{$order}')->field('{$field}')->cache({$cache})->select();
        }
    }else{
        \${$data} = \\app\\cms\\model\\CmsCategory::where('{$where}')->where('id',{$id})->where('status',1)->order('{$order}')->field('{$field}')->cache({$cache})->select();
    }
    if(\${$data}){
         foreach(\${$data} as \$k=>\$v){  
            if(\$v['type']==2 || \$v['type']==4){
                \${$data}[\$k]['url'] = addons_url('index/lists',['cateid'=>\$v['id']]);
            }elseif(\$v['type']==1){
                \${$data}[\$k]['url'] = addons_url('index/lists',['cateid'=>\$v['id'],'flag'=>\$v['cateflag']]);
            }
        }
    }
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>
EOF;
        return $parseStr;
    }
    /**
     * FUN标签 获取各种模型列表
     */
    public function tagFun($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        $cache      =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $cateid     =  isset($tag['cateid']) ? ((substr($tag['cateid'], 0, 1) == '$') ? $tag['cateid'] : (int) $tag['cateid']) : 0;
        $sql        =  isset($tag['sql']) && trim($tag['sql']) ? trim($tag['sql']) : '';
        $where        =  isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';
        $order      =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'sort asc';
        $field      =  isset($tag['field']) && trim($tag['field'])? trim($tag['field']): '*';
        $page       =  isset($tag['page']) ? ((substr($tag['page'], 0, 1) == '$') ? $tag['page'] : (int) $tag['page']) : 1;
        $limit        =  isset($tag['limit'])  && intval($tag['limit']) ?  intval($tag['limit']):10;
        if($sql){
            if(strpos($sql,'insert')!==false || strpos($sql,'update')!==false || strpos($sql,'delete')!==false ){
                return false;
            }else{
                $sql = str_replace(array("think_", "fun_",'__prefix__'), config('database.connections.mysql.prefix'), strtolower($sql));
                $parseStr = <<<EOF
<?php 
    \${$data} = \\think\\facade\\Db::query('{$sql}');
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>
EOF;
            }
        } elseif($cateid){
            $parseStr = <<<EOF
<?php 
    \$categoryModule =  new \app\cms\model\CmsCategory();
    \$category = \$categoryModule->where('status',1)->find('{$cateid}');
    \$childid= \$category->arrchildid;
    \$cateid = \$childid.','.'{$cateid}';
    \${$data} = \\app\\cms\\model\\CmsForum::where('cateid','in',\$cateid)
        ->where('{$where}')->where('status',1)->order('{$order}')->field('{$field}')
        ->cache($cache)->paginate(['list_rows' => {$limit} ,'page' =>{$page}])->each(function(\$item, \$key){
        \$item->url = addons_url('index/show',['cateid'=>\$item['cateid'],'id'=>\$item['id']]);
        \$modulecontent = \\think\\facade\\Db::name('{\$category->tablename}')->find(\$item['id']);
        if(\$modulecontent){
           \$item->appendData(\$modulecontent);
        }
        return \$item;
    });
?>
<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>
EOF;
        }
        return $parseStr;

    }
    /**
     * Queyry 万能标签
     */
    public function tagQuery($tag, $content)
    {
        $data   =  isset($tag['data']) && trim($tag['data']) ? trim($tag['data']) : 'data';
        //缓存时间
        $cache = isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        //每页显示总数
        $limit = isset($tag['limit']) && intval($tag['limit']) > 0 ? intval($tag['limit']) : 10;
        //排序
        $tag['order'] = isset($tag['order']) && trim($tag['order']) ? trim($tag['order']) : '';
        $order = $tag['order']? $tag['order'] : 'id desc';
        $field = isset($tag['field']) && trim($tag['field']) ? trim($tag['field']) : '*';
        $where = isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';
        //当前分页参数
        $page = $tag['page'] = (isset($tag['page'])) ? ((substr($tag['page'], 0, 1) == '$') ? $tag['page'] : (int) $tag['page']) : 1;
        if (isset($tag['sql'])) {
            $sql = str_replace(array("think_","fun_","__prefix__"), config('database.connections.mysql.prefix'), strtolower($tag['sql']));
        }
        if (isset($tag['table'])) {
            $table = str_replace(config('database.connections.mysql.prefix'), '', $tag['table']);
        }
        if (!isset($sql) && !isset($table)) {
            return false;
        }
        //不执行增删改
        if (isset($sql) && (stripos($sql, "delete") !== false || stripos($sql, "insert")!== false || stripos($sql, "update")!== false)) {
            return false;
        }
        $sql = str_replace(array("think_","fun_","__prefix__"), config("database.connections.mysql.prefix"), strtolower($sql) );
        //如果使用table参数方式，使用类似tp的查询语言效果
        if (isset($table) && $table) {
            $table = strtolower($table);
            $parseStr =<<<EOF
<?php

   \$tabEXOdel = \\think\\facade\\Db::name(strtolower('{$table}'));
    if(!empty('{$where}')){
            \${$data}=  \$tabEXOdel->whereRaw('{$where}')->whereRaw('status=1')->cache({$cache})->field('{$field}')->order('{$order}')->paginate(['list_rows' => {$limit} ,'page' =>{$page}]);
    }else{
        \${$data} =  \$tabEXOdel->whereRaw('status=1')->order('{$order}')->field('{$field}')->cache({$cache})->paginate(['list_rows' => {$limit} ,'page' =>{$page}]);
    }
    \$pages = \${$data}->render();
?>
{$content}
EOF;
        }else{
            $tagString =implode('',$tag);
            $parseStr =<<<EOF
<?php
    \$cacheID = \\fun\\helper\\StringHelper::uuid('md5','{$tagString}');
    if(!empty('{$cache}') and !\\think\\facade\\Cache::get(\$cacheID)){
      \$order = !empty('{$tag["order"]}') ? ' ORDER BY {$order}' :'';
        if(!empty('{$page}')){
            \$count = count(\\think\\facade\\Db::query('{$sql}'));//总计条数
            \$sql = '{$sql}' . \$order . ' limit ?,?';
            \${$data} = \\think\\facade\Db::query(\$sql,[({$page}-1)*{$limit},{$limit}]);
            \$pages = \\addons\\cms\\frontend\\paginator\\Layui::make(\${$data},{$limit},{$page},\$count,false,['path'=>\\addons\\cms\\frontend\\paginator\\Layui::getCurrentPath(),'query'=>request()->param()]);
            \$pages = \$pages->render();
        }else{
            \$sql ='{$sql}'  .  \$order . ' limit {$limit}  ';
            \${$data} = \\think\\facade\\Db::query(\$sql);
        }
        \\think\\facade\\Cache::set(\$cacheId,\${$data},{$cache});
        
    }elseif(\\think\\facade\\Cache::get(\$cacheID)){
        \${$data} = \\think\\facade\\Cache::get(\$cacheID);
    }else{
       \$order = !empty('{$tag["order"]}') ? ' ORDER BY {$order}' :'';
        if(!empty('{$page}')){
            \$count = count(\\think\\facade\\Db::query('{$sql}'));//总计条数
            \$sql = '{$sql}' . \$order . ' limit ?,?';
            \${$data} = \\think\\facade\Db::query(\$sql,[({$page}-1)*{$limit},{$limit}]);
            \$pages =  \\addons\\cms\\frontend\\paginator\\Layui::make(\${$data},{$limit},{$page},\$count,false,['path'=>\\addons\\cms\\frontend\\paginator\\Layui::getCurrentPath(),'query'=>request()->param()]);
            \$pages = \$pages->render();
        }else{
            \$sql ='{$sql}'  .  \$order . ' limit {$limit}  ';
            \${$data} = \\think\\facade\\Db::query(\$sql);
        }
    }
?>
{$content}
EOF;
        }
return $parseStr;
    }
}
