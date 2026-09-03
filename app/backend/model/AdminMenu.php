<?php

namespace app\backend\model;

/**
 * 后台菜单只负责导航展示，授权资源由 Permission 独立维护。
 */
class AdminMenu extends BackendModel
{
    protected $name = 'admin_menu';

}
