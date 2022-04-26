<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/6
 */

namespace app\common\service;

use think\App;
use think\facade\Log;
use think\facade\Cache;

class PredisService extends AbstractService
{
    public $redisObj = null;//redis实例化时静态变量

    static protected $instance;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    public function initialize($options = [])
    {
        $store = isset($options['store'])?$options['store']:'redis';
        $this->redisObj = Cache::store($store)->handler();
    }

    public function getKeys($key = '*')
    {
        return $this->redisObj->keys($key);
    }

    public function setExpire($key, $time = 0)
    {
        if (!$key) {
            return false;
        }
        switch (true) {
            case ($time == 0):
                return $this->redisObj->expire($key, 0);
                break;
            case ($time > time()):
                return $this->redisObj->expireAt($key, $time);
                break;
            default:
                return $this->redisObj->expire($key, $time);
                break;
        }
    }


    /*------------------------------------start 1.string结构----------------------------------------------------*/
    /**
     * 增，设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value 设置值
     * @param int $timeOut 时间  0表示无过期时间
     * @return true【总是返回true】
     */
    public function set($key, $value, $timeOut = 0)
    {
        $setRes = $this->redisObj->set($key, $value);
        if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        return $setRes;
    }

    /**
     * 查，获取 某键对应的值，不存在返回false
     * @param $key ,键值
     * @return bool|string ，查询成功返回信息，失败返回false
     */
    public function get($key)
    {
        $setRes = $this->redisObj->get($key);//不存在返回false
        if ($setRes === 'false') {
            return false;
        }
        return $setRes;
    }
    /*------------------------------------1.end string结构----------------------------------------------------*/


    /*------------------------------------start 事务快string结构----------------------------------------------------*/

    /**
     * 事务快start
     */
    public function multi(){
        return $this->redisObj->multi();
    }
    /**
     * 事务快 send
     */
    public function exec(){
        return $this->redisObj->exec();
    }

    /**
     * 取消事务
     */
    public  function discard()
    {
        $this->redisObj->discard();
    }


    /*------------------------------------ end 事务快string结构----------------------------------------------------*/

    /**
     * 条件形式设置缓存，如果 key 不存时就设置，存在时设置失败
     *
     * @param string $key 缓存KEY
     * @param string $value 缓存值
     * @return boolean
     */
    public function setnx($redis_key, $value,$timeOut = 0)
    {
        $res = $this->redisObj->set($redis_key, $value,['NX','EX'=>$timeOut]);
        return $res;

    }
        /*------------------------------------2.start list结构----------------------------------------------------*/
    /**
     * 增，构建一个列表(先进后去，类似栈)
     * @param String $key KEY名称
     * @param string $value 值
     * @param $timeOut |num  过期时间
     */
    public function lPush($key, $value, $timeOut = 0)
    {
        if (is_array($value)) {
            $value = json_encode($value, true);
        }
        $re = $this->redisObj->LPUSH($key, $value);
        if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        return $re;
    }

    /**
     * 增，构建一个列表(先进先去，类似队列)
     * @param string $key KEY名称
     * @param string $value 值
     * @param $timeOut |num  过期时间
     */
    public function rPush($key, $value, $timeOut = 0)
    {
        if (is_array($value)) {
            $value = json_encode($value, true);
        }
        $re = $this->redisObj->RPUSH($key, $value);
        if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        return $re;
    }

    /**
     * 查，获取所有列表数据（从头到尾取）
     * @param string $key KEY名称
     * @param int $start 开始
     * @param int $end 结束
     */
    public function lrange($key, $start = 0, $end = -1)
    {
        return $this->redisObj->lrange($key, $start, $end);
    }

    /**移除并返回列表 key 的尾元素。
     * @param $key
     * @return mixed
     */

    public function rPop($key)
    {
        return $this->redisObj->rPop($key);
    }

