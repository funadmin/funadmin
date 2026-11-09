<?php
declare(strict_types=1);
namespace app\cms\service;

/** Site operations map to existing host capabilities, preserving the system ceiling on upgrade. */
final class PermissionCatalog
{
    public static function groups(): array
    {
        return [
            'site'=>['name'=>'站点管理','menu'=>'detail','actions'=>['detail'=>'查看设置','edit'=>'修改基本设置','domain'=>'管理域名','locale'=>'管理语言']],
            'category'=>['name'=>'栏目管理','menu'=>'list','actions'=>['list'=>'查看栏目','create'=>'新建栏目','edit'=>'编辑栏目','status'=>'启用/停用']],
            'content'=>['name'=>'内容管理','menu'=>'list','actions'=>['list'=>'查看内容','create'=>'新建内容','edit'=>'编辑草稿','preview'=>'预览内容','publish'=>'发布/发布历史修订','offline'=>'下架内容']],
            'menu'=>['name'=>'导航管理','menu'=>'list','actions'=>['list'=>'查看导航组','create'=>'新建导航组','edit'=>'编辑导航组','status'=>'启用/停用','delete'=>'删除导航组']],
            'tag'=>['name'=>'模板标签','menu'=>'list','actions'=>['list'=>'查看标签','create'=>'新建标签','edit'=>'编辑草稿','preview'=>'预览标签','publish'=>'发布标签']],
            'model'=>['name'=>'内容模型','menu'=>'list','actions'=>['list'=>'查看模型','status'=>'本站启用/停用','template'=>'本站默认模板']],
            'submission'=>['name'=>'会员投稿','menu'=>'list','actions'=>['list'=>'查看投稿','review'=>'审核投稿']],
            'access'=>['name'=>'站点权限','menu'=>'list','actions'=>['list'=>'查看成员/角色','members'=>'管理成员','roles'=>'管理角色/权限']],
            'form'=>['name'=>'表单','menu'=>'list','actions'=>['list'=>'查看表单','manage'=>'管理表单','submissions'=>'查看提交','handle'=>'跟进处理','export'=>'导出提交','delete'=>'删除提交']],
        ];
    }
    public static function operations(): array
    {
        $codes=[];foreach(self::groups() as $group=>$item)foreach($item['actions'] as $action=>$name)$codes[]="cms:$group:$action";return $codes;
    }
    public static function menus(): array
    {
        $codes=[];foreach(self::groups() as $group=>$item)$codes[]="cms:$group:".$item['menu'];return $codes;
    }
    /**
     * 站点操作的宿主上限：执行该操作的后台接口所需的按方法权限码（全部满足才可分配）。
     * 由升级前的粗粒度上限按"旧权限 → 它覆盖的全部方法"展开而来，保证升级前后可分配的站点操作不变。
     *
     * @return list<string>
     */
    public static function ceilings(string $operation): array
    {
        if(!in_array($operation,self::operations(),true))throw new \InvalidArgumentException('未知站点权限',422);
        return self::CEILINGS[$operation];
    }
    private const CEILINGS = [
        'cms:site:detail' => ['cms:sites:configuration', 'cms:sites:context', 'cms:sites:index', 'cms:sites:settings'],
        'cms:site:edit' => ['cms:sites:configuration-save', 'cms:sites:domain-save', 'cms:sites:locale-save', 'cms:sites:model-save', 'cms:sites:options', 'cms:sites:save'],
        'cms:site:domain' => ['cms:sites:configuration-save', 'cms:sites:domain-save', 'cms:sites:locale-save', 'cms:sites:model-save', 'cms:sites:options', 'cms:sites:save'],
        'cms:site:locale' => ['cms:sites:configuration-save', 'cms:sites:domain-save', 'cms:sites:locale-save', 'cms:sites:model-save', 'cms:sites:options', 'cms:sites:save'],
        'cms:category:list' => ['cms:categories:index', 'cms:sites:context'],
        'cms:category:create' => ['cms:categories:save', 'cms:categories:status'],
        'cms:category:edit' => ['cms:categories:save', 'cms:categories:status'],
        'cms:category:status' => ['cms:categories:save', 'cms:categories:status'],
        'cms:content:list' => ['cms:content:detail', 'cms:content:index', 'cms:content:lookups', 'cms:content:revisions', 'cms:content:translation', 'cms:sites:context'],
        'cms:content:create' => ['cms:content:attachments', 'cms:content:save', 'cms:content:upload'],
        'cms:content:edit' => ['cms:content:attachments', 'cms:content:save', 'cms:content:upload'],
        'cms:content:preview' => ['cms:content:preview'],
        'cms:content:publish' => ['cms:content:publish'],
        'cms:content:offline' => ['cms:content:offline'],
        'cms:menu:list' => ['cms:menus:index', 'cms:menus:targets', 'cms:sites:context'],
        'cms:menu:create' => ['cms:menus:save', 'cms:menus:status'],
        'cms:menu:edit' => ['cms:menus:save', 'cms:menus:status'],
        'cms:menu:status' => ['cms:menus:save', 'cms:menus:status'],
        'cms:menu:delete' => ['cms:menus:remove'],
        'cms:tag:list' => ['cms:sites:context', 'cms:tags:index', 'cms:tags:options', 'cms:tags:providers'],
        'cms:tag:create' => ['cms:tags:save'],
        'cms:tag:edit' => ['cms:tags:save'],
        'cms:tag:preview' => ['cms:tags:preview'],
        'cms:tag:publish' => ['cms:tags:publish'],
        'cms:model:list' => ['cms:models:bindings', 'cms:models:index', 'cms:sites:context'],
        'cms:model:status' => ['cms:sites:configuration-save', 'cms:sites:domain-save', 'cms:sites:locale-save', 'cms:sites:model-save', 'cms:sites:options', 'cms:sites:save'],
        'cms:model:template' => ['cms:sites:configuration-save', 'cms:sites:domain-save', 'cms:sites:locale-save', 'cms:sites:model-save', 'cms:sites:options', 'cms:sites:save'],
        'cms:submission:list' => ['cms:contributions:index', 'cms:sites:context'],
        'cms:submission:review' => ['cms:contributions:review'],
        'cms:access:list' => ['cms:access:assign', 'cms:access:candidates', 'cms:access:index', 'cms:access:remove', 'cms:access:save-role', 'cms:sites:context'],
        'cms:access:members' => ['cms:access:assign', 'cms:access:candidates', 'cms:access:index', 'cms:access:remove', 'cms:access:save-role', 'cms:sites:context'],
        'cms:access:roles' => ['cms:access:assign', 'cms:access:candidates', 'cms:access:index', 'cms:access:remove', 'cms:access:save-role', 'cms:sites:context'],
        'cms:form:list' => ['cms:forms:index', 'cms:forms:summary', 'cms:sites:context'],
        'cms:form:manage' => ['cms:forms:save', 'cms:forms:status', 'cms:forms:delete'],
        'cms:form:submissions' => ['cms:forms:submissions', 'cms:forms:detail', 'cms:forms:file'],
        'cms:form:handle' => ['cms:forms:note', 'cms:forms:transition', 'cms:forms:invalidate'],
        'cms:form:export' => ['cms:forms:export'],
        'cms:form:delete' => ['cms:forms:remove'],
    ];
    /** 站点操作码升级前同时用作接口权限的那些路由（冻结）：站点操作生效时这些接口随之可见。 */
    private const ROUTES = [
        'cms:site:detail' => ['cms:contributions:settings'],
        'cms:site:edit' => ['cms:contributions:save-settings'],
        'cms:category:list' => ['cms:categories:index', 'cms:sites:context'],
        'cms:content:list' => ['cms:content:detail', 'cms:content:index', 'cms:content:lookups', 'cms:content:revisions', 'cms:content:translation', 'cms:sites:context'],
        'cms:content:preview' => ['cms:content:preview'],
        'cms:content:publish' => ['cms:content:publish'],
        'cms:content:offline' => ['cms:content:offline'],
        'cms:menu:list' => ['cms:menus:index', 'cms:menus:targets', 'cms:sites:context'],
        'cms:menu:delete' => ['cms:menus:remove'],
        'cms:tag:list' => ['cms:sites:context', 'cms:tags:index', 'cms:tags:options', 'cms:tags:providers'],
        'cms:tag:preview' => ['cms:tags:preview'],
        'cms:tag:publish' => ['cms:tags:publish'],
        'cms:model:list' => ['cms:models:bindings', 'cms:models:index', 'cms:sites:context'],
        'cms:submission:list' => ['cms:contributions:index', 'cms:sites:context'],
        'cms:submission:review' => ['cms:contributions:review'],
    ];
    /**
     * 宿主权限发现范围（admin.permission_codes 过滤，也参与后台路由判权）：
     * 只保留当前管理员至少在一个站点上生效的站点操作所覆盖的 CMS 权限码；非 CMS 权限码原样保留。
     * 站点操作覆盖 = 操作本身 + 它的宿主上限 + 升级前以该操作码守卫的接口。
     * 成员/角色管理只有在"查看成员/角色"也生效时才覆盖站点授权接口（与升级前一致）。
     *
     * @param list<string> $codes 管理员持有的宿主权限码
     * @param list<string> $operations 至少在一个站点上生效的站点操作
     * @return list<string>
     */
    public static function scope(array $codes, array $operations): array
    {
        $other=array_values(array_filter($codes,static fn($c)=>!str_starts_with((string)$c,'cms:')));
        $allowed=$operations;
        foreach($operations as $op){
            if(!in_array($op,self::operations(),true))continue;
            if(in_array($op,['cms:access:members','cms:access:roles'],true)&&!in_array('cms:access:list',$operations,true))continue;
            $allowed=array_merge($allowed,self::ceilings($op),self::ROUTES[$op]??[]);
        }
        return array_values(array_unique(array_merge($other,array_intersect($codes,$allowed))));
    }
    public static function hostCodes(): array
    {
        // 站点操作本身（能力权限）+ 所有上限用到的按方法接口权限码。
        $codes=self::operations();foreach($codes as $code)$codes=array_merge($codes,self::ceilings($code));return array_values(array_unique($codes));
    }
    public static function registered(): array
    {
        if(!\support\Db::schema()->hasTable('permission'))return [];
        return \support\Db::table('permission')->where('source_type','plugin')->where('source_name','cms')->where('status',1)->whereNull('deleted_at')->whereIn('resource_type',['route','capability'])->whereIn('code',self::hostCodes())->pluck('code')->all();
    }
    public static function presets(): array
    {
        $read=['cms:category:list','cms:content:list','cms:menu:list','cms:tag:list','cms:model:list','cms:form:list','cms:form:submissions'];
        $edit=array_merge($read,['cms:content:create','cms:content:edit','cms:content:preview','cms:form:handle']);
        return ['viewer'=>['只读',$read],'editor'=>['编辑',$edit],'publisher'=>['发布',array_merge($edit,['cms:content:publish','cms:content:offline'])],'administrator'=>['站点管理员',self::operations()]];
    }
}
