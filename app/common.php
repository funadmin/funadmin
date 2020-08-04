<?php
// +----------------------------------------------------------------------
// | 应用公共文件
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yuege
// +----------------------------------------------------------------------

use app\common\model\Region;
use speed\helper\FormHelper;
use think\App;
use think\facade\Route;

error_reporting(0);

if (!function_exists('region')) {

    function region($id)
    {
        $model = new Region();
        return $model->where('id',$id)->cache('region_'.$id)->find();
    }
}

if (!function_exists('syscfg')) {
    /**
     * @param $group
     * @param null $code
     * @return array|mixed|object|App
     */
    function syscfg($group,$code= null)
    {
        $where = ['group' => $group];
        $value = empty($code) ? cache("syscfg_{$group}") : cache("syscfg_{$group}_{$code}");
        if (!empty($value)) {
            return $value;
        }
        if (!empty($code)) {
            $where['code'] = $code;
            $value = \app\common\model\Config::where($where)->value('value');
            cache("syscfg_{$group}_{$code}", $value, 3600);
        } else {
            $value = \app\common\model\Config::where($where)->column('value', 'code');
            cache("syscfg_{$group}", $value, 3600);
        }
        return $value;

    }
}

//重写url 助手函数
if (!function_exists('url')) {

    function url($url='', array $vars = [], $suffix = true, $domain = false)
    {
        $url = (string) Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
        $pos = strpos($url,'/backend');
        if($pos!==false){
            $url = substr_replace($url,'',$pos,strlen('/backend'));
        }
        return $url;
    }
}

//重写url 助手函数
if (!function_exists('__url')) {

    function __url($url='', array $vars = [], $suffix = true, $domain = false)
    {
        $url = (string) Route::buildUrl($url, $vars)->suffix($suffix)->domain($domain);
        $pos = strpos($url,'/backend');
        if($pos!==false){
            $url = substr_replace($url,'',$pos,strlen('/backend'));
        }
        return $url;
    }
}


if (!function_exists('form_build_input')) {
    /**
     * @param $type
     * @param $name
     * @return string
     */
    function form_build_input($type, $name)
    {
        return FormHelper::input($type,$name,$options = []);
    }
}

if (!function_exists('form_build_select')) {
    /**
     * @param null $name
     * @param array $options
     * @return string
     */
    function form_build_select($name = null,$value=[], $options = [])
    {
        return FormHelper::select($name, $value, $options);
    }
}

if (!function_exists('form_build_date')) {
    /**
     * @param array $options
     * @return string
     */

    function form_build_date($name=null,$options = [])
    {
        return FormHelper::date($name, $options);
    }
}

