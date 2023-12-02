<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/26
 */
namespace fun\helper;
use fun\helper\HttpHelper;
class ExpressHelper{

    /**
     * 查询快递
     * @param $postcom  快递公司编码
     * @param $getNu  快递单号
     * @return array  物流跟踪信息数组
     */
    function queryExpress($postcom , $getNu) {
        $url = "https://m.kuaidi100.com/query";
        $params = [
            'type'=>$postcom,
            'postid'=>$getNu,
            'id'=>1,
            'valicode'=>'',
            'temp'=>'0.49738534969422676',
        ];
        $resp = HttpHelper::get($url,$params);
        return $resp;
    }
}