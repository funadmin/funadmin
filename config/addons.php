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
      'addons' => 'blog',
      'domain' => 'blog',
      'rule' => 
      array (
        '/' => 'blog/frontend/index/index',
        'diyform/[:diyid]' => 'blog/frontend/index/diyform',
        'lists/[:cateid]/[:flag]/[:page]' => 'blog/frontend/index/lists',
        'show/[:cateid]/[:id]' => 'blog/frontend/index/show',
        'search/[:keys]/[:flag]/[:page]' => 'blog/frontend/index/search',
        'error/[:message]' => 'blog/frontend/error/err',
        'notice/[:message]' => 'blog/frontend/error/notice',
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