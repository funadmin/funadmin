<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */
namespace app\common\model;

class  CmsTags extends Common{

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    //添加tags
    public  function addTags($data,$id){
        if(!$data){
            return '';
        }
        if(strpos($data,',')===false){
            $data = [$data];
        }else{

            $data = array_filter(explode(',',$data));
        }
        foreach ($data as $k=>$v) {
            $tag =$this->where('name',$v)->find();
            if($tag){
                $tag->inc('nums')->update();
                if(strpos($tag->article_ids,$id)===false){
                    $tag->article_ids =  $tag->article_ids.','.$id;
                    $tag->save();
                }
            }else{
                $this->create(['name'=>$v,'article_id'=>$id]);
            }
        }
        return true;

    }
}