    public function lPop($key)
    {
        return $this->redisObj->lpop($key);
    }
    /**
     * 从列表中弹出最后一个值，将弹出的元素插入到另外一个列表开头并返回这个元素
     * @param  string $list1  要弹出元素的列表名
     * @param  string $list2  要接收元素的列表名
     * @return string|bool  返回被弹出的元素,如果其中有一个列表不存在则返回false
     */
    public  function lpoppush($list1,$list2)
    {
        if($this->lrange($list1) && $this->lrange($list2)){
            return $this->redisObj->brpoplpush($list1, $list2, 500);
        }
        return false;
    }
    /**对一个列表进行修剪(trim)，就是说，让列表只保留指定区间内的元素
     * @param $key
     * @param int $start
     * @param int $end
     * @param int $count
     * @return mixed
     */
    public function lTrim($key, $start = 1, $end = -1)
    {

        return $this->redisObj->lTrim($key, $start, $end);

    }

    /**返回列表 key 的长度。
     * @param $list_name
     * @return int
     */
    public function lLen($key)
    {
        return $this->redisObj->lLen($key);
    }
    /**
     * 通过索引来设置元素的值
     * @param  string $list  列表名
     * @param  string $value 元素值
     * @param  int $index 索引值
     * @return bool  成功返回true,否则false.当索引参数超出范围，或列表不存在返回false。
     */
    public  function lset($list, $index, $value)
    {
        return $this->redisObj->lset($list, $index, $value);
    }
    /**
     * 通过索引获取列表中的元素
     * @param  string  $list  列表名
     * @param  int  $index  索引位置，从0开始计,默认0表示第一个元素，-1表示最后一个元素索引
     * @return string  返回指定索引位置的元素
     */
    public  function lindex($list, $index=0)
    {
        return$this->redisObj->lindex($list, $index);
    }

    /**移除list 指定value
     * @param $key
     * @param $value
     * @param int $count 0 表示移除全部
     * @return string
     */
    public function lRem($key,$value,$count=0)
    {
        return $this->redisObj->lRem($key,$value, $count);
    }


    /**
     * 用于在指定的列表元素前或者后插入元素。如果元素有重复则选择第一个出现的。当指定元素不存在于列表中时，不执行任何操作
     * @param  string $list   列表名
     * @param  string $element 指定的元素
     * @param  string $value   要插入的元素
     * @param  string $pop     要插入的位置，before前,after后。默认before
     * @return int  返回列表的长度。 如果没有找到指定元素 ，返回 -1 。 如果列表不存在或为空列表，返回 0 。
     */
    public function linsert($list, $element, $value, $pop='before')
    {
        return $this->redisObj->linsert($list, $pop, $element, $value);
    }
    /*------------------------------------2.end list结构----------------------------------------------------*/


    /*------------------------------------3.start set结构----------------------------------------------------*/

    /**
     * 增，构建一个集合(无序集合)
     * @param string $key 集合Y名称
     * @param string|array $value 值
     * @param int $timeOut 时间  0表示无过期时间
     * @return
     */
    public function sAdd($key, $value, $timeOut = 0)
    {
        $re = $this->redisObj->sadd($key, $value);
        if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        return $re;
    }
    /**
     * 移除并返回集合中的一个随机元素
     * @param  string $set 集合名称
     * @return string|bool  返回移除的元素,如果集合为空则返回false
     */
    public  function spop($set)
    {
        return $this->redisObj->spop($set);
    }
    /**
     * 查，取集合对应元素
     * @param string $key 集合名字
     */
    public function sMembers($key)
    {
        $re = $this->redisObj->exists($key);//存在返回1，不存在返回0
        if (!$re) return false;
        return $this->redisObj->smembers($key);
    }
    /**
     * 获取集合中元素的数量。
     * @param  string $set 集合名称
     * @return int  返回集合的成员数量
     */
    public  function scard($set)
    {
        return $this->redisObj->scard($set);
    }
    /**
     * 查，取集合对应交集元素
     * @param string $key 集合名字
     */
    public function sinter($key1,$key2)
    {
        $re = $this->redisObj->exists($key1);
        $re2 = $this->redisObj->exists($key2);
        //存在返回1，不存在返回0
        if (!$re) return false;
        if (!$re2) return false;
        return $this->redisObj->sinter($key1,$key2);
    }

