<?php

use think\facade\Route;

Route::get('auth/csrf', 'AdminAuth/csrf');
Route::get('auth/captcha', 'AdminAuth/captcha');
Route::post('auth/login', 'AdminAuth/login');
Route::get('auth/me', 'AdminAuth/me');
Route::get('auth/menus', 'AdminAuth/menus');
Route::post('auth/logout', 'AdminAuth/logout');

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
