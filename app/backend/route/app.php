<?php

use think\facade\Route;

Route::post('upload', 'system.AdminUpload/upload');

Route::get('development/crud/connections', 'development.DevCrud/connections');
Route::get('development/crud/tables', 'development.DevCrud/tables');
Route::get('development/crud/table/:table', 'development.DevCrud/tableSchema')->pattern(['table' => '[a-z_][a-z0-9_]*']);
Route::post('development/crud/infer', 'development.DevCrud/infer');
Route::post('development/crud/validate', 'development.DevCrud/validate');
Route::post('development/crud/preview', 'development.DevCrud/preview');
Route::post('development/crud/generate', 'development.DevCrud/generate');
Route::get('development/crud/generations/:id', 'development.DevCrud/generationDetail')->pattern(['id' => '\d+']);

Route::get('system/storage', 'system.SystemStorage/index');
Route::put('system/storage', 'system.SystemStorage/update');

Route::post('system/plugin/account/login', 'system.SystemPlugin/accountLogin');
Route::post('system/plugin/account/refresh', 'system.SystemPlugin/accountRefresh');
Route::post('system/plugin/account/logout', 'system.SystemPlugin/accountLogout');
Route::get('system/plugin/account/current', 'system.SystemPlugin/currentAccount');
Route::get('system/plugin/market/categories', 'system.SystemPlugin/marketCategories');
Route::get('system/plugin/market/search', 'system.SystemPlugin/marketSearch');
Route::get('system/plugin/market/:name/versions', 'system.SystemPlugin/marketVersions')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/market/:name', 'system.SystemPlugin/marketDetail')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/market/check-updates', 'system.SystemPlugin/checkUpdates');
Route::get('system/plugin/local/discovered', 'system.SystemPlugin/discovered');
Route::get('system/plugin/local/installed', 'system.SystemPlugin/installed');
Route::post('system/plugin/local/install', 'system.SystemPlugin/installLocal');
Route::post('system/plugin/local/:name/install', 'system.SystemPlugin/installDiscovered')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/local/:name/update', 'system.SystemPlugin/updateLocal')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/local/:name', 'system.SystemPlugin/localDetail')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/cloud/:name/install', 'system.SystemPlugin/installCloud')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/modules/enabled', 'system.SystemPlugin/enabledModules');
Route::post('system/plugin/:name/update', 'system.SystemPlugin/update')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/:name/migrate', 'system.SystemPlugin/migrate')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/:name/enable', 'system.SystemPlugin/enable')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::post('system/plugin/:name/disable', 'system.SystemPlugin/disable')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/:name/config', 'system.SystemPlugin/getConfig')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::put('system/plugin/:name/config', 'system.SystemPlugin/saveConfig')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::delete('system/plugin/:name/uninstall', 'system.SystemPlugin/uninstall')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::delete('system/plugin/:name/purge', 'system.SystemPlugin/purge')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::delete('system/plugin/:name/package', 'system.SystemPlugin/deletePackage')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/:name/history', 'system.SystemPlugin/history')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/:name/history/:id/download', 'system.SystemPlugin/downloadHistory')->pattern(['name' => '[a-z][a-z0-9]*', 'id' => '\\d+']);
Route::post('system/plugin/:name/history/:id/redeploy', 'system.SystemPlugin/redeployHistory')->pattern(['name' => '[a-z][a-z0-9]*', 'id' => '\\d+']);
Route::get('system/plugin/:name/recovery', 'system.SystemPlugin/recoveryInfo')->pattern(['name' => '[a-z][a-z0-9]*']);
Route::get('system/plugin/:name/operations', 'system.SystemPlugin/operations')->pattern(['name' => '[a-z][a-z0-9]*']);

Route::get('system/role/permission-tree', 'system.SystemRole/permissionTree');
Route::get('system/role/all', 'system.SystemRole/all');
Route::get('system/role/parent-options', 'system.SystemRole/parentOptions');
Route::get('system/role', 'system.SystemRole/index');
Route::get('system/role/:id', 'system.SystemRole/detail')->pattern(['id' => '\d+']);
Route::post('system/role', 'system.SystemRole/create');
Route::put('system/role/:id', 'system.SystemRole/update')->pattern(['id' => '\d+']);
Route::delete('system/role/:id', 'system.SystemRole/delete')->pattern(['id' => '\d+']);
Route::delete('system/role', 'system.SystemRole/delete');
Route::post('system/role/:id/permissions', 'system.SystemRole/permissions')->pattern(['id' => '\d+']);

