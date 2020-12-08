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

namespace addons\cms\common\model;

use addons\cms\common\model\CmsCategory as CategoryModel;
use app\common\model\BaseModel;
use think\facade\Db;

class CmsFiling extends BaseModel
{

    protected $name = 'addons_cms_filing';
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function category(){
        return $this->belongsTo(CategoryModel::class,'cateid','id');
    }
    public static function onBeforeInsert($model){
        $post = request()->post();
        $post['publish_time'] = $post['publish_time']?strtotime($post['publish_time']):time();
        $model->appendData($post);
    }
    public static function onAfterInsert($model){

        CategoryModel::where('id', $model->cateid)->inc('items')->update();//跟新栏目数量
        if(isset($model->tags) and $model->tags){
            $tagModel = new CmsTags();
            $tagModel->addTags($model->tags,$model->id);
        }
    }

    /**
     * 跟新前
     * @param \think\Model $model
     * @return mixed|void
     */
    public static function onBeforeUpdate($model){

        $post = request()->post();
        $post['publish_time'] = $post['publish_time']?strtotime($post['publish_time']):time();
        $model->appendData($post);
    }

    /**
     * 跟新后
     * @param \think\Model $model
     */
    public static function onAfterUpdate($model){

        if(isset($model->tags) and $model->tags){
            $tagModel = new CmsTags();
            $tagModel->addTags($model->tags,$model->id);
        }
    }

    /**
     * 写入前
     * 事件无论是新增还是更新都会执行
     * @param \think\Model $model
     * @return mixed|void
     */
    public static function onBeforeWrite($model){

    }

    /**
     * 写入后
     * 事件无论是新增还是更新都会执行
     * @param \think\Model $model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function onAfterWrite($model){
        $catemodule = CategoryModel::find($model->cateid);
        $tablename = $catemodule['tablename'];
        if($model->content){
            $addondata = ['id'=>$model->id,'content'=>$model->content];
            $field = Cmsfield::where('moduleid',$catemodule['moduleid'])->column('field','field');
            $addondata  = array_merge($addondata,array_intersect_key($model->getData(), $field));
            if(!Db::name($tablename)->find($addondata['id'])){
                Db::name($tablename)->insert($addondata);
            }else{
                Db::name($tablename)->save($addondata);
            }
        }
    }
    /**
     * 删除后
     */
    public static function onAfterDelete($model){

        CategoryModel::where('id', $model->cateid)->dec('items')->update();//跟新栏目数量

    }

}
