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
 * Date: 2019/9/4
 */

namespace addons\wechat\auth\controller;

use app\common\controller\AddonsFrontend;
use addons\wechat\backend\service\WechatService;
use addons\wechat\backend\model\WechatMaterialInfo;
use addons\wechat\backend\model\WechatMaterial;
use addons\wechat\backend\model\WechatMsgHistory;
use addons\wechat\backend\model\WechatReply;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use think\App;

class WechatAuth extends AddonsFrontend
{
    protected $where = [];
    protected $wxapp;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $service = new WechatService();
        $this->wxapp = $service->wxapp();
        $this->getMessage();
    }
    /**
     * @throws \EasyWeChat\Kernel\Exceptions\BadRequestException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * 微信开启服务器关联
     */
    public function related()
    {
        $response = $this->wxapp->server->serve();
        // 将响应输出
        $response->send();exit();
    }
    /*
     * 接受消息
     *
     */
    public function getMessage()
    {
        $this->wxapp->server->push(function ($message) {
            if (!empty($message)) {
                $file = './log.txt';
                if (!$file) {
                    @mkdir('./log.txt', '777', true);
                }
                $files = fopen($file, 'a+');
                fwrite($files, json_encode($message));
                $this->addMsg($message);
            }
            if (isset($message['MsgType'])) {
                switch ($message['MsgType']) {
                    case 'event':
                        $result = $this->MsgTypeEvent($message);
                        break;
                    case 'text':
                        $result = $this->MsgTypeText($message);
                        break;
                    case 'image':
                        $result = $this->MsgTypeImage($message);
                        break;
                    case 'voice':
                        $result = $this->MsgTypeVoice($message);
                        break;
                    case 'video':
                        $result = $this->MsgTypeVideo($message);
                        break;
                    case 'location':
                        $result = $this->MsgTypeLocation($message);
                        break;
                    case 'link':
                        $result = $this->MsgTypeLink($message);
                        break;
                    case 'file':
                        $result = $this->MsgTypeFile($message);
                        break;
                    // ... 其它消息
                    default:
                        $result = '欢迎关注！';
                        break;
                }
                return $result;
            }
        });
    }
    /**
     * *****************消息事件*************************************************
     */
    public function MsgTypeEvent($message)
    {
        $content = "";
        switch ($message['Event']) {
            case "subscribe": // 关注公众号 添加关注回复
                $content = $this->getSubscribeReply($message);
                // 构造Material数据并返回
                break;
            case "unsubscribe": // 取消关注公众号
                $content = $this->getSubscribeReply($message);
                break;
            case "VIEW": // VIEW事件 - 点击菜单跳转链接时的事件推送
                $content = "";
                break;
            case "SCAN": // SCAN事件 - 用户已关注时的事件推送
                $content = "";
                break;
            case "CLICK": // CLICK事件 - 自定义菜单事件
                break;
            default:
                break;
        }
        return $content;
    }
    /**
     * @param $message
     * @return int
     * 文本
     */
    public function MsgTypeText($message)
    {
        $content = $message['Content'];
        $info = WechatReply::where($this->where)->where('keyword', 'like', '%' . $content . '%')->find();
        $file = './log1.txt';
        if ($info) {
            $content = $this->getWeixinReplyDetail($info);
        } else {
            $content = $this->getWeixinReplyDefault();
        }
        return $content;
    }
    public function MsgTypeImage($message)
    {
        return $this->getWeixinReplyDefault();
    }
    /*
     * 音频
     */
    public function MsgTypeVoice($message)
    {
        return $this->getWeixinReplyDefault();
    }
    /**
     * @param $message
     * 视频
     */
    public function MsgTypeVideo($message)
    {
        return $this->getWeixinReplyDefault();
    }
    /**
     * @param $message
     * 定位
     */
    public function MsgTypeLocation($message)
    {
        return $this->getWeixinReplyDefault();

    }
    /**
     * @param $message 链接
     */
    public function MsgTypeLink($message)
    {
        return $this->getWeixinReplyDefault();
    }

    public function MsgTypeFile($message)
    {
        return $this->getWeixinReplyDefault();
    }

    /**
     * *****************获取消息*************************************************
     */
    /**
     * 获取关注回复
     * @param
     * @return unknown|string
     */
    public function getSubscribeReply()
    {
        $weixin_replay = new WechatReply();
        $info = $weixin_replay
            ->where($this->where)
            ->where('type', 'subscribe')
            ->find();
        if (!empty($info)) {
            $info = $this->getWeixinReplyDetail($info);
            return $info;
        } else {
            return $this->getWeixinReplyDefault();
        }
    }
    /**
     * 获取默认消息
     *
     */

    protected function getWeixinReplyDefault()
    {

        return WechatReply::where($this->where)->where('type', 'default')->value('data');
    }

    /**
     * @param $Material_id
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 媒体详情
     */

    protected function getWeixinReplyDetail($info)
    {
        $msg_type = $info->msg_type;
        $material_id = $info->material_id;
        $media_id = '';
        if ($material_id) {
            $material = WechatMaterial::find($material_id);
            if ($material) {
                $media_id = $material->media_id;
            }
        }
        $message = WechatReply::where($this->where)->where('type', 'default')->value('data');
        switch ($msg_type) {
            case 'text':
                $message = $info->data;
                break;
            case 'keyword':
                $message = $info->data;
                break;
            case 'image':
                if (WechatMaterial::where('media_id', $media_id)->find()) {
                    $message = new Image($media_id);
                }
                break;
            case 'news':
                $new = WechatMaterialInfo::where($this->where)->where('material_id', $material_id)->find();
                if ($new) {
                    $newsList[] = new NewsItem([
                        'title' => $new->title,
                        'description' => $new->digest,
                        'url' => $new->url,
                        'image' => $new->cover,
                    ]);
                    $message = new News($newsList);
                }
                break;
            case 'video':
                if (WechatMaterial::where('media_id', $media_id)->find()) {
                    $message = new Video($media_id, $material->file_name, $material->des);
                }
                break;
            case 'voice':
                if (WechatMaterial::where('media_id', $media_id)->find()) {
                    $message = new Voice($media_id);
                }
                break;
            default:
                break;
        }
        return $message;
    }
    //添加消息
    public function addMsg($message)
    {
        $type = $message['MsgType'];
        $data['openid'] = $message['FromUserName'];
        $data['nickname'] = $this->wxapp->user->get($message['FromUserName'])['nickname'];
        $data['content_json'] = json_encode($message);
        $data['create_time'] = $message['CreateTime'];
        $data['status'] = 1;
        $data['type'] = $type;
        switch ($type) {
            case 'text':
                $data['content'] = $message['Content'];
                $keys = WechatReply::where($this->where)
                    ->where('keyword', 'like', $data['content'])
                    ->find();
                if ($keys) {
                    $data['keyword_id'] = $keys->id;
                }
                break;
            case 'image':
                $data['content'] = $message['PicUrl'];
                $data['Material_id'] = $message['MediaId'];
                break;
            case 'voice':
                $data['content'] = $data['content_json'];
                $data['Material_id'] = $message['MediaId'];
                break;
            case 'video':
                $data['content'] = $data['content_json'];
                $data['Material_id'] = $message['MediaId'];
                break;
            case 'shortvideo':
                $data['content'] = $data['content_json'];
                $data['Material_id'] = $message['MediaId'];
                break;
            case 'location':
                $data['content'] = $data['content_json'];
                break;
            case 'link':
                $data['content'] = $data['content_json'];
                break;
            case 'event';
                $data['event'] = $message['Event'];
                break;
            default:
                $data['content'] = $message['Content'];
                $keys = WechatReply::where($this->where)
                    ->where('keyword', 'like', $data['content'])
                    ->find();
                if ($keys) {
                    $data['keyword_id'] = $keys->id;
                }
                break;
        }
        $res = WechatMsgHistory::create($data);
        if ($res) {
            return $res;
        } else {
            return '';
        }
    }

}