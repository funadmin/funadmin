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

namespace addons\cms\common\taglib;

use think\template\TagLib;

class Ftag extends Taglib
{

    // 标签定义
    protected $tags = [
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'template'  => ['attr' => 'file', 'close' => 0],
        'debris'    => ['attr' => 'tid,id,cache,order', 'close' => 1, ],//碎片
        'lmy'       => ['attr' => 'where,page,num,cateid,id,sql,cache,order,page,field', 'close' => 1, ],//碎片
        'nav'       => ['attr' => 'order,cache,pid','close' => 1, ],//导航
        'tags'      => ['attr' => 'where,num,cache,order','close' => 1, ],//导航
        'links'     => ['attr' => 'cache,order,num', 'close' => 1, ],//碎片
        'adv'       => ['attr' => 'cache,order,pid,id', 'close' => 1, ],//碎片
        'category'  => ['attr' => 'where,field,order,cache,cateid', 'close' => 1,],//分类表
        'query'     => ['attr' => 'table,sql,where,field,num,page,order,cache', 'close' => 1, ],//万能标签

    ];
    //碎片
    public function tagDebris($tag, $content){
        $cache   =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $tid     =  isset($tag['tid']) && trim($tag['tid']) ? trim($tag['tid']) : 0;
        $id      =  isset($tag['id']) && trim($tag['id']) ? trim($tag['id']) : 0;
        $order   =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';



        $parseStr = <<<EOD
<?php 
      \$debrisModel = new \\app\\common\\model\\CmsDebris();
        if ({$id}) {
            \$data = \$debrisModel->find({$id});
        } elseif ({$tid}) {
            \$data = \$debrisModel->where('status', 1)
                ->where('tid', {$tid})->order('{$order}')
                ->cache({$cache})->select();
        } else {
            \$data = \$debrisModel->where('status', 1)
                ->order('{$order}')->cache({$cache})->select();
        }
?>
{$content}
EOD;
        return $parseStr;

    }

//标签
    public function tagTags($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量
        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $where =  isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';
        $num   =  isset($tag['num']) && trim($tag['num']) ? trim($tag['num']) : 5;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $data = 'tags';
        $parseStr = <<<EOD
<?php 
        \$CmsTags = new \\app\\common\\model\\CmsTags();
        \${$data}  = \$CmsTags->where('{$where}')->order('{$order}')->limit('{$num}')->cache({$cache})->select();
?>

<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>

EOD;
        return $parseStr;

    }