Route::get('system/dept/tree', 'system.SystemDepartment/tree');
Route::get('system/dept/:id', 'system.SystemDepartment/detail')->pattern(['id' => '\d+']);
Route::post('system/dept', 'system.SystemDepartment/create');
Route::put('system/dept/:id', 'system.SystemDepartment/update')->pattern(['id' => '\d+']);
Route::delete('system/dept/:id', 'system.SystemDepartment/delete')->pattern(['id' => '\d+']);
Route::delete('system/dept', 'system.SystemDepartment/delete');

Route::get('system/user', 'system.SystemAdmin/index');
Route::get('system/user/:id', 'system.SystemAdmin/detail')->pattern(['id' => '\d+']);
Route::post('system/user', 'system.SystemAdmin/create');
Route::put('system/user/:id', 'system.SystemAdmin/update')->pattern(['id' => '\d+']);
Route::delete('system/user/:id', 'system.SystemAdmin/delete')->pattern(['id' => '\d+']);
Route::delete('system/user', 'system.SystemAdmin/delete');
Route::post('system/user/:id/reset-password', 'system.SystemAdmin/resetPassword')->pattern(['id' => '\d+']);
Route::post('system/user/:id/status', 'system.SystemAdmin/status')->pattern(['id' => '\d+']);

Route::get('system/menu/tree', 'system.SystemMenu/tree');
Route::get('system/menu/:id', 'system.SystemMenu/detail')->pattern(['id' => '\d+']);
Route::post('system/menu', 'system.SystemMenu/create');
Route::put('system/menu/:id', 'system.SystemMenu/update')->pattern(['id' => '\d+']);
Route::delete('system/menu/:id', 'system.SystemMenu/delete')->pattern(['id' => '\d+']);
Route::delete('system/menu', 'system.SystemMenu/delete');


Route::get('system/permission/tree', 'system.SystemPermission/tree');
Route::get('system/permission/:id', 'system.SystemPermission/detail')->pattern(['id' => '\d+']);
Route::post('system/permission', 'system.SystemPermission/create');
Route::put('system/permission/:id', 'system.SystemPermission/update')->pattern(['id' => '\d+']);
Route::delete('system/permission/:id', 'system.SystemPermission/delete')->pattern(['id' => '\d+']);
Route::delete('system/permission', 'system.SystemPermission/delete');

Route::get('system/blacklist', 'system.SystemBlacklist/index');
Route::get('system/blacklist/export', 'system.SystemBlacklist/export');
Route::get('system/blacklist/:id', 'system.SystemBlacklist/detail')->pattern(['id' => '\d+']);
Route::post('system/blacklist', 'system.SystemBlacklist/create');
Route::put('system/blacklist/:id', 'system.SystemBlacklist/update')->pattern(['id' => '\d+']);
Route::post('system/blacklist/:id/status', 'system.SystemBlacklist/status')->pattern(['id' => '\d+']);
Route::delete('system/blacklist', 'system.SystemBlacklist/delete');
Route::post('system/blacklist/restore', 'system.SystemBlacklist/restore');
Route::delete('system/blacklist/destroy', 'system.SystemBlacklist/destroy');
Route::post('system/blacklist/import', 'system.SystemBlacklist/import');

Route::get('system/language', 'system.SystemLanguage/index');
Route::get('system/language/:id', 'system.SystemLanguage/detail')->pattern(['id' => '\d+']);
Route::post('system/language', 'system.SystemLanguage/create');
Route::put('system/language/:id', 'system.SystemLanguage/update')->pattern(['id' => '\d+']);
Route::delete('system/language/:id', 'system.SystemLanguage/delete')->pattern(['id' => '\d+']);
Route::delete('system/language', 'system.SystemLanguage/delete');

Route::get('system/member-group', 'system.SystemMemberGroup/index');
Route::get('system/member-group/export', 'system.SystemMemberGroup/export');
Route::post('system/member-group/import', 'system.SystemMemberGroup/import');
Route::get('system/member-group/:id', 'system.SystemMemberGroup/detail')->pattern(['id' => '\d+']);
Route::post('system/member-group', 'system.SystemMemberGroup/create');
Route::put('system/member-group/:id', 'system.SystemMemberGroup/update')->pattern(['id' => '\d+']);
Route::post('system/member-group/:id/status', 'system.SystemMemberGroup/status')->pattern(['id' => '\d+']);
Route::delete('system/member-group', 'system.SystemMemberGroup/recycle');
Route::post('system/member-group/restore', 'system.SystemMemberGroup/restore');
Route::delete('system/member-group/destroy', 'system.SystemMemberGroup/destroy');

