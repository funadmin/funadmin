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
 * Date: 2019/9/22
 */
namespace fun\helper;
use Ramsey\Uuid\Uuid;
class StringHelper
{

    /*
        参数过滤防止攻击SQL
        */
    public static function filterWords($str)
    {
        $farr = array(
            "/<(\\/?)(script|i?frame|style|html|body|title|link|meta|object|\\?|\\%)([^>]*?)>/isU",
            "/(<[^>]*)on[a-zA-Z]+\s*=([^>]*>)/isU",
            "/select\b|insert\b|update\b|delete\b|drop\b|;|\"|\'|\/\*|\*|\.\.\/|\.\/|union|into|load_file|outfile|dump/is"
        );
        $str = preg_replace($farr, '', $str);
        $str = strip_tags($str);
        return $str;
    }

    /*
    参数校验
    */
    public static function filterInput($str)
    {
        if (!$str) {
            throw new \Exception('参数错误');
        }
        return self::filterWords($str);
    }
    /**
     * 生成Uuid
     *
     * @param string $type 类型 默认时间 time/md5/random/sha1/uniqid 其中uniqid不需要特别开启php函数
     * @param string $name 加密名
     * @return string
     * @throws \Exception
     */
    public static function uuid($type = 'time', $name = 'php.net')
    {
        switch ($type) {
            // 生成版本1（基于时间的）UUID对象
            case  'time' :
                $uuid = Uuid::uuid1();

                break;
            // 生成第三个版本（基于名称的和散列的MD5）UUID对象
            case  'md5' :
                $uuid = Uuid::uuid3(Uuid::NAMESPACE_DNS, $name);

                break;
            // 生成版本4（随机）UUID对象
            case  'random' :
                $uuid = Uuid::uuid4();

                break;
            // 产生一个版本5（基于名称和散列的SHA1）UUID对象
            case  'sha1' :
                $uuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, $name);

                break;
            // php自带的唯一id
            case  'uniqid' :
                return md5(uniqid(md5(microtime(true) . self::randomNum(8)), true));

                break;
        }
        return $uuid->toString();
    }
    /**
     * 字符串截取
     */
    public static function msubstr($str, $start, $length, $charset="utf-8", $suffix=true) {
        // 过滤html代码
        $str=strip_tags($str);
        if(function_exists("mb_substr")){
            $slice = mb_substr($str, $start, $length, $charset);
            $strlen = mb_strlen($str,$charset);
        }elseif(function_exists('iconv_substr')){
            $slice = iconv_substr($str,$start,$length,$charset);
            $strlen = iconv_strlen($str,$charset);
        }else{
            $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("",array_slice($match[0], $start, $length));
            $strlen = count($match[0]);
        }
        if($suffix && $strlen>$length)$slice.='...';
        return $slice;
    }

    /**
     * @param $content
     * @return string|string
     * 字符串替换
     */
    public static function htmlReplace($content){
        $content=str_replace("&lt;", "<", $content);
        $content=str_replace("&gt;", ">", $content);
        $content=str_replace("&namp;", "&", $content);
        $content=str_replace("&quot;", "\"", $content);
        return $content;
    }

    /**
     * 随机字符
     * @param int $length 长度
     * @param string $type 类型
     * @param int $convert 转换大小写 1大写 0小写
     * @return string
     */
    public static function  randomNum($length=10, $type='all', $convert=0)
    {
        $config = [
            'number'=>'1234567890',
            'letter'=>'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'string'=>'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789',
            'all'=>'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'
        ];
        if(!isset($config[$type])) $type = 'letter';
        $string = $config[$type];
        $code = '';
        $strlen = strlen($string) -1;
        for($i = 0; $i < $length; $i++){
            $code .= $string[mt_rand(0, $strlen)];
        }
        if(!empty($convert)){
            $code = ($convert > 0)? strtoupper($code) : strtolower($code);
        }
        return $code;
    }

    /**
     * PHP格式化字节大小
     * @param  number $size      字节数
     * @param  string $delimiter 数字和单位分隔符
     * @return string            格式化后的带单位的大小
     */
    public static function formatBytes($size, $delimiter = '') {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
        return round($size, 2) . $delimiter . $units[$i];
    }

    /**
     * 将$name中的下划线转换成类名   全如  aa_aa   变成 AaAa
     * @access public
     * @return string
     */
    public static function formatClass($name){
        $temp_array = array();
        $arr=explode("_", $name);
        foreach ($arr as $key => $value) {
            $temp_array[]=ucfirst($value);
        }
        return implode('',$temp_array);
    }
    /**
     * @access public
     * @return string
     */
    public static function getOrderSn($str = ""){
        return $str.date("YmdHis",time()).sprintf('%06s', rand(0,999999));
    }
    /**
     * 过滤字符串中的一些内容
     * @access public
     * @return string
     */
    public static function paramFilter($value=""){

        $value=preg_replace("/<script[\s\S]*?<\/script>/im", "", $value);

        $value=preg_replace("/<script>|<\/script>/im", "", $value);

        return $value;

    }

    /**
     * 移除微信昵称中的emoji字符
     * @param type $nickname
     * @return type
     */
    public static function removeEmoji($nickname) {
        $clean_text = "";
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clean_text = preg_replace($regexEmoticons, '', $nickname);
        // Match Miscellaneous Symbols and Pictographs
        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clean_text = preg_replace($regexSymbols, '', $clean_text);
        // Match Transport And Map Symbols
        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clean_text = preg_replace($regexTransport, '', $clean_text);
        // Match Miscellaneous Symbols
        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $clean_text = preg_replace($regexMisc, '', $clean_text);
        // Match Dingbats
        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $clean_text = preg_replace($regexDingbats, '', $clean_text);
        return trim($clean_text);
    }



    /**
     * 生成卡号
     * @param $prifix
     * @param int $num
     * @param int $length
     * @return array 生成卡密
     */
    public static  function getCardId($num=1,$length=10,$prifix='F_')
    {

        //输出数组
        $card = array();
        //填补字符串
        $pad = '';

        //日期
        $temp = time();
        $Y = date('Y', $temp);
        $M = date('m', $temp);
        $D = date('d', $temp);
        $TD = date('YmdHis', $temp);

        //长度
        $LY = strlen((string)$Y);
        $LM = strlen((string)$M);
        $LD = strlen((string)$D);
        $LTD = strlen((string)$TD);

        //流水号长度
        $W = 5;

        //根据长度生成填补字串
        if ($length <= 12) {
            $pad = $prifix . self::randomNum($length - $W);
        } else if ($length > 12 && $length <= 16) {
            $pad = $prifix . (string)$Y . self::randomNum($length - ($LY + $W));
        } else if ($length > 16 && $length <= 20) {
            $pad = $prifix . (string)$Y . (string)$M . self::randomNum($length - ($LY + $LM + $W));
        } else {
            $pad = $prifix . (string)$TD . self::randomNum($length - ($LTD + $W));
        }
        //生成X位流水号
        for ($i = 0; $i < $num; $i++) {
            $STR = $pad . str_pad((string)($i + 1), $W, '0', STR_PAD_LEFT);
            $card[$i] = $STR;
        }

        return $card;

    }

    /**
     * 生成密码
     * @param int $num
     * @return array
     */
    public static function getCardPwd($num=1)
    {

        $pwd = array();
        for ($i = 0; $i < $num; $i++) {
            //生成基本随机数
            $charid = substr(MD5(uniqid(mt_rand(), true)), 8, 16) .self::randomNum(4, '2');
            $pwd[$i] = strtoupper($charid);
        }
        return $pwd;
    }

    /**
     * 获取加密token
     * @param $data
     * @return string
     */
    public static function getToken($data){
        $arr = '';
        if(is_array($data)){
            foreach ($data as $val) {
                $arr.=$val;
            }
        }
        if(is_object($data)){
            $data = get_object_vars($data);
            foreach ($data as $val) {
                $arr.=$val;
            }
        }
        if(is_string($data)){
            $arr = $data;
        }
        $token = md5($arr);
        return $token;
    }

}
