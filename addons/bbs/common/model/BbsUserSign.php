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
use app\common\model\Common;

class BbsUserSign extends Common{

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


    /**
     * 积分规则，返回连续签到的天数对应的积分
     * @param int $sign_count 当天应该得的分数
     * @return int 积分
     */
    public function _todayScores($sign_count)
    {
        $score = 0;
        $scores = BbsUserSignRule::where("days <= $sign_count")->order('days desc')->limit(1)->value('score');
        if ($scores) {
            $score = $scores;
        }
        return $score;
    }
    /**
     * 返回每次签到要插入的数据
     * @param int $uid 用户id
     * @return array(
     *  '$sign_count'   =>  '连续天数',
     *  '$sign_total'   =>  '累计天数',
     *  'is_sign'  =>  '昨天是否签到,用1表示已经签到',
     *  'sign_last'   =>  '签到时间',
     * );
     */
    public function _signInsertData($uid)
    {
        // 昨天的连续签到天数
        $sign =BbsUserSign::where('uid',$uid)->whereDay("sign_last",'yesterday')->find();
        if ($sign) {
            $sign_count =$sign->sign_count+1;
            $sign_total =$sign->sign_total+1;
            $is_sign = 1;
            $sign_time = $sign->sign_time .','.time();
        } else {
            $sign = BbsUserSign::where('uid',$uid)->find();
            if($sign){
                $sign_time = $sign->sign_time .','.time();
                $sign_total =$sign->sign_total+1;
            }else{
                $sign_total =1;
                $sign_time = time();

            }
            $sign_count = 1;
            $is_sign = 1;

        }
        $sign_last = time();
        return json([
            'sign_count' => $sign_count,
            'sign_total' => $sign_total,
            'is_sign' => $is_sign,
            'sign_last' => $sign_last,
            'sign_time' => $sign_time,
        ]);
    }

    /**
     * 用户当天签到的数据
     * @return array 签到信息 is_sign,stime 等
     */
    public function _signTodayData()
    {

        $res = BbsUserSign::where('uid',session('user.id'))->whereDay('sign_last')->find();
        $score = 0;
        if ($res) {
            $is_sign = 1;
            //昨天已签到
            $sign_count = $res['sign_count'];
            $score = $this->_todayScores($res['sign_count']);
            $will_getscore = $this->_todayScores($res['sign_count'] + 1);
        } else {
            //今天没有签，昨天是否签到
            $is_sign = 0;
            $yestoday = $this->_signInsertData(session('user.id'))->getData();
            if ($yestoday['is_sign']) {
                //今天连续天数
                $sign_count = $yestoday['sign_count'] - 1;
                $will_getscore = $this->_todayScores($yestoday['sign_count']);
            } else {
                //今天第一天
                $sign_count = 0;
                $will_getscore = $this->_todayScores(1);

            }

            //已连续签到 可获取
            $score = $this->_todayScores($sign_count);
        }
        $data = [
            'is_sign' => $is_sign,
            'sign_count' => $sign_count,
            'score' => $score,
            'will_getscore' => $will_getscore,
        ];

        return json($data);
    }

}