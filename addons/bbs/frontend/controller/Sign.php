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
 * Date: 2019/11/27
 */
namespace app\bbs\controller;

use app\common\model\User;
use app\common\model\BbsUserSign;
use app\common\model\BbsUserSignRule;
use think\facade\Session;
use think\facade\Db;
use think\facade\View;
use think\Collection;
use think\Response;
use app\common\model\BbsMessage;

class Sign extends Comm
{

    protected $uid;

    public function initialize()
    {
        parent::initialize();
        $this->uid = session("user.id");
		
    }

    /*************************************************签到******************************************/
    //首页签到活跃版
    public function signlists()
    {
        //总榜
        $totallist = BbsUserSign::alias('s')->join('user u', 's.uid=u.id')->field('s.uid,s.id,max(s.sign_count) as sign_count,u.username,u.avatar')->group('s.uid')->order('sign_count desc')->limit(20)->select();
        //今日最快
        $fastlist = BbsUserSign::alias('s')->join('user u', 's.uid=u.id')->field('s.*,u.username ,u.avatar ')->whereDay('sign_last')->order('s.id asc')->limit(20)->select();
        //最新
        $newlist = BbsUserSign::alias('s')->join('user u', 's.uid=u.id')->whereDay('s.sign_last')->field('s.*,u.username ,u.avatar')->order('id desc')->limit(20)->select();

        if($fastlist){
            $fastlist = $fastlist->toArray();
        }
        if($newlist){
            $newlist = $newlist->toArray();
        }
        if($totallist){
            $totallist = $totallist->toArray();
        }
        $view = [
            'code'=>1,
            'msg'=>'ok',
            'data'=>[
                $newlist,
                $fastlist,
                $totallist,
            ]

        ];
        return json($view);
    }

    /**
     * 签到；获取积分
     */
    public function sign()
    {
        $this->LoginErr();
        $uid = session('user.id');
        $user = session('user');
        $userSing = new BbsUserSign();
        $_signTodayData = $userSing->_signTodayData()->getData();
        if ($_signTodayData['is_sign'] == 1) { //数组中是返回的是一个对象，不能直接用[]来显示，正确的输出方法是：$pic[0]->title问题解决！
            $this->error('亲，你今天已经签过到了');

        } else {
            $data = $userSing->_signInsertData($uid)->getData();
//                $sign_count = $data['sign_count'];
            // 无今天数据
            $data['uid'] = $uid;
            $data['sign_last'] = time();
            $UserSign = BbsUserSign::where('uid',$uid)->find();
            if($UserSign){
                $UserSign->save($data);
            }
            else{
                $UserSign = BbsUserSign::create($data);
            }
            if ($UserSign) {
                $score = $_signTodayData['will_getscore'];
                $date=date('Ymd');
                $msg='亲爱的lemo用户,您获得了'.$score.'L币！';
                $newYearDate=['20200125','20200126','20200127','20200128','20200129','20200130','20200201'];
                //签到奖励
                if(in_array($date,$newYearDate)){
                    $randnum=rand(1,99);
                    $msg='亲爱的lemo用户,新年好！您额外获得随机奖励'.$randnum.'L币！';
                }
                if ($score > 0) {
                    // 为该用户添加积分
                    $user->scores = $user->scores +$score;
                    $user->save();

                }
                $mess = new BbsMessage();//消息通知
                $data = [
                    'type'=>0,//签到系统消息
                    'content'=>$msg,
                    'send_id'=>0,
                    'receive_id' =>session('user.id'),
                    'score' =>$score,
                ];
                $mess->add($data);
                $this->success('签到成功');
            } else {
                $this->error('签到失败，请重试！');
            }
        }

    }

    /**
     * 用户当天签到的数据
     * @return array 签到信息 is_sign,sign_last 等到最后时间
     */
    public function signData()
    {
        $res = BbsUserSign::where('uid',session('user.id'))->whereDay('sign_last')->find();
        $score = 0;
        $userSign = new BbsUserSign();
        if ($res) {
            $is_sign = 1;
            //昨天已签到
            $sign_count = $res['sign_count'];
            $score = $userSign->_todayScores($res['sign_count']);
            $will_getscore = $userSign->_todayScores($res['sign_count'] + 1);
        } else {
            //没有签，看昨天
            $is_sign = 0;
            $yestoday = $userSign->_signInsertData(session('user_id'))->getData();
            if ($yestoday['is_sign']) {
                //今天连续天数
                $sign_count = $yestoday['sign_count'] - 1;
                $will_getscore = $userSign->_todayScores($yestoday['sign_count']);
            } else {
                //今天第一天
                $sign_count = 0;
                $will_getscore = $userSign->_todayScores(1);

            }
            //已连续签到 可获取
            $score = $userSign->_todayScores($sign_count);
        }
        $data = [
            'is_sign' => $is_sign,
            'sign_count' => $sign_count,
            'score' => $score,
            'will_getscore' => $will_getscore,
        ];

        $this->success('请求成功','',$data);
    }



    /*
     * 获取签到规则
     */
    public function signrule()
    {
        $rules = BbsUserSignRule::order('days asc')->select();
        $this->success('请求成功','',$rules);
    }






}