    /**
     * 将给定集合set1和set2之间的交集存储在指定的set集合中。如果指定的集合已存在，则会被覆盖。
     * @param  string $key  指定存储的集合
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return int  返回指定存储集合元素的数量
     */
    public  function sinterstore($key, $key1, $key2)
    {
        return $this->redisObj->sinterstore($key, $key1, $key2);
    }
    /**
     * 返回给定集合之间的差集(集合1对于集合2的差集)。不存在的集合将视为空集
     * @param  string $key1 集合1名称
     * @param  string $key2 集合2名称
     * @return array  返回差集（即筛选存在集合1中但不存在于集合2中的元素）
     */
    public  function sdiff($key1, $key2)
    {
        $re = $this->redisObj->exists($key1);
        $re2 = $this->redisObj->exists($key2);
        //存在返回1，不存在返回0
        if (!$re) return false;
        if (!$re2) return false;
        return $this->redisObj->sdiff($key1, $key2);
    }

    /**
     * 将给定集合set1和set2之间的差集存储在指定的set集合中。如果指定的集合已存在，则会被覆盖。
     * @param  string $key  指定存储的集合
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return int  返回指定存储集合元素的数量
     */
    public  function sdiffstore($key, $key1, $key2)
    {
        return $this->redisObj->sdiffstore($key, $key1, $key2);
    }
    /**
     * 返回集合1和集合2的并集(即两个集合合并后去重的结果)。不存在的集合被视为空集。
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return array  返回并集数组
     */
    public function sunion($key1, $key2)
    {
        $re = $this->redisObj->exists($key1);
        $re2 = $this->redisObj->exists($key2);
        //存在返回1，不存在返回0
        if (!$re) return false;
        if (!$re2) return false;
        return $this->redisObj->sunion($key1, $key2);
    }

    /**
     * 将给定集合set1和set2之间的并集存储在指定的set集合中。如果指定的集合已存在，则会被覆盖。
     * @param  string $set  指定存储的集合
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return int  返回指定存储集合元素的数量
     */
    public function sunionstore($set, $key1, $key2)
    {
        return $this->redisObj->sunionstore($set, $key1, $key2);
    }

    /**
     * 将元素从集合1中移动到集合2中
     * @param  string $set1 集合1
     * @param  string $set2 集合2
     * @param  string $member 要移动的元素成员
     * @return bool  成功返回true,否则false
     */
    public  function smove($set1, $set2, $member)
    {
        return $this->redisObj->smove($set1, $set2, $member);
    }
    /**
     * 判断成员元素是否是集合的成员
     * @param  string $set   集合名称
     * @param  string $member 要判断的元素
     * @return bool 如果成员元素是集合的成员返回true,否则false
     */
    public  function sismember($set, $member)
    {
        return $this->redisObj->sismember($set, $member);
    }





    /*------------------------------------3.end  set结构----------------------------------------------------*/


    /*------------------------------------4.start sort set结构----------------------------------------------------*/
    /*
     * 增，改，构建一个集合(有序集合),支持批量写入,更新
     * @param string $key 集合名称
     * @param array $score_value key为scoll, value为该权的值
     * @return int 插入操作成功返回插入数量【,更新操作返回0】
     */
    public function zadd($key, $score_values, $timeOut = 0)
    {
        if (!is_array($score_values)) return false;
        $a = 0;//存放插入的数量
        foreach ($score_values as $score => $value) {
            $re = $this->redisObj->zadd($key, $score, $value);//当修改时，可以修改，但不返回更新数量
            $re && $a += 1;
            if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        }
        return $a;
    }

