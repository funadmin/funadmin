<?php
namespace app\api\controller\v1;

use fun\auth\Api;
use think\App;
use think\facade\Request;
use think\facade\Config;

/**
 * 生成token
 */
class Token extends Api
{
    protected $noAuth = ['*'];

    public function __construct(App $app)
    {
        parent::__construct($app);
        //跨域
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:Accept,Referer,Host,Keep-Alive,User-Agent,X-Requested-With,Cache-Control,Content-Type,Cookie,token');
        header('Access-Control-Allow-Credentials:true');
        header('Access-Control-Allow-Methods:GET, POST, PATCH, PUT, DELETE,OPTIONS');

    }

    public function build(Request $request)
    {
        $class = ucwords('\\fun\\auth\\'.ucfirst($this->type).'Token');
        $token = $class::instance();
        $token->build();

    }
    public function refresh(Request $request)
    {
        $class = ucwords('\\fun\\auth\\'.ucfirst($this->type).'Token');
        $token = $class::instance();
        $token->refresh();

    }

}