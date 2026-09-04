<?php

use think\facade\Route;

Route::get('auth/csrf', 'AdminAuth/csrf');
Route::get('auth/captcha', 'AdminAuth/captcha');
Route::post('auth/login', 'AdminAuth/login');
Route::get('auth/me', 'AdminAuth/me');
Route::get('auth/menus', 'AdminAuth/menus');
Route::post('auth/logout', 'AdminAuth/logout');

Route::get('profile', 'AdminProfile/index');
Route::put('profile', 'AdminProfile/update');
Route::post('profile/password', 'AdminProfile/password');

Route::post('upload', 'AdminUpload/upload');

Route::get('system/role/permission-tree', 'SystemRole/permissionTree');
Route::get('system/role/all', 'SystemRole/all');
Route::get('system/role/parent-options', 'SystemRole/parentOptions');
Route::get('system/role', 'SystemRole/index');
Route::get('system/role/:id', 'SystemRole/detail')->pattern(['id' => '\d+']);
Route::post('system/role', 'SystemRole/create');
Route::put('system/role/:id', 'SystemRole/update')->pattern(['id' => '\d+']);
Route::delete('system/role/:id', 'SystemRole/delete')->pattern(['id' => '\d+']);
Route::delete('system/role', 'SystemRole/delete');
Route::post('system/role/:id/permissions', 'SystemRole/permissions')->pattern(['id' => '\d+']);

Route::get('system/dept/tree', 'SystemDepartment/tree');
Route::get('system/dept/:id', 'SystemDepartment/detail')->pattern(['id' => '\d+']);
Route::post('system/dept', 'SystemDepartment/create');
Route::put('system/dept/:id', 'SystemDepartment/update')->pattern(['id' => '\d+']);
Route::delete('system/dept/:id', 'SystemDepartment/delete')->pattern(['id' => '\d+']);
Route::delete('system/dept', 'SystemDepartment/delete');

Route::get('system/user', 'SystemAdmin/index');
Route::get('system/user/:id', 'SystemAdmin/detail')->pattern(['id' => '\d+']);
Route::post('system/user', 'SystemAdmin/create');
Route::put('system/user/:id', 'SystemAdmin/update')->pattern(['id' => '\d+']);
Route::delete('system/user/:id', 'SystemAdmin/delete')->pattern(['id' => '\d+']);
Route::delete('system/user', 'SystemAdmin/delete');
Route::post('system/user/:id/reset-password', 'SystemAdmin/resetPassword')->pattern(['id' => '\d+']);
Route::post('system/user/:id/status', 'SystemAdmin/status')->pattern(['id' => '\d+']);

Route::get('system/menu/tree', 'SystemMenu/tree');
Route::get('system/menu/:id', 'SystemMenu/detail')->pattern(['id' => '\d+']);
Route::post('system/menu', 'SystemMenu/create');
Route::put('system/menu/:id', 'SystemMenu/update')->pattern(['id' => '\d+']);
Route::delete('system/menu/:id', 'SystemMenu/delete')->pattern(['id' => '\d+']);
Route::delete('system/menu', 'SystemMenu/delete');


Route::get('system/permission/tree', 'SystemPermission/tree');
Route::get('system/permission/:id', 'SystemPermission/detail')->pattern(['id' => '\d+']);
Route::post('system/permission', 'SystemPermission/create');
Route::put('system/permission/:id', 'SystemPermission/update')->pattern(['id' => '\d+']);
Route::delete('system/permission/:id', 'SystemPermission/delete')->pattern(['id' => '\d+']);
Route::delete('system/permission', 'SystemPermission/delete');

Route::get('system/blacklist', 'SystemBlacklist/index');
Route::get('system/blacklist/export', 'SystemBlacklist/export');
Route::get('system/blacklist/:id', 'SystemBlacklist/detail')->pattern(['id' => '\d+']);
Route::post('system/blacklist', 'SystemBlacklist/create');
Route::put('system/blacklist/:id', 'SystemBlacklist/update')->pattern(['id' => '\d+']);
Route::post('system/blacklist/:id/status', 'SystemBlacklist/status')->pattern(['id' => '\d+']);
Route::delete('system/blacklist', 'SystemBlacklist/delete');
Route::post('system/blacklist/restore', 'SystemBlacklist/restore');
Route::delete('system/blacklist/destroy', 'SystemBlacklist/destroy');
Route::post('system/blacklist/import', 'SystemBlacklist/import');

Route::get('system/language', 'SystemLanguage/index');
Route::get('system/language/:id', 'SystemLanguage/detail')->pattern(['id' => '\d+']);
Route::post('system/language', 'SystemLanguage/create');
Route::put('system/language/:id', 'SystemLanguage/update')->pattern(['id' => '\d+']);
Route::delete('system/language/:id', 'SystemLanguage/delete')->pattern(['id' => '\d+']);
Route::delete('system/language', 'SystemLanguage/delete');

Route::get('system/member-group', 'SystemMemberGroup/index');
Route::get('system/member-group/export', 'SystemMemberGroup/export');
Route::get('system/member-group/:id', 'SystemMemberGroup/detail')->pattern(['id' => '\d+']);
Route::post('system/member-group', 'SystemMemberGroup/create');
Route::put('system/member-group/:id', 'SystemMemberGroup/update')->pattern(['id' => '\d+']);
Route::post('system/member-group/:id/status', 'SystemMemberGroup/status')->pattern(['id' => '\d+']);
Route::delete('system/member-group', 'SystemMemberGroup/recycle');
Route::post('system/member-group/restore', 'SystemMemberGroup/restore');
Route::delete('system/member-group/destroy', 'SystemMemberGroup/destroy');

Route::get('system/log/operation', 'SystemOperationLog/index');
Route::get('system/log/operation/:id', 'SystemOperationLog/detail')->pattern(['id' => '\d+']);
Route::delete('system/log/operation/:id', 'SystemOperationLog/delete')->pattern(['id' => '\d+']);
Route::delete('system/log/operation', 'SystemOperationLog/delete');

Route::get('system/dict/types', 'SystemDict/types');
Route::post('system/dict/types', 'SystemDict/createType');
Route::put('system/dict/types/:id', 'SystemDict/updateType');
Route::delete('system/dict/types/:id', 'SystemDict/deleteType');
Route::delete('system/dict/types', 'SystemDict/deleteTypes');

Route::get('system/dict/items', 'SystemDict/items');
Route::post('system/dict/items', 'SystemDict/createItem');
Route::put('system/dict/items/:id', 'SystemDict/updateItem');
Route::delete('system/dict/items/:id', 'SystemDict/deleteItem');
Route::delete('system/dict/items', 'SystemDict/deleteItems');

Route::post('system/dict/batch', 'SystemDict/batch');
Route::get('system/dict/:code/options', 'SystemDict/options')->pattern(['code' => '[A-Za-z][A-Za-z0-9_]{0,59}']);