if (!function_exists('form_build_submitbtn')) {
    /**
     * @param bool $reset
     * @param array $options
     * @return string
     */
    function form_build_submitbtn($reset = true, $options=[])
    {
        return FormHelper::submitbtn($reset, $options);
    }
}
if (!function_exists('form_build_upload')) {
    /**
     * @param $name
     * @param null $formdata
     * @return string
     */
    function form_build_upload($name,$formdata=null)
    {
        return FormHelper::upload($name,$formdata);
    }
}
if (!function_exists('form_build_ueditor')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_build_ueditor($id,$name)
    {
        return FormHelper::ueditor($id,$name);
    }
}
if (!function_exists('form_build_wangeditor')) {
    /**
     * @param $id
     * @param $name
     * @return string
     */
    function form_build_wangeditor($id,$name)
    {
        return FormHelper::wangeditor($id,$name);
    }
}
/** 百度编辑器*/
if (!function_exists('build_ueditor')) {
function build_ueditor($params = array())
{
    $name = isset($params['name']) ? $params['name'] : null;
    $theme = isset($params['theme']) ? $params['theme'] : 'normal';
    $content = isset($params['content']) ? $params['content'] : null;
    //http://fex.baidu.com/ueditor/#start-toolbar
    /* 指定使用哪种主题 */
    $themes = array(
        'normal' => "[   
           'fullscreen', 'source', '|', 'undo', 'redo', '|',
            'bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'superscript', 'subscript', 'removeformat', 'formatmatch', 'autotypeset', 'blockquote', 'pasteplain', '|', 'forecolor', 'backcolor', 'insertorderedlist', 'insertunorderedlist', 'selectall', 'cleardoc', '|',
            'rowspacingtop', 'rowspacingbottom', 'lineheight', '|',
            'customstyle', 'paragraph', 'fontfamily', 'fontsize', '|',
            'directionalityltr', 'directionalityrtl', 'indent', '|',
            'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', '|', 'touppercase', 'tolowercase', '|',
            'link', 'unlink', 'anchor', '|', 'imagenone', 'imageleft', 'imageright', 'imagecenter', '|',
            'simpleupload', 'insertimage', 'emotion', 'scrawl', 'insertvideo', 'music', 'attachment', 'map', 'gmap', 'insertframe', 'insertcode', 'webapp', 'pagebreak', 'template', 'background', '|',
            'horizontal', 'date', 'time', 'spechars', 'snapscreen', 'wordimage', '|',
            'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol', 'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', 'charts', '|',
            'print', 'preview', 'searchreplace', 'drafts', 'help'
       ]", 'simple' => " ['fullscreen', 'source', 'undo', 'redo', 'bold']",
    );
    switch ($theme) {
        case 'simple':
            $theme_config = $themes['simple'];
            break;
        default:
            $theme_config = $themes['normal'];
            break;
    }
    /* 配置界面语言 */
    switch (config('think_var')) {
        case 'en-us':
            $lang =  '__PLUGINS__/ueditor/lang/en/en.js';
            break;
        default:
            $lang = '__PLUGINS__/ueditor/lang/zh-cn/zh-cn.js';
            break;
    }
    $include_js = '<script type="text/javascript" charset="utf-8" src="__PLUGINS__/ueditor/ueditor.config.js"></script> <script type="text/javascript" charset="utf-8" src="__PLUGINS__/ueditor/ueditor.all.min.js""> </script><script type="text/javascript" charset="utf-8" src="' . $lang . '"></script>';
    $content = json_encode($content);
    return <<<EOT
$include_js
<script type="text/javascript">
let ue = UE.getEditor('{$name}',{
    toolbars:[{$theme_config}],
        })
    if($content){
ue.ready(function() {
       this.setContent($content)	
})
   }
      
</script>
EOT;

}
}

/**
 * 打印
 */
if (!function_exists('p')) {
    function p($var, $die = 0) {
        print_r($var);
        $die && die();
    }
}
/**
 * 手机
 */
if (!function_exists('isMobile')) {
    function isMobile()
    {
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        if (isset ($_SERVER['HTTP_VIA'])) {
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }
}

//是否https;

if (!function_exists('isHttps')) {
    function isHttps() {
        if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
            return true;
        } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return false;
    }


}

/**
 * 获取http类型
 */
if(!function_exists('getHttpType')) {
    /**
     * http 类型
     * @return string
     */
    function getHttpType()
    {
        return $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

    }
}

if (!function_exists('timeAgo')) {
    /**
     * 从前
     * @param $posttime
     * @return string
     */
    function timeAgo($posttime)
    {
        //当前时间的时间戳
        $nowtimes = strtotime(date('Y-m-d H:i:s'), time());
        //之前时间参数的时间戳
        $posttimes = strtotime($posttime);
        //相差时间戳
        $counttime = $nowtimes - $posttimes;

        //进行时间转换
        if ($counttime <= 10) {

            return '刚刚';

        } else if ($counttime > 10 && $counttime <= 30) {

            return '刚才';

        } else if ($counttime > 30 && $counttime <= 60) {

            return '刚一会';

        } else if ($counttime > 60 && $counttime <= 120) {

            return '1分钟前';

        } else if ($counttime > 120 && $counttime <= 180) {

            return '2分钟前';

        } else if ($counttime > 180 && $counttime < 3600) {

            return intval(($counttime / 60)) . '分钟前';

        } else if ($counttime >= 3600 && $counttime < 3600 * 24) {

            return intval(($counttime / 3600)) . '小时前';

        } else if ($counttime >= 3600 * 24 && $counttime < 3600 * 24 * 2) {

            return '昨天';

        } else if ($counttime >= 3600 * 24 * 2 && $counttime < 3600 * 24 * 3) {

            return '前天';

        } else if ($counttime >= 3600 * 24 * 3 && $counttime <= 3600 * 24 * 20) {

            return intval(($counttime / (3600 * 24))) . '天前';

        } else {

            return $posttime;

        }
    }
}