    /**
     * 查，有序集合查询，可升序降序,默认从第一条开始，查询一条数据
     * @param $key ,查询的键值
     * @param $min ,从第$min条开始
     * @param $max，查询的条数
     * @param $order ，asc表示升序排序，desc表示降序排序
     * @return array|bool 如果成功，返回查询信息，如果失败返回false
     */
    public function zRange($key, $min = 0, $num = 1, $order = 'desc')
    {
        $re = $this->redisObj->exists($key);//存在返回1，不存在返回0
        if (!$re) return false;//不存在键值
        if ('desc' == strtolower($order)) {
            $re = $this->redisObj->zrevrange($key, $min, $min + $num - 1);
        } else {
            $re = $this->redisObj->zrange($key, $min, $min + $num - 1);
        }
        if (!$re) return false;//查询的范围值为空
        return $re;
    }
    /**
     * 计算有序集合中指定分数区间的成员数量
     * @param  string $key 集合名称
     * @param  int|float $min 最小分数值
     * @param  int|float $max 最大分数值
     * @return int  返回指定区间的成员数量
     */
    public  function zcount($key, $min, $max)
    {
        return $this->redisObj->zcount($key, $min, $max);
    }
    /**
     * 返回有序集合中成员数量
     * @param  string $key 集合名称
     * @return int  返回成员的数量
     */
    public  function zcard($key)
    {
        return $this->redisObj->zcard($key);
    }

    /**
     * 返回集合key中，成员member的排名
     * @param $key，键值
     * @param $member，scroll值
     * @param $type ,是顺序查找还是逆序
     * @return bool,键值不存在返回false，存在返回其排名下标
     */
    public function zrank($key, $member, $type = 'desc')
    {
        $type = strtolower(trim($type));
        if ($type == 'desc') {
            $re = $this->redisObj->zrevrank($key, $member);//其中有序集成员按score值递减(从大到小)顺序排列，返回其排位
        } else {
            $re = $this->redisObj->zrank($key, $member);//其中有序集成员按score值递增(从小到大)顺序排列，返回其排位
        }
        if (!is_numeric($re)) return false;//不存在键值
        return $re;
    }

    /**
     * 返回名称为key的zset中score >= star且score <= end的所有元素
     * @param $key
     * @param $member
     * @param $star，
     * @param $end ,
     * @return array
     */
    public function zrangbyscore($key, $star, $end)
    {
        return $this->redisObj->ZRANGEBYSCORE($key, $star, $end);
    }
    /**
     * 计算set1和set2有序集的交集，并将该交集(结果集)储存到新集合set中。
     * @param  string $key  指定存储的集合名称
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return int  返回保存到目标结果集的的成员数量
     */
    public  function zinterstore($key, $key1, $key2)
    {
        return $this->redisObj->zinterstore($key, 2, $key1, $key2);
    }
    /**
     * 计算set1和set2有序集的并集，并将该并集(结果集)储存到新集合set中。
     * @param  string $key  指定存储的集合名称
     * @param  string $key1 集合1
     * @param  string $key2 集合2
     * @return int  返回保存到目标结果集的的成员数量
     */
    public function zunionstore($key, $key1, $key2)
    {
        return $this->redisObj->zunionstore($key, 2, $key1, $key2);
    }

    /**
     * 移除有序集中的一个或多个成员，不存在的成员将被忽略。
     * @param  string $set     有序集合名称
     * @param  string|array $members 要移除的成员，如果要移除多个请传入多个成员的数组
     * @return int   返回被移除的成员数量，不存在的成员将被忽略
     */
    public function zrem($set, $members)
    {
        $num = 0;
        if (is_array($members)) {
            foreach ($members as $value) {
                $num += $this->redisObj->zrem($set, $value);
            }
        } else {
            $num += $this->redisObj->zrem($set, $members);
        }
        return $num;
    }
    /**
     * 移除有序集中，指定排名(rank)区间内的所有成员（这个排名数字越大排名越高，最低排名0开始）
     * @param  string $set   集合名称
     * @param  int $min 最小排名，从0开始计
     * @param  int $max 最大排名
     * @return int  返回被移除的成员数量，如移除排名为倒数5名的成员：$redis::zremrank($set,0,4);
     */
    public  function zrembyrank($set, $min, $max)
    {
        return $this->redisObj->zremrangebyrank($set, $min, $max);
    }