    //广告
    public function tagAdv($tag, $content){
        $cache =  isset($tag['cache']) && (int)$tag['cache'] ? (int)($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id asc';
        $id =  isset($tag['id']) && trim($tag['id'])? trim($tag['id']): '0';
        $pid   = (isset($tag['pid'])) ? ((substr($tag['pid'], 0, 1) == '$') ? $tag['pid'] : (int) $tag['pid']) : 0;
        if(!$id){
            $parseStr = <<<EOD
<?php 

 \$data  = \\app\\common\\model\\CmsAdv::where('status',1)->where('pid',{$pid})->order('{$order}')->cache({$cache})->select();
   
?>
{$content}
EOD;
        }else{


            $parseStr = <<<EOD
<?php 

 \$data  = \\app\\common\\model\\CmsAdv::where('status',1)->find({$id});
   
?>
{$content}
EOD;
        }

        return $parseStr;

    }

    //友情链接
    public function tagLinks($tag, $content){
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $num =  isset($tag['num']) && trim($tag['num'])? trim($tag['num']): 0;
        $data = 'links';
        $parseStr = <<<EOD
<?php 

\$linkModel = new \\app\\common\\model\\CmsLink();
if('{$num}'){
 \${$data}  = \$linkModel->where('status',1)->order('{$order}')->limit($num)->cache({$cache})->select();

}else{
 \${$data}  = \$linkModel->where('status',1)->order('{$order}')->cache({$cache})->select();

}
   
?>

<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>

EOD;
        return $parseStr;

    }

    //导航
    public function tagNav($tag, $content){
        $cache =  isset($tag['cache']) && (int)$tag['cache'] ? (int)($tag['cache']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id asc';
        $pid   = (isset($tag['pid'])) ? ((substr($tag['pid'], 0, 1) == '$') ? $tag['pid'] : (int) $tag['pid']) : 0;


        $parseStr = <<<EOD
<?php 
        \$data  = \\app\\common\\model\\CmsCategory::where('status',1)->where('is_menu',1)->where('pid',{$pid})->order('{$order}')->cache({$cache})->select();
        if(!\$data){
            return false;
        }
        foreach(\$data as &\$v){
            if(\$v['type']!=2){
                \$v['url'] = url('index/lists',['cateid'=>\$v['id']]);
            }
        }
?>
{$content}
EOD;
        return $parseStr;

    }


    //分类
    public function tagCategory($tag, $content){
        $cache =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        $cateid   = (isset($tag['cateid'])) ? ((substr($tag['cateid'], 0, 1) == '$') ? $tag['cateid'] : (int) $tag['cateid']) : 0;
        $order =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'id desc';
        $field =  isset($tag['field']) && trim($tag['field'])? trim($tag['field']): '*';
        $where =  isset($tag['where']) && trim($tag['where'])? trim($tag['where']): '';

       if(isset($cateid) && $cateid){

            $parseStr = <<<EOD
<?php 
    \$data = \\think\\facade\\Db::name('cms_category')->where('{$where}')->where('pid',{$cateid})->where('status',1)->order('{$order}')->field('{$field}')->cache({$cache})->select();
    foreach(\$data as &\$v){
        if(\$v['type']!=2){
            \$v['url'] = url('index/lists',['cateid'=>\$v['id']]);
        }
    }
?>
{$content}
EOD;
        }else{
            return false;
        }


        return $parseStr;

    }

    /**
     * LMY标签 获取各种模型列表
     */
    public function tagLmy($tag, $content){
            $key    = !empty($tag['key']) ? $tag['key'] : 'i';
            $item  =  empty($tag['item']) ? 'vo' : $tag['item'];//循环变量名
            $cache      =  isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
            $cateid     =  isset($tag['cateid']) ? ((substr($tag['cateid'], 0, 1) == '$') ? $tag['cateid'] : (int) $tag['cateid']) : 0;
            $sql        =  isset($tag['sql']) && trim($tag['sql']) ? trim($tag['sql']) : '';
            $where        =  isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';
            $order      =  isset($tag['order']) && trim($tag['order'])? trim($tag['order']): 'sort asc';
            $field      =  isset($tag['field']) && trim($tag['field'])? trim($tag['field']): '*';
            $page       =  isset($tag['page']) ? ((substr($tag['page'], 0, 1) == '$') ? $tag['page'] : (int) $tag['page']) : 1;
            $num        =  isset($tag['num'])  && intval($tag['num']) ?  intval($tag['num']):10;
            $data = 'modulelist';


            if($sql){
                if(strpos($sql,'insert')!==false || strpos($sql,'update')!==false || strpos($sql,'delete')!==false ){
                  return false;
                }else{
                    $parseStr = <<<EOD
<?php 
    \$data = \\think\\facade\\Db::query('{$sql}');
?>
{$content}
EOD;
                }


            } elseif($cateid){

                $categoryModule =  new  \app\common\model\CmsCategory();
                $category = $categoryModule->where('status',1)->find($cateid);
                $tablename  = $category->module;
                $childid= $category->arrchildid;
                $cateid = $childid.','.$cateid;
                $parseStr = <<<EOD
<?php 
    \$table = \\think\\facade\\Db::name('{$tablename}');
     \${$data}  = \$table->where('{$where}')->where('status',1)->whereIn('cateid','{$cateid}')->order('{$order}')->field('{$field}')->cache({$cache})->paginate(['list_rows' => {$num} ,'page' =>{$page}]);
?>

<?php foreach(\${$data} as \${$key}=>\${$item}):?>
{$content}
<?php endforeach;?>

EOD;
            }




        return $parseStr;

    }



        /**
     * Queyry 万能标签
     */
    public function tagQuery($tag, $content)
    {
        //缓存时间
        $cache = isset($tag['cache']) && intval($tag['cache']) ? intval($tag['cache']) : 0;
        //每页显示总数
        $num = isset($tag['num']) && intval($tag['num']) > 0 ? intval($tag['num']) : 10;
        //排序
        $order = isset($tag['order']) && trim($tag['order']) ? trim($tag['order']) : 'id desc';
        $field = isset($tag['field']) && trim($tag['field']) ? trim($tag['field']) : '*';
        $where = isset($tag['where']) && trim($tag['where']) ? trim($tag['where']) : '';

        //当前分页参数
        $page = $tag['page'] = (isset($tag['page'])) ? ((substr($tag['page'], 0, 1) == '$') ? $tag['page'] : (int) $tag['page']) : 1;

        if (isset($tag['sql'])) {
            $sql = str_replace(array("think_", "lm_"), config('database.connections.mysql.prefix'), strtolower($tag['sql']));
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
        $sql = str_replace(array("think_", "lm_"), config("database.connections.mysql.prefix"), $sql );
        //如果使用table参数方式，使用类似tp的查询语言效果
        if (isset($table) && $table) {
            $table = strtolower($table);
            $parseStr =<<<EOD
<?php

   \$tabEXOdel = \\think\\facade\\Db::name(strtolower('{$table}'));
    if(!empty('{$where}')){
            \$data =  \$tabEXOdel->whereRaw('{$where}')->whereRaw('status=1')->cache({$cache})->field('{$field}')->order('{$order}')->paginate(['list_rows' => {$num} ,'page' =>{$page}]);
    }else{
        \$data =  \$tabEXOdel->whereRaw('status=1')->order('{$order}')->field('{$field}')->cache({$cache})->paginate(['list_rows' => {$num} ,'page' =>{$page}]);

    }
 
    \$pages = \$data->render();
   
   
?>

{$content}
EOD;

        }else{

            $tagString =implode('',$tag);
            $parseStr =<<<EOD
<?php
    
    \$cacheID = \\EXO\\helper\\StringHelper::uuid('md5','{$tagString}');
    if(!empty('{$cache}') and !\\think\\facade\\Cache::get(\$cacheID)){
      \$order = !empty('{$tag["order"]}') ? ' ORDER BY {$order}' :'';
        if(!empty('{$page}')){
            \$count = count(\\think\\facade\\Db::query('{$sql}'));//总计条数
           
            \$sql = '{$sql}' . \$order . ' limit ?,?';
            \$data = \\think\\facade\Db::query(\$sql,[({$page}-1)*{$num},{$num}]);
            \$pages = \\app\\cms\\paginator\\Layui::make(\$data,{$num},{$page},\$count,false,['path'=>\\app\\cms\\paginator\\Layui::getCurrentPath(),'query'=>request()->param()]);
            \$pages = \$pages->render();
        }else{
            \$sql ='{$sql}'  .  \$order . ' limit {$num}  ';
            \$data = \\think\\facade\\Db::query(\$sql);
        }
        \\think\\facade\\Cache::set(\$cacheId,\$data,{$cache});
        
    }elseif(\\think\\facade\\Cache::get(\$cacheID)){
    
        \$data = \\think\\facade\\Cache::get(\$cacheID);
    
    }else{
    
       \$order = !empty('{$tag["order"]}') ? ' ORDER BY {$order}' :'';
        if(!empty('{$page}')){
            \$count = count(\\think\\facade\\Db::query('{$sql}'));//总计条数
           
            \$sql = '{$sql}' . \$order . ' limit ?,?';
            \$data = \\think\\facade\Db::query(\$sql,[({$page}-1)*{$num},{$num}]);
            \$pages = \\app\\cms\\paginator\\Layui::make(\$data,{$num},{$page},\$count,false,['path'=>\\app\\cms\\paginator\\Layui::getCurrentPath(),'query'=>request()->param()]);
            \$pages = \$pages->render();
        }else{
            \$sql ='{$sql}'  .  \$order . ' limit {$num}  ';
            \$data = \\think\\facade\\Db::query(\$sql);
        }
    
    }
 
?>
{$content}
EOD;

        }

    return $parseStr;

    }








}
