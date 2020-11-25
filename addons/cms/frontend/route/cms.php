<?php
use think\facade\Route;
Route::rule('lists/:cateid/:flag/[:condition]', 'index/lists')->pattern(['cateid' => '\d+','flag'=>'[0-9_&=a-zA-Z]+', 'condition' => '[0-9_&=a-zA-Z]+']);
Route::rule('page/:cateid/:id', 'index/page')->pattern(['cateid' => '\d+', 'id' => '\d+']);
Route::rule('tags/:id', 'index/tags')->pattern(['id' => '\d+', 'id' => '\d+']);
