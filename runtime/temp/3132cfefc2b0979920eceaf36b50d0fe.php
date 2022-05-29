<?php /*a:3:{s:62:"D:\wwwroot\my-space\funadmin\app\backend\view\addon\index.html";i:1651927097;s:62:"D:\wwwroot\my-space\funadmin\app\backend\view\layout\main.html";i:1650116882;s:66:"D:\wwwroot\my-space\funadmin\app\backend\view\layout\logintpl.html";i:1650024132;}*/ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo syscfg('site','sys_name'); ?>后台管理</title>
    <meta property="og:keywords" content="<?php echo syscfg('site','site_seo_keywords'); ?>" />
    <meta property="og:description" content="<?php echo syscfg('site','site_seo_desc'); ?>" />
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="referrer" content="never">
    <meta name="format-detection" content="telephone=no">
    <link rel="stylesheet" href="/static/plugins/layui/css/layui.css" media="all" />
    <link rel="stylesheet" href="/static/backend/css/comm.css" media="all">
    <link rel="stylesheet" href="/static/backend/css/global.css" media="all" />
    <script src="/static/plugins/jquery/jquery-3.6.0.min.js"></script>
    <script src="/static/plugins/layui/layui.js" charset="utf-8"></script>
    <?php echo token_meta(); ?>
</head>
<script>
    window.Config = <?php echo json_encode($config); ?>;
    window.Config.formData = <?php echo isset($formData)?(json_encode($formData)):'""'; ?>,
    window.STATIC ='/static'
    window.ADDONS = '/static/addons'
    window.PLUGINS = '/static/plugins';
</script>
<body style="padding: 10px;background: #fff">

<div class="fun-container" id="app" style="">

<div class="layui-tab layui-tab-card" lay-filter="tab">
    <ul class="layui-tab-title" data-field="cateid">
        <li class="layui-this" data-value="" lay-event="tabswitch">全部</li>
        <?php if(is_array($cateList) || $cateList instanceof \think\Collection || $cateList instanceof \think\Paginator): $i = 0; $__LIST__ = $cateList;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
        <li data-value="<?php echo htmlentities($vo['id']); ?>" lay-event="tabswitch"><?php echo htmlentities($vo['title']); ?></li>
        <?php endforeach; endif; else: echo "" ;endif; ?>
    </ul>
</div>
<table class="layui-table" id="list" lay-filter="list"
      data-node-add="<?php echo auth(__u('add')); ?>"
      data-node-edit="<?php echo auth(__u('edit')); ?>"
      data-node-delete="<?php echo auth(__u('delete')); ?>"
      data-node-destroy="<?php echo auth(__u('destroy')); ?>"
      data-node-modify="<?php echo auth(__u('modify')); ?>"
      data-node-recycle="<?php echo auth(__u('recycle')); ?>"
      data-node-restore="<?php echo auth(__u('restore')); ?>"
      data-node-import="<?php echo auth(__u('import')); ?>"
      data-node-export="<?php echo auth(__u('export')); ?>"
      data-node-install="<?php echo auth(__u('install')); ?>"
      data-node-localinstall="<?php echo auth(__u('localinstall')); ?>"
      data-node-uninstall="<?php echo auth(__u('uninstall')); ?>"
      data-node-config="<?php echo auth(__u('config')); ?>"
>
</table>
<!--<table  id="list" lay-filter="list"></table>-->
<!--登陆页面-->
<script type="text/html" id="login_tpl">
  <style>
    .layui-form-label {
      padding: 9px 0px;
      text-align:center;
    }
    .layui-form-item .required::after{
      position:unset;
    }
    .layui-card-header{
      text-align: left;margin-top: 10px;
    }
    .layui-elem-quote{
      padding: 5px;background: #409EFF ;
      border-left: 5px solid #409EFF;
      color: #fff;
    }
  </style>
  <div>
    <div class="layui-card">
      <div class="layui-card-header" style="">
        <blockquote class="layui-elem-quote" style="">温馨提示
          <br>
          此处账号为: <a class="layui-font-red" target="_blank" href="http://www.FunAdmin.com">FunAdmin云平台账号</a>
        </blockquote>
      </div>
      <br>
      <div class="layui-card-body">
        <form class="layui-form" action="">
          <div class="layui-form-item">
            <label class="layui-form-label required">账号<i class="fa fa-user"></i></label>
            <div class="layui-input-block">
              <input type="text" class="layui-input"  lay-verify="required" id="inputUsername" value=""
                     placeholder="<?php echo lang('username or email'); ?>">
            </div>
          </div>
          <div class="layui-form-item">
            <label class="layui-form-label required">密码<i class="fa fa-lock"></i></label>
            <div class="layui-input-block">
              <input type="password" class="layui-input"  lay-verify="required" id="inputPassword" value=""
                     placeholder="<?php echo lang('password'); ?>">
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</script>
<button type="button" class="layui-btn layui-btn-sm" id="importFile" value="离线安装" style="display: none"/>
<script>
    var auth = <?php echo htmlentities($auth); ?>;
</script>



</div>
</body>
</html>
<!--[if lt IE 9]>
<script src="https://cdn.staticfile.org/html5shiv/r29/html5.min.js"></script>
<script src="https://cdn.staticfile.org/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

<script defer src="/static/require.min.js?v=<?php echo syscfg('site','site_version'); ?>" data-main="/static/js/require-backend<?php echo syscfg('site','app_debug')?'':'.min'; ?>.js?v=<?php echo syscfg('site','app_debug')?time():syscfg('site','site_version'); ?>" charset="utf-8"></script>
