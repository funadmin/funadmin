<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $now = date('Y-m-d H:i:s');
    foreach (['development' => '业务开发','form' => '表单','ai' => 'AI 助手','identity' => '身份与应用'] as $module => $label) {
        $groupCode = 'module:' . $module;
        $group = Db::table('permission')->where('code',$groupCode)->first();
        $groupId = $group?->id ?? Db::table('permission')->insertGetId(['pid'=>0,'app_name'=>'admin','code'=>$groupCode,'obj'=>'','act'=>'','name'=>$label,'resource_type'=>'group','source_type'=>'system','source_name'=>$module,'status'=>1,'created_at'=>$now,'updated_at'=>$now]);
        foreach (glob(app_path('admin/' . $module . '/controller/*.php')) ?: [] as $file) {
            $class = 'app\\admin\\' . $module . '\\controller\\' . basename($file,'.php');
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) continue;
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) continue;
                $routed = false;
                foreach ($method->getAttributes() as $attribute) if (str_starts_with($attribute->getName(),'support\\annotation\\route\\')) $routed = true;
                if (!$routed) continue;
                $resource = PermissionResource::fromParts('admin',$module . '.' . basename($file,'.php'),$method->getName());
                if (Db::table('permission')->where('code',$resource['code'])->exists()) continue;
                Db::table('permission')->insert(['pid'=>$groupId,'app_name'=>'admin','code'=>$resource['code'],'obj'=>$resource['obj'],'act'=>$resource['act'],'name'=>$label . ' / ' . $method->getName(),'resource_type'=>'route','status'=>1,'source_type'=>'system','source_name'=>$module,'created_at'=>$now,'updated_at'=>$now]);
            }
        }
    }
    $menu = static function (string $source, string $name, string $href, string $component, string $route, int $pid, string $permission = '', string $type = 'C', int $order = 0) use ($now): int {
        $existing = Db::table('admin_menu')->where('source_name',$source)->where('href',$href)->first();
        if ($existing) return (int) $existing->id;
        return (int) Db::table('admin_menu')->insertGetId(['pid'=>$pid,'app_name'=>'admin','name'=>$name,'href'=>$href,'query'=>http_build_query(['component'=>$component,'name'=>$route,'type'=>$type,'permission'=>$permission]),'icon'=>'i-ep-grid','target'=>'_self','status'=>1,'sort_order'=>$order,'source_type'=>'admin_web','source_name'=>$source,'permission_id'=>$permission === '' ? 0 : (int) Db::table('permission')->where('code',$permission)->value('id'),'created_at'=>$now,'updated_at'=>$now]);
    };
    $development = $menu('development_tools','开发工具','/development','Layout','Development',0,'','M',40);
    $menu('business_development','业务开发','business/mine','development/business/mine','BusinessDevelopment',$development,'development.business:modules');
    $menu('ai_development','智能开发助手','ai','development/ai/index','AiDevelopment',$development,'development.ai:conversationindex');
    $identity = $menu('enterprise_application_center','身份与应用','/applications','Layout','EnterpriseApplications',0,'','M',50);
    foreach ([['应用列表','center','applications/index','ApplicationList','identity.enterpriseapplication:index'],['OAuth 客户端','oauth-client','applications/oauth/index','OAuthClientManagement','identity.oauthclient:index'],['域名管理','domains','applications/identity-management','DomainManagement','identity.enterpriseapplication:domains'],['单点登录','sso','applications/sso/index','SsoConfiguration','identity.ssoconfiguration:config'],['Scope 与 Claim','scopes','applications/identity-management','ScopeClaimManagement','identity.scopeclaim:index'],['身份用户','users','applications/identity-management','IdentityUsers','identity.identityuser:index'],['会话与授权','sessions','applications/identity-management','IdentitySessions','identity.oidcsession:index'],['签名密钥','signing-keys','applications/identity-management','SigningKeys','identity.oidcsigningkey:index'],['登录审计','audit','applications/identity-management','IdentityAudit','identity.identityaudit:index'],['应用门户','portal','applications/portal','ApplicationPortal','identity.enterpriseapplication:portal']] as [$name,$href,$component,$route,$permission]) $menu('enterprise_application_center',$name,$href,$component,$route,$identity,$permission);
};
