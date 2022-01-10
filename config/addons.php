<?php

return array (
  'autoload' => true,
  'hooks' => 
  array (
    'AddonsInit' => 
    array (
      0 => 'mocms',
      1 => 'translate',
    ),
    'DemoHook' => 
    array (
      0 => 'mocms',
    ),
    'Translate' => 
    array (
      0 => 'translate',
    ),
  ),
  'route' => 
  array (
    0 => 
    array (
      'addons' => 'cms',
      'domain' => 'cms',
      'rule' => 
      array (
        '/' => 'cms/frontend/index/index',
        'download/[:id]' => 'cms/frontend/index/download',
        'diyform/[:diyid]' => 'cms/frontend/index/diyform',
        'lists/[:cateid]/[:flag]/[:page]' => 'cms/frontend/index/lists',
        'show/[:cateid]/[:id]' => 'cms/frontend/index/show',
        'search/[:keys]/[:flag]/[:page]' => 'cms/frontend/index/search',
        'error/[:message]' => 'cms/frontend/error/err',
        'notice/[:message]' => 'cms/frontend/error/notice',
        'login' => 'cms/frontend/member/login',
        'register' => 'cms/frontend/member/register',
        'reset' => 'cms/frontend/member/reset',
      ),
    ),
    1 => 
    array (
      'addons' => 'mocms',
      'domain' => 'mocms,testzh,testzh,testzhja',
      'rule' => 
      array (
        '/' => 'mocms/frontend/index/index',
        'download/[:id]' => 'mocms/frontend/index/download',
        'diyform/[:cateid]/[:diyid]' => 'mocms/frontend/index/diyform',
        'lists/[:cateid]/[:flag]/[:page]' => 'mocms/frontend/index/lists',
        'topic/[:cateid]/[:id]/[:page]' => 'mocms/frontend/index/topic',
        'show/[:cateid]/[:id]' => 'mocms/frontend/index/show',
        'search/[:keys]/[:flag]/[:page]' => 'mocms/frontend/index/search',
        'error/[:message]' => 'mocms/frontend/error/err',
        'notice/[:message]' => 'mocms/frontend/error/notice',
        'login' => 'mocms/frontend/member/login',
        'register' => 'mocms/frontend/member/register',
        'reset' => 'mocms/frontend/member/reset',
      ),
    ),
  ),
  'service' => 
  array (
  ),
);