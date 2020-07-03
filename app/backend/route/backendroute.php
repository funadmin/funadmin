<?php
use think\facade\Route;
Route::post('login/login','login/login')->token('__token__');
