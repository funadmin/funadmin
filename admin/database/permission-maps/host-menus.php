<?php

declare(strict_types=1);

// 冻结：核心菜单（按 query 中的 name 识别）→ 页面列表接口的按方法推导码。迁移已固化，不要手改。
return [
    'SystemDict' => 'system:dict:types',
    'SystemRole' => 'authorization:role:index',
    'SystemDept' => 'authorization:department:tree',
    'SystemUser' => 'authorization:admin:index',
    'SystemMenu' => 'authorization:menu:tree',
    'SystemLogOperation' => 'system:operation-log:index',
    'SystemPermission' => 'authorization:permission:tree',
    'SystemBlacklist' => 'system:blacklist:index',
    'SystemLanguage' => 'system:language:index',
    'SystemMemberGroup' => 'system:member-group:index',
    'SystemMemberLevel' => 'system:member-level:index',
    'SystemMember' => 'system:member:index',
    'SystemAttachment' => 'system:attachment:index',
    'SystemConfig' => 'system:config:index',
    'SystemPlugin' => 'plugin:plugin:installed',
    'SystemUpgrade' => 'system:upgrade:status',
    'BusinessDevelopment' => 'development:business:modules',
    'BusinessMine' => 'development:business:modules',
    'BusinessVisual' => 'development:business:create-visual',
    'BusinessDatabase' => 'development:business:inspect-database',
    'BusinessRecords' => 'development:business:generations',
    'AiDevelopment' => 'ai:ai:conversation-index',
    'ApplicationList' => 'identity:enterprise-application:index',
    'OAuthClientManagement' => 'identity:o-auth-client:index',
    'DomainManagement' => 'identity:enterprise-application:domains',
    'SsoConfiguration' => 'identity:sso-configuration:config',
    'ScopeClaimManagement' => 'identity:scope-claim:index',
    'IdentityUsers' => 'identity:identity-user:index',
    'IdentitySessions' => 'identity:oidc-session:index',
    'SigningKeys' => 'identity:oidc-signing-key:index',
    'IdentityAudit' => 'identity:identity-audit:index',
    'ApplicationPortal' => 'identity:enterprise-application:portal',
];