    /**
     * 返回有序集中，成员的分数值。
     * @param  string $key    集合名称
     * @param  string $member 成员
     * @return float|bool   返回分数值(浮点型)，如果成员不存在返回false
     */
    function zScore($key, $member)
    {
        return $this->redisObj->zscore($key, $member);
    }
    /*------------------------------------4.end sort set结构----------------------------------------------------*/


    /*------------------------------------5.hash结构----------------------------------------------------*/

    public function hSetJson($redis_key, $field, $data, $timeOut = 0)
    {
        $redis_info = json_encode($data);                           //field的数据value，以json的形式存储
        $re = $this->redisObj->hSet($redis_key, $field, $redis_info);//存入缓存
        if ($timeOut > 0) $this->redisObj->expire($redis_key, $timeOut);//设置过期时间
        return $re;
    }

    public function hGetJson($redis_key, $field)
    {
        $info = $this->redisObj->hget($redis_key, $field);
        if ($info) {
            $info = json_decode($info, true);
        } else {
            $info = false;
        }
        return $info;
    }

    public function hSet($redis_key, $name, $data, $timeOut = 0)
    {
        $re = $this->redisObj->hset($redis_key, $name, $data);
        if ($timeOut > 0) $this->redisObj->expire($redis_key, $timeOut);
        return $re;
    }

    public function hSetNx($redis_key, $name, $data, $timeOut = 0)
    {
        $re = $this->redisObj->hsetNx($redis_key, $name, $data);
        if ($timeOut > 0) $this->redisObj->expire($redis_key, $timeOut);
        return $re;
    }


    /**
     * 增，普通逻辑的插入hash数据类型的值
     * @param $key ,键名
     * @param $data |array 一维数组，要存储的数据
     * @param $timeOut |num  过期时间
     * @return $number 返回OK【更新和插入操作都返回ok】
     */
    public function hMset($key, $data, $timeOut = 0)
    {
        $re = $this->redisObj->hmset($key, $data);
        if ($timeOut > 0) $this->redisObj->expire($key, $timeOut);
        return $re;
    }

    /**
     * 查，普通的获取值
     * @param $key ,表示该hash的下标值
     * @return array 。成功返回查询的数组信息，不存在信息返回false
     */
    public function hVals($key)
    {
        $re = $this->redisObj->exists($key);//存在返回1，不存在返回0
        if (!$re) return false;
        $vals = $this->redisObj->hvals($key);
        $keys = $this->redisObj->hkeys($key);
        $re = array_combine($keys, $vals);
        foreach ($re as $k => $v) {
            if (!is_null(json_decode($v))) {
                $re[$k] = json_decode($v, true);//true表示把json返回成数组
            }
        }
        return $re;
    }

    /**
     *
     * @param $key
     * @param $filed
     * @return bool|string
     */
    public function hGet($key, $filed = [])
    {
        if (empty($filed)) {
            $re = $this->redisObj->hgetAll($key);
        } elseif (is_string($filed)) {
            $re = $this->redisObj->hget($key, $filed);
        } elseif (is_array($filed)) {
            $re = $this->redisObj->hMget($key, $filed);
        }
        if (!$re) {
            return false;
        }
        return $re;
    }

    public function hDel($redis_key, $name)
    {
        $re = $this->redisObj->hdel($redis_key, $name);
        return $re;
    }

    public function hLan($redis_key)
    {
        $re = $this->redisObj->hLen($redis_key);
        return $re;
    }

    public function hIncre($redis_key, $filed, $value = 1)
    {
        return $this->redisObj->hIncrBy($redis_key, $filed, $value);
    }

    /**
     * 检验某个键值是否存在
     * @param $keys keys
     * @param string $type 类型，默认为常规
     * @param string $field 若为hash类型，输入$field
     * @return bool
     */
    public function hExists($keys, $field = '')
    {
        $re = $this->redisObj->hexists($keys, $field);//有返回1，无返回0
        return $re;
    }



    /*------------------------------------end hash结构----------------------------------------------------*/


