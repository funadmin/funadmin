<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\ContentModel;
use app\cms\model\Domain;
use app\cms\model\Site;
use app\cms\model\SiteAdmin;
use app\cms\model\SiteLocale;
use app\cms\model\SiteModel;
use support\Db;
use InvalidArgumentException;
final class SiteService
{
    public static function host(string $host): string
    {
        $host=strtolower(trim($host));
        if(preg_match('/^(.+):([0-9]{1,5})$/D',$host,$m)) { if((int)$m[2]<1||(int)$m[2]>65535) throw new InvalidArgumentException('端口无效'); $host=$m[1]; }
        if(strlen($host)>253||!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D',$host)) throw new InvalidArgumentException('域名无效，请使用不带协议的 ASCII 域名');
        return $host;
    }
    public function get(string $id,bool $active=false): array
    {
        $q=Site::query()->where('id',Input::id($id));if($active)$q->enabled();
        return Input::row($q->first())??throw new InvalidArgumentException('站点不存在',404);
    }
    /** Serialize invariants owned by a site; callers must already be in a transaction. */
    public function lock(string $siteId): array
    {
        if(Db::connection()->transactionLevel()===0)throw new \LogicException('Site lock requires a transaction');
        return Input::row(Site::query()->where('id',Input::id($siteId))->lockForUpdate()->first())??throw new InvalidArgumentException('站点不存在',404);
    }
    public function listing(string $adminId): array
    {
        $q=Site::query();
        if(!(new SiteAccess())->isSuper($adminId)) $q->whereIn('id',SiteAdmin::query()->select('site_id')->where('admin_id',Input::id($adminId)));
        return Input::rows($q->orderBy('id')->get());
    }
    public function save(array $input,string $adminId): array
    {
        if(empty($input['id'])) (new SiteAccess())->super($adminId); else (new SiteAccess())->assert(Input::id($input['id']),$adminId);
        return Db::transaction(function()use($input,$adminId): array {
            $old=empty($input['id'])?null:$this->lock(Input::id($input['id']));$locale=Input::locale($input['default_locale']??$old['default_locale']??'zh-cn');$now=Input::now();
            if($old&&!SiteLocale::query()->forSite($old['id'])->forLocale($locale)->enabled()->exists()) throw new InvalidArgumentException('默认语言必须已启用');
            $settings=SiteConfiguration::siteSettings($input['settings']??$old['settings']??[]);
            if($old&&isset($settings['configuration']))SiteConfiguration::reconcileLocales($old['id'],$settings['configuration']['fields']);
            // 表单权限补齐标记由系统维护：编辑时沿用旧值；新站点创建时即按预设授权，直接标记为已补齐。
            $marker=$old?($old['settings'][FormPermissionUpgrade::MARKER]??null):$now;unset($settings[FormPermissionUpgrade::MARKER]);if($marker!==null)$settings[FormPermissionUpgrade::MARKER]=$marker;
            $data=['code'=>Input::code($input['code']??$old['code']??''),'name'=>Input::text($input['name']??$old['name']??'',100),'default_locale'=>$locale,'theme'=>Input::template($input['theme']??$old['theme']??'default'),'settings'=>Input::json($settings),'status'=>(int)($input['status']??$old['status']??1),'updated_at'=>$now];
            if($old) { $id=$old['id'];Site::query()->withTrashed()->where('id',$id)->update($data); }
            else {
                $row=new Site();$row->fill($data+['created_at'=>$now])->save();$id=(string)$row->getKey();
                $this->saveLocale($id,['locale'=>$locale,'url_prefix'=>'','name'=>$data['name']]);
                foreach(ContentModel::query()->enabled()->get() as $model) SiteModel::query()->insert(['site_id'=>$id,'model_id'=>$model->getRawOriginal('id'),'default_template'=>'detail','status'=>1]);
            }
            if(!empty($input['host'])) $this->saveDomain($id,['host'=>$input['host'],'primary'=>true]);
            if(isset($input['primary_domain_id'])) {
                $domain=Domain::query()->where('id',Input::id($input['primary_domain_id']))->forSite($id)->enabled()->first();if(!$domain) throw new InvalidArgumentException('主域名必须属于本站且启用');
                Site::query()->withTrashed()->where('id',$id)->update(['primary_domain_id'=>$domain->getRawOriginal('id')]);
            }
            if(!$old)SiteRoleSchema::seedSite($id);
            (new PublicPageCache())->invalidateSite($id);
            return $this->get($id);
        });
    }
    public function locales(string $siteId,bool $active=false): array
    {
        $q=SiteLocale::query()->forSite(Input::id($siteId));if($active)$q->enabled();$rows=Input::rows($q->orderBy('id')->get());foreach($rows as &$row)$row['home_path']=(new RouteService())->canonical($siteId,'home',$row['id'])['path']??'';return $rows;
    }
    public function language(string $siteId,string $locale): array
    {
        return Input::row(SiteLocale::query()->forSite(Input::id($siteId))->forLocale(Input::locale($locale))->enabled()->first())??throw new InvalidArgumentException('语言未启用',404);
    }
    public function saveLocale(string $siteId,array $input): array
    {
        return Db::transaction(function()use($siteId,$input): array {
            $site=$this->lock($siteId);$locale=Input::locale($input['locale']??'');$status=(int)($input['status']??1);
            if($locale===$site['default_locale']&&$status!==1) throw new InvalidArgumentException('不能停用默认语言');
            $old=Input::row(SiteLocale::query()->forSite($siteId)->forLocale($locale)->lockForUpdate()->first());
            $prefix=Input::text($input['url_prefix']??$old['url_prefix']??$locale,64);
            if($prefix!==''&&!preg_match('/^[a-z][a-z0-9-]*$/D',$prefix)) throw new InvalidArgumentException('语言路径前缀无效');
            $now=Input::now();$data=['site_id'=>$siteId,'locale'=>$locale,'url_prefix'=>$prefix,'name'=>Input::text($input['name']??$old['name']??$site['name'],100),'seo_title'=>Input::text($input['seo_title']??$old['seo_title']??''),'seo_description'=>Input::text($input['seo_description']??$old['seo_description']??'',500),'settings'=>Input::json(SiteConfiguration::localeSettings($siteId,$input['settings']??$old['settings']??[])),'status'=>$status,'updated_at'=>$now];
            if($old) { $id=$old['id'];SiteLocale::query()->where('id',$id)->update($data); }
            else { $row=new SiteLocale();$row->fill($data+['created_at'=>$now])->save();$id=(string)$row->getKey();(new RouteService())->publish($siteId,$locale,'home',$id,$prefix===''?'/':'/'.$prefix.'/'); }
            if(isset($input['home_path'])&&$input['home_path']!=='')(new RouteService())->publish($siteId,$locale,'home',$id,(string)$input['home_path']);
            (new PublicPageCache())->invalidateSite($siteId);
            return Input::row(SiteLocale::query()->where('id',$id)->first());
        });
    }
    public function domains(string $siteId): array { return Input::rows(Domain::query()->forSite(Input::id($siteId))->orderBy('id')->get()); }
    public function saveDomain(string $siteId,array $input): array
    {
        $this->get($siteId);$raw=Input::text($input['host']??'',253);
        if(str_contains($raw,':')) throw new InvalidArgumentException('域名配置不包含端口或协议');$host=self::host($raw);
        return Db::transaction(function()use($siteId,$input,$host): array {
            $site=$this->lock($siteId);
            $old=Input::row(Domain::query()->where('host',$host)->lockForUpdate()->first());
            if($old&&$old['site_id']!==$siteId) throw new InvalidArgumentException('域名已绑定其他站点',409);
            $status=(int)($input['status']??1);
            if(($input['primary']??false)&&$status!==1)throw new InvalidArgumentException('主域名必须启用');
            if($old&&$old['id']===$site['primary_domain_id']&&$status!==1) throw new InvalidArgumentException('请先更换主域名');
            $now=Input::now();$data=['site_id'=>$siteId,'host'=>$host,'status'=>$status,'updated_at'=>$now];
            if($old) { $id=$old['id'];Domain::query()->where('id',$id)->update($data); }
            else { $row=new Domain();$row->fill($data+['verification_status'=>'pending','created_at'=>$now])->save();$id=(string)$row->getKey(); }
            if(($input['primary']??false)||(!$site['primary_domain_id']&&$status===1)) Site::query()->withTrashed()->where('id',$siteId)->update(['primary_domain_id'=>$id]);
            (new PublicPageCache())->invalidateSite($siteId);
            return Input::row(Domain::query()->where('id',$id)->first());
        });
    }
    public function byHost(string $host): ?array
    {
        try{$host=self::host($host);}catch(InvalidArgumentException){return null;}
        $domain=Input::row(Domain::query()->where('host',$host)->enabled()->first());if(!$domain)return null;
        try{$site=$this->get($domain['site_id'],true);}catch(InvalidArgumentException){return null;}
        $primary=Input::row(Domain::query()->where('id',$site['primary_domain_id'])->forSite($site['id'])->enabled()->first());if(!$primary)return null;
        return $site+['domain'=>$domain,'primary_host'=>$primary['host']];
    }
}