Route::get('system/member-level', 'system.SystemMemberLevel/index');
Route::get('system/member-level/export', 'system.SystemMemberLevel/export');
Route::post('system/member-level/import', 'system.SystemMemberLevel/import');
Route::get('system/member-level/:id', 'system.SystemMemberLevel/detail')->pattern(['id' => '\d+']);
Route::post('system/member-level', 'system.SystemMemberLevel/create');
Route::put('system/member-level/:id', 'system.SystemMemberLevel/update')->pattern(['id' => '\d+']);
Route::post('system/member-level/:id/status', 'system.SystemMemberLevel/status')->pattern(['id' => '\d+']);
Route::delete('system/member-level', 'system.SystemMemberLevel/recycle');
Route::post('system/member-level/restore', 'system.SystemMemberLevel/restore');
Route::delete('system/member-level/destroy', 'system.SystemMemberLevel/destroy');

Route::get('system/member/options', 'system.SystemMember/options');
Route::get('system/member/export', 'system.SystemMember/export');
Route::post('system/member/restore', 'system.SystemMember/restore');
Route::delete('system/member/destroy', 'system.SystemMember/destroy');
Route::post('system/member/import', 'system.SystemMember/import');
Route::get('system/member/:id', 'system.SystemMember/detail')->pattern(['id' => '\d+']);
Route::put('system/member/:id', 'system.SystemMember/update')->pattern(['id' => '\d+']);
Route::post('system/member/:id/status', 'system.SystemMember/status')->pattern(['id' => '\d+']);
Route::get('system/member', 'system.SystemMember/index');
Route::post('system/member', 'system.SystemMember/create');
Route::delete('system/member', 'system.SystemMember/recycle');

Route::get('system/config/options', 'system.SystemConfig/options');
Route::get('system/config', 'system.SystemConfig/index');
Route::get('system/config/:id', 'system.SystemConfig/detail')->pattern(['id' => '\d+']);
Route::post('system/config', 'system.SystemConfig/create');
Route::put('system/config/:id', 'system.SystemConfig/update')->pattern(['id' => '\d+']);
Route::put('system/config/:id/value', 'system.SystemConfig/value')->pattern(['id' => '\d+']);
Route::post('system/config/:id/status', 'system.SystemConfig/status')->pattern(['id' => '\d+']);
Route::delete('system/config', 'system.SystemConfig/delete');
Route::get('system/config-group', 'system.SystemConfig/groups');
Route::post('system/config-group', 'system.SystemConfig/createGroup');
Route::put('system/config-group/:id', 'system.SystemConfig/updateGroup')->pattern(['id' => '\d+']);
Route::delete('system/config-group/:id', 'system.SystemConfig/deleteGroup')->pattern(['id' => '\d+']);

Route::get('system/attachment', 'system.SystemAttachment/index');
Route::get('system/attachment/:id', 'system.SystemAttachment/detail')->pattern(['id' => '\d+']);
Route::put('system/attachment/:id/name', 'system.SystemAttachment/rename')->pattern(['id' => '\d+']);
Route::post('system/attachment/move', 'system.SystemAttachment/move');
Route::delete('system/attachment', 'system.SystemAttachment/delete');
Route::get('system/attachment-group/tree', 'system.SystemAttachmentGroup/tree');
Route::get('system/attachment-group/:id', 'system.SystemAttachmentGroup/detail')->pattern(['id' => '\d+']);
Route::post('system/attachment-group', 'system.SystemAttachmentGroup/create');
Route::put('system/attachment-group/:id', 'system.SystemAttachmentGroup/update')->pattern(['id' => '\d+']);
Route::delete('system/attachment-group/:id', 'system.SystemAttachmentGroup/delete')->pattern(['id' => '\d+']);

Route::get('system/log/operation', 'system.SystemOperationLog/index');
Route::get('system/log/operation/:id', 'system.SystemOperationLog/detail')->pattern(['id' => '\d+']);
Route::delete('system/log/operation/:id', 'system.SystemOperationLog/delete')->pattern(['id' => '\d+']);
Route::delete('system/log/operation', 'system.SystemOperationLog/delete');

Route::get('system/dict/types', 'system.SystemDict/types');
Route::post('system/dict/types', 'system.SystemDict/createType');
Route::put('system/dict/types/:id', 'system.SystemDict/updateType');
Route::delete('system/dict/types/:id', 'system.SystemDict/deleteType');
Route::delete('system/dict/types', 'system.SystemDict/deleteTypes');

Route::get('system/dict/items', 'system.SystemDict/items');
Route::post('system/dict/items', 'system.SystemDict/createItem');
Route::put('system/dict/items/:id', 'system.SystemDict/updateItem');
Route::delete('system/dict/items/:id', 'system.SystemDict/deleteItem');
Route::delete('system/dict/items', 'system.SystemDict/deleteItems');

Route::post('system/dict/batch', 'system.SystemDict/batch');
Route::get('system/dict/:code/options', 'system.SystemDict/options')->pattern(['code' => '[A-Za-z][A-Za-z0-9_]{0,59}']);