    /*------------------------------------其他结构----------------------------------------------------*/
    /**
     * 设置自增,自减功能
     * @param $key ，要改变的键值
     * @param int $num ，改变的幅度，默认为1
     * @param string $member ，类型是zset或hash，需要在输入member或filed字段
     * @param string $type，类型，default为普通增减 ,还有:zset,hash
     * @return bool|int 成功返回自增后的scroll整数，失败返回false
     */
    public function incre($key, $num = 1, $member = '', $type = '')
    {
        $num = intval($num);
        switch (strtolower(trim($type))) {
            case "zset":
                $re = $this->redisObj->zIncrBy($key, $num, $member);//增长权值
                break;
            case "hash":
                $re = $this->redisObj->hincrby($key, $member, $num);//增长hashmap里的值
                break;
            default:
                if ($num > 0) {
                    $re = $this->redisObj->incrby($key, $num);//默认增长
                } else {
                    $re = $this->redisObj->decrBy($key, -$num);//默认增长
                }
                break;
        }
        if ($re) return $re;
        return false;
    }


    /**
     * 清除缓存数据库
     * @param int $type 默认为0，清除当前数据库；1表示清除所有缓存
     */
    public function flush($type = 0)
    {
        if ($type) {
            $this->redisObj->flushAll();//清除所有数据库
        } else {
            $this->redisObj->flushdb();//清除当前数据库
        }
    }

    /**
     * 检验某个键值是否存在
     * @param $keys keys
     * @param string $type 类型，默认为常规
     * @param string $field 若为hash类型，输入$field
     * @return bool
     */
    public function exists($keys, $type = '', $field = '')
    {
        switch (strtolower(trim($type))) {
            case 'hash':
                $re = $this->redisObj->hexists($keys, $field);//有返回1，无返回0
                break;
            default:
                $re = $this->redisObj->exists($keys);
                break;
        }
        return $re;
    }
    /**
     * 返回集合中的一个或多个随机元素
     * @param  string $set   集合名称
     * @param  int $count 要返回的元素个数，0表示返回单个元素，大于等于集合基数则返回整个元素数组。默认0
     * @return string|array   返回随机元素，如果是返回多个则为数组返回
     */
    public function srand($set, $count=0)
    {
        return ((int)$count==0) ?$this->redisObj->srandmember($set) : $this->redisObj->srandmember($set, $count);
    }
    /**
     * 删除缓存
     * @param string|array $key 键值
     * @param $type 类型 默认为常规，还有hash,zset
     * @param string $field ,hash=>表示$field值，set=>表示value,zset=>表示value值，list类型特殊暂时不加
     * @return int |  返回删除的个数
     */
    public function delete($key, $type = "default", $field = '')
    {
        switch (strtolower(trim($type))) {
            case 'hash':
                $re = $this->redisObj->hDel($key, $field);//返回删除个数
                break;
            case 'set':
                $re = $this->redisObj->sRem($key, $field);//返回删除个数
                break;
            case 'zset':
                $re = $this->redisObj->zDelete($key, $field);//返回删除个数
                break;
            default:
                $re = $this->redisObj->del($key);//返回删除个数
                break;
        }
        return $re;
    }

    //日志记录
    public function writelog($content, $position = 'member')
    {
        $max_size = 10000000;   //声明日志的最大尺寸10000K

        $log_dir = './log';//日志存放根目录

        if (!file_exists($log_dir)) mkdir($log_dir, 0777);//如果不存在该文件夹，创建

        if ($position == 'member') {
            $filename = "{$log_dir}/Member_redis_log.txt";  //日志名称
        } else {
            $filename = "{$log_dir}/Wap_redis_log.txt";  //日志名称
        }

        //如果文件存在并且大于了规定的最大尺寸就删除了
        if (file_exists($filename) && (abs(filesize($filename)) > $max_size)) {
            unlink($filename);
        }

        //写入日志，内容前加上时间， 后面加上换行， 以追加的方式写入
        file_put_contents($filename, date('Y-m-d_H:i:s') . " " . $content . "\n", FILE_APPEND);
    }


    public function flushDB()
    {
        $this->redisObj->flushDB();
    }

    public function __destruct()
    {
        $this->redisObj->close();
    }

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        call_user_func_array([$this->redisObj, $method], $args);
    }


}