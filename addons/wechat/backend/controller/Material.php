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
use EasyWeChat\Kernel\Messages\Article;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class Material extends \app\common\controller\AddonsBackend
{
    //图片（image）: 2M，支持bmp/png/jpeg/jpg/gif格式
    //
    //语音（voice）：2M，播放长度不超过60s，mp3/wma/wav/amr格式
    //
    //视频（video）：10MB，支持MP4格式
    //
    //缩略图（thumb）：64KB，支持JPG格式
    protected $imageValidate = [
        'image' => 'filesize:2048|fileExt:jpg,png,gif,jpeg'

    ];
    protected $videoValidate = [
        'file' => 'filesize:10240|fileExt:avi,rmvb,3gp,flv,mp4,rm'

    ];
    protected $voiceValidate = [
        'file' => 'filesize:2048|fileExt:mp3,wma,wav,amr'

    ];
    protected $thumbValidate = [
        'image' => 'filesize:64|fileExt:jpg'

    ];
    public function __construct(\think\App $app){
        parent::__construct($app);
    }
    public function material()
    {

        if (Request::isPost()) {
            $addons_wechat_aid = $this->request->post('addons_wechat_aid');
            if (!$addons_wechat_aid) {
                $addons_wechat_aid = $this->wechatAccount->id;
            }
            if (!$addons_wechat_aid) {
                return $result = ['code' => 0, 'msg' => lang('account is not accessed'),];

            }
            $keys = $this->request->post('keys', '', 'trim');
            $page = $this->request->post('page') ? $this->request->post('page') : 1;
            $list = Db::name('addons_wechat_material')
                ->where('nickname', 'like', '%' . $keys . '%')
                ->where('addons_wechat_aid', $addons_wechat_aid)
                ->order('fans_id desc')
                ->paginate(['list_rows' => $this->pageSize, 'page' => $page])
                ->toArray();
            return $result = ['code' => 0, 'msg' => lang('get info success'), 'data' => $list['data'], 'count' => $list['total']];
        }
        $materialGroup = $this->getMaterialGroup();
        $view = [
            'title' => lang('material'),
            'info' => '',
            'materialGroup' => $materialGroup,
        ];
        View::assign($view);
        return view();


    }

//添加图文素材
    public function materialAdd()
    {
        if (Request::isPost()) {
            $data = $this->request->post('content');

            foreach ($data as $k => $v) {
                $article = new Article($data[$k]);
                $articles[$k] = $article;
            }
            $res = $this->wechatApp->material->uploadArticle($articles);
            $this->showError($res);
            $AddonsWechatMaterialData = [
                'store_id' => $this->store_id,
                'addons_wechat_aid' => $this->wechatAccount->id,
                'type' => 'news',
                'media_id' => $res['media_id'],
            ];

            $material = $this->getMaterialByMediaId($res['media_id']);

            // 启动事务
            Db::startTrans();
            try {
                $mat = WechatMaterial::create($AddonsWechatMaterialData);
//                if(isset($data[0])){
                //多图文
                foreach ($data as $k => $v) {

                    $data[$k]['cover'] = WechatMaterial::where('media_id', $data[$k]['thumb_media_id'])->value('media_url');
                    $data[$k]['local_cover'] = WechatMaterial::where('media_id', $data[$k]['thumb_media_id'])->value('local_cover');
                    $data[$k]['store_id'] = $this->store_id;
                    $data[$k]['addons_wechat_aid'] = $this->wechatAccount->id;
                    $data[$k]['url'] = $material['news_item'][$k]['url'];
                    $data[$k]['material_id'] = $mat->id;
                    $matinfo = Db::name('AddonsWechatMaterialInfo')->save($data[$k]);

                }

                // 提交事务
                Db::commit();
                $this->success('成功');

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->error($e->getMessage());

            }
        }
        $params['name'] = 'container';
        $params['content'] = '';
        $view = [
            'info' => [],
            'title' => lang('add'),
            'ueditor' => build_ueditor($params),
        ];
        View::assign($view);
        return view();
    }

    public function materialEdit()
    {
        if (Request::isPost()) {
            $data = $this->request->post('content');
            $id = $this->request->post('mediaId');
            $mediaId = WechatMaterial::where('id', $id)->value('media_id');

            foreach ($data as $k => $v) {

                $article = new Article($data[$k]);

                $res = $this->wechatApp->material->updateArticle($mediaId, $article, $k);

            }

            $this->showError($res);

            $material = $this->getMaterialByMediaId($mediaId);

            // 启动事务
            Db::startTrans();
            try {
//
                foreach ($data as $k => $v) {
                    $data[$k]['id'] = WechatMaterialInfo::where($this->where)->where('thumb_media_id', $v['thumb_media_id'])->value('id');
                    $data[$k]['cover'] = WechatMaterial::where('media_id', $data[$k]['thumb_media_id'])->value('media_url');
                    $data[$k]['store_id'] = $this->store_id;
                    $data[$k]['addons_wechat_aid'] = $this->wechatAccount->id;
                    $data[$k]['url'] = $material['news_item'][$k]['url'];
                    $data[$k]['material_id'] = $id;
                    $data[$k]['update_time'] = time();
                    $AddonsWechatMaterialInfoModel = WechatMaterialInfo::find($data[$k]['id']);
                    $r[$k] = $AddonsWechatMaterialInfoModel->force()->save($data[$k]);
                }

                // 提交事务
                Db::commit();
                $this->success('成功');

            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $id = $this->request->get('id');
        $info = WechatMaterialInfo::where('material_id', $id)->select()->toArray();
        $params['name'] = 'container';
        $params['content'] = '';
        $view = [
            'title' => lang('edit'),
            'info' => $info,
            'ueditor' => build_ueditor($params),
        ];
        View::assign($view);

        return view('material_add');
    }

//素材同步
    public function materialAysn()
    {
        if (Request::isPost()) {
            $res = cache('materialList');
            if (!$res) {
                $res = $this->wechatApp->material->list('news', 0, 50);
                cache('materialList', $res, 3600);
            }
            $this->showError($res);
            foreach ($res['item'] as $k => $v) {
                $material = WechatMaterial::where('media_id', $v['media_id'])->find();
                if (!$material) {
                    $material = ['store_id' => $this->store_id,
                        'addons_wechat_aid' => $this->wechatAccount->id,
                        'media_id' => $v['media_id'],
                        'media_url' => $v['content']['news_item'][0]['thumb_url'],
                        'type' => "news",
                    ];
                    $wxmater = WechatMaterial::create($material);
                    foreach ($v['content']['news_item'] as $kk => $vv) {
                        $info = ['store_id' => $this->store_id,
                            'addons_wechat_aid' => $this->wechatAccount->addons_wechat_aid,
                            'material_id' => $wxmater->id,
                            'thumb_media_id' => $vv['thumb_media_id'],
                            'local_cover' => $vv['thumb_url'],
                            'cover' => $vv['thumb_url'],
                            'title' => $vv['title'],
                            'author' => $vv['author'],
                            'show_cover' => $vv['show_cover_pic'],
                            'digest' => $vv['digest'],
                            'content' => $vv['content'],
                            'url' => $vv['url'],
                            'content_source_url' => $vv['content_source_url'],
                            'need_open_comment' => $vv['need_open_comment'],
                            'only_fans_can_comment' => $vv['only_fans_can_comment'],
                        ];
                        WechatMaterialInfo::create($info);

                    }
                }
            }
            $this->success(lang('aysn success'));

        } else {

            $this->error(lang('invalid request'));
        }


    }

    public function materialDel()
    {

        $id = $this->request->post('id');
        $material = WechatMaterial::find($id);
        //删除微信媒体库
        $this->wechatApp->material->delete($material->media_id);

        if ($material['type'] == 'news') {
            $info = WechatMaterialInfo::where($this->where)->where('material_id', $id)->delete();
            if ($info && $material->delete()) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('delete fail'));

            }
        } else {
            if ($material->delete()) {

                $this->success(lang('operation success'));
            } else {
                $this->error(lang('delete fail'));

            }
        }

    }

// 发送消息
    public function materialSend()
    {
        if (Request::isPost()) {
            $id = $this->request->post('id');
            $info = WechatMaterial::find($id);
            $res = $this->sendAll($info);
            $this->showError($res);
            $this->success(lang('send success'));
        } else {
            $this->error(lang('send fail'));

        }

    }

//预览消息

    public function materialPreview()
    {
        if (Request::isPost()) {
            $wxname = $this->request->post('wxname');
            $id = $this->request->post('id');
            $info = WechatMaterial::find($id);
            $res = $this->preview($info, $wxname);

            $this->showError($res);
            $this->success(lang('send success'));

        } else {
            $this->error(lang('send fail'));

        }


    }

    protected function preview($info, $wxname)
    {

        // 发送预览群发消息给指定的 openId 用户
        //$this->wechatApp->broadcasting->previewText($text, $openId);
        //$this->wechatApp->broadcasting->previewNews($mediaId, $openId);
        //$this->wechatApp->broadcasting->previewVoice($mediaId, $openId);
        //$this->wechatApp->broadcasting->previewImage($mediaId, $openId);
        //$this->wechatApp->broadcasting->previewVideo($message, $openId);
        //$this->wechatApp->broadcasting->previewCard($cardId, $openId);
        //发送预览群发消息给指定的微信号用户
        //$wxanme 是用户的微信号，比如：notovertrue
        //
        //$this->wechatApp->broadcasting->previewTextByName($text, $wxname);
        //$this->wechatApp->broadcasting->previewNewsByName($mediaId, $wxname);
        //$this->wechatApp->broadcasting->previewVoiceByName($mediaId, $wxname);
        //$this->wechatApp->broadcasting->previewImageByName($mediaId, $wxname);
        //$this->wechatApp->broadcasting->previewVideoByName($message, $wxname);
        //$this->wechatApp->broadcasting->previewCardByName($cardId, $wxname);

        $type = $info->type;
        switch ($type) {
            case 'text':
                $res = $this->wechatApp->broadcasting->previewTextByName($info->media_id, $wxname);
                break;

            case 'image':

                $res = $this->wechatApp->broadcasting->previewImageByName($info->media_id, $wxname);

                break;

            case 'news':
                $res = $this->wechatApp->broadcasting->previewNewsByName($info->media_id, $wxname);

                break;
            case 'video':
                $res = $this->wechatApp->broadcasting->previewVideoByName($info->media_id, $wxname);


                break;
            case 'voice':
                $res = $this->wechatApp->broadcasting->previewVoiceByName($info->media_id, $wxname);
                break;
            case 'card':
                $res = $this->wechatApp->broadcasting->previewCardByName($info->media_id, $wxname);
                break;
            default:
                $res = $this->wechatApp->broadcasting->previewTextByName($info->media_id, $wxname);

                break;
        }

        return $res;

    }

    protected function sendAll($info)
    {


        $type = $info->type;
        switch ($type) {

            case 'image':

                $res = $this->wechatApp->broadcasting->sendImage($info->media_id);

                break;

            case 'news':
                $res = $this->wechatApp->broadcasting->sendNews($info->media_id);

                break;
            case 'video':
                $res = $this->wechatApp->broadcasting->sendVideo($info->media_id);


                break;
            case 'voice':
                $res = $this->wechatApp->broadcasting->sendVoice($info->media_id);
                break;
            case 'card':
                $res = $this->wechatApp->broadcasting->sendCard($info->media_id);
                break;
            default:
                $res = $this->wechatApp->broadcasting->image($info->media_id);

                break;
        }
        return $res;

    }

}