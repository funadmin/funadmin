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

use addons\wechat\backend\model\WechatMaterial;
use addons\wechat\backend\model\WechatMaterialInfo;
use addons\wechat\backend\model\WechatReply;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use app\common\controller\AddonsBackend;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class MsgHistory extends AddonsBackend
{
    protected $wxapp;
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->modelClass = new \addons\wechat\backend\model\WechatMsgHistory();
        $this->wxapp = (new \addons\wechat\backend\service\WechatService())->wxapp();
    }


    public function reply()
    {
        if ($this->request->isPost()) {
            $material_id = $this->request->post('material_id');
            $openid = $this->request->post('openid');
            $msg_type = $this->request->post('msg_type');
            $material = WechatMaterial::find($material_id);
            if ($material) {
                $media_id = $material->media_id;
            } else {
                $media_id = '';
            }
            $message = WechatReply::where('type', 'default')->value('data');
            switch ($msg_type) {
                case 'text':
                    $data = $this->request->post('data');
                    $message = new Text($data);
                    break;
                case 'image':
                    if (WechatMaterial::where('media_id', $media_id)->find()) {
                        $message = new Image($media_id);
                    }
                    break;
                case 'news':
                    $new = WechatMaterialInfo::where('material_id', $material_id)->find();
                    if ($new) {
                        $newsList[] = new NewsItem([
                            'title' => $new->title,
                            'description' => $new->digest,
                            'url' => $new->url,
                            'image' => $new->cover,
                        ]);
                        $message = new News($newsList);
                    } else {
                        $message = WechatReply::where('type', 'default')->value('data');
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
            $result = $this->wxapp->customer_service->message($message)->to($openid)->send();
            if ($result['errcode'] == 0) {
                $this->success(lang('send success'));
            } else {
                $this->error(lang('send fail'));
            }
        } else {
            $id = $this->request->get('id');
            $info = $this->modelClass->where('store_id', $this->store_id)->find($id);
            $user = $this->wxapp->user->get($info->openid);
            $materialGroup = $this->getMaterialGroup();
            $view = ['title' => lang('reply'), 'user' => $user, 'info' => $info, 'materialGroup' => $materialGroup];
            View::assign($view);
            return view();
        }

    }
    /**
     * @param $type
     * @param int $id
     * @return array|\think\Collection|\think\Model|null
     * 获取素材
     */
    protected function getMaterialGroup(){
        $materialGroup = [];
        foreach ($this->materialType as $k=>$v){
            if($v === 'news'){
                $weixin_material_data = WechatMaterial::where('type','news')->select()->toArray();
                if (!empty($weixin_material_data)) {
                    foreach($weixin_material_data as $key=>$value){
                        $item_info = WechatMaterialInfo::where('material_id' , $value['id'])->select()->toArray();
                        $weixin_material_data[$key]['item_info'] = $item_info;
                    }
                }
                $materialGroup[$v] = $weixin_material_data;
            }else{
                $weixin_material_data = WechatMaterial::where('type',$v)->select()->toArray();
                $materialGroup[$v] = $weixin_material_data;
            }
        }
        return $materialGroup;
    }
}