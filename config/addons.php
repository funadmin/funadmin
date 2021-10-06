<?php

return array (
  'autoload' => true,
  'hooks' => 
  array (
  ),
  'route' => 
  array (
    0 => 
    array (
      'addons' => 'cms',
      'domain' => 'cms',
      'rule' => 
      array (
        'cms' => 'cms/frontend/index/index',
        'cms/download/[:id]' => 'cms/frontend/index/download',
        'cms/diyform/[:diyid]' => 'cms/frontend/index/diyform',
        'cms/lists/[:cateid]/[:flag]/[:page]' => 'cms/frontend/index/lists',
        'cms/show/[:cateid]/[:id]' => 'cms/frontend/index/show',
        'cms/search/[:keys]/[:flag]/[:page]' => 'cms/frontend/index/search',
        'cms/error/[:message]' => 'cms/frontend/error/err',
        'cms/notice/[:message]' => 'cms/frontend/error/notice',
        'cms/login' => 'cms/frontend/member/login',
        'cms/register' => 'cms/frontend/member/register',
        'cms/reset' => 'cms/frontend/member/reset',
      ),
    ),
  ),
  'service' => 
  array (
  ),
);