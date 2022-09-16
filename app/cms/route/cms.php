<?php

use think\facade\Route;


//Route::rule('lists/:cateid/:flag/[:condition]', 'index/lists')->pattern(['cateid' => '\d+', 'flag' => '[0-9_&=a-zA-Z]+', 'condition' => '[0-9_&=a-zA-Z]+']);
//Route::rule('page/:cateid/:id', 'index/page')->pattern(['cateid' => '\d+', 'id' => '\d+']);
//Route::rule('tags/:id', 'index/tags')->pattern(['id' => '\d+', 'id' => '\d+']);


Route::rule('/', 'cms/index/index');
Route::rule('download/[:id]', 'cms/index/download');
Route::rule('diyform/[:diyid]', 'cms/index/diyform');
Route::rule('lists/[:cateid]/[:flag]/[:page]', 'cms/index/lists');
Route::rule('show/[:cateid]/[:id]', 'cms/index/show');
Route::rule('search/[:keys]/[:flag]/[:page]', 'cms/index/search');
Route::rule('error/[:message]', 'cms/error/err');
Route::rule('notice/[:message]', 'cms/error/notice');
Route::rule('login', 'cms/member/login');
Route::rule('register', 'cms/member/register');
Route::rule('reset', 'cms/member/reset');