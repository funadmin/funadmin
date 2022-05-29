<?php /*a:2:{s:68:"D:\wwwroot\my-space\funadmin\addons\curd\view\backend\index\add.html";i:1653747457;s:36:"../app/backend/view/layout/main.html";i:1650116882;}*/ ?>
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

<style>
    body {
        padding:30px!important;
    }
    .layui-form-label {
    float: none;
    padding: 0;
    display: inline-block;
    width: auto;
    text-align: left;margin-left: 5px;
}

.layui-form-item .layui-input-block {
    margin: 0 auto;
}

.layui-form-item .required::after {
    content: "*";
    color: red;
    position: absolute;
    margin-left: 4px;
    font-weight: bold;
    line-height: 0.8em;
    top: 6px;
    right: -8px;
}

.layui-elem-quote {
    line-height: .6;
    border-left: 5px solid #772c6a;
    color: #772c6a;
    padding: 10px;
}

.block {
    display: -webkit-box;
    display: flow-root;
}
</style>
<div class="layui-tab">
    <ul class="layui-tab-title">
        <li class="layui-this">CURD</li>
        <li>菜单</li>
    </ul>
    <div class="layui-tab-content">
        <div class="layui-tab-item layui-show">
            <div class="layui-row">
                <form class="layui-form" lay-filter="form">
                    <blockquote class="layui-elem-quote"><?php echo lang('Base'); ?></blockquote>
                    <div class="block">

                        <div class="layui-col-sm6">
                        <?php echo form_select('module',
                        ['backend'=>'backend','common'=>'common'],
                        ['verify'=>'required','label'=>'module', 'tips'=>'default backend']
                        ,[],'backend'); ?>
                        </div>
                        <div class="layui-col-sm6">
                            <?php echo form_select('driver',
                            $driver, ['verify'=>'required','filter'=>'driver','label'=>'driver', 'tips'=>'default mysql']
                            ,[],'mysql'); ?>
                        </div>
                    </div>
                    <blockquote class="layui-elem-quote"><?php echo lang('MainTable'); ?></blockquote>
                    <div class="block">
                        <div class="layui-col-sm3">
                            <?php echo form_select('table',$table,['verify'=>'required','filter'=>'table','class'=>'table','tips'=>'MainTable','label'=>"MainTable"]); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_input('controller','text',['label'=>"Controller",'tips'=>'default tableName']); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_input('model','text',['label'=>"Modelname", 'tips'=>'default tableName']); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_select('fields',[],['class'=>' fields','label'=>"Fields",'multiple'=>1, 'tips'=>'visible field']); ?>
                        </div>
                    </div>
                    <blockquote class="layui-elem-quote">
                        <input type="button" value="<?php echo lang('addRel'); ?>" class="addRelation layui-btn layui-btn-xs layui-bg-blue">
                    </blockquote>
                    <div class="block">
                        <div style="display:block">

                            <table class="layui-table">
                                <colgroup>
                                    <col class="layui-col-xs-2">
                                    <col class="layui-col-xs-2">
                                    <col class="layui-col-xs-2">
                                    <col class="layui-col-xs-2">
                                    <col class="layui-col-xs-2">
                                    <col class="layui-col-xs-2">
                                </colgroup>
                                <thead>
                                <tr>
                                    <th class="required"><?php echo lang('relTable'); ?></th>
                                    <th class="required"><?php echo lang('relType'); ?></th>
                                    <th class="required"><?php echo lang('selectField'); ?></th>
                                    <th class="required"><?php echo lang('relForField'); ?></th>
                                    <th class="required"><?php echo lang('relPriField'); ?></th>
                                    <th class="required"><?php echo lang('oprate'); ?></th>
                                </tr>
                                </thead>
                                <tbody id="relTab">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="margin-top:20px;">

                    </div>
                    <blockquote class="layui-elem-quote"><?php echo lang('Other Setting'); ?></blockquote>
                    <div class="block">
                        <div class="layui-col-sm3">
                            <?php echo form_input('addon','text',['label'=>"addons name",'tips'=>'addons directory']); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_input('ignoreFields','text',['label'=>"ignoreFields",'tips'=>'Ignore fields separated by commas']); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_select('method',
                            ['index'=>'index',
                            'add'=>'add',
                            'edit'=>'edit',
                            'destroy'=>'destroy',
                            'delete'=>'delete',
                            'deleteAll'=>'deleteAll',
                            'import'=>'import',
                            'export'=>'export',
                            'recycle'=>'recycle',
                            'restore'=>'restore',
                            ],
                            ['label'=>'method','multiple'=>1,'search'=>1,
                            'tips'=>'不选代表默认全部']); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_radio('menu',['no','yes'],['verify'=>'','label'=>'make menu','tips'=>'make menu'],1); ?>
                        </div>
                    </div>
                    <div class="block">
                        <div class="layui-col-sm3">
                            <?php echo form_input('limit','text',['verify'=>'','label'=>"pageSize"],15); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_radio('page',['no','yes'],['verify'=>'','label'=>'isPage'],1); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_radio('force',['no','yes'],['filter'=>'force','label'=>'force mode'],0); ?>
                        </div>
                        <div class="layui-col-sm3">
                            <?php echo form_radio('delete',['no','yes'],['filter'=>'delete','label'=>'delete mode'],0); ?>
                        </div>
                    </div>
                    <input type="hidden" value="1" name="type">
                    <?php echo form_submitbtn(true,['show'=>1]); ?>
                </form>
            </div>
        </div>
        <div class="layui-tab-item">
            <div class="layui-row">
                <form class="layui-form" lay-filter="form">
                <div class="block">
                    <?php echo form_select('controllers',
                    $controllerList,
                    ['verify'=>'required','label'=>'controller', 'tips'=>'controller']
                    ,[],'backend'); ?>
                </div>
                    <div class="layui-col-sm3">
                        <?php echo form_radio('force',['no','yes'],['filter'=>'force','label'=>'force mode'],0); ?>
                    </div>
                    <div class="layui-col-sm3">
                        <?php echo form_radio('delete',['no','yes'],['filter'=>'delete','label'=>'delete mode'],0); ?>
                    </div>
                    <input type="hidden" value="2" name="type">
                <?php echo form_submitbtn(true,['show'=>1]); ?>
                </form>
            </div>
    </div>
</div>


<script>
    var list = <?php echo json_encode($list); ?>;
</script>
<script type="text/html" id="tpl">
    <tr id="relTab-{{d.index}}">
        <td class="" >
            <select name="joinTable[{{d.index}}]"
                    id="joinTable-{{d.index}}"  lay-verify="required" lay-filter='jointable'
                    class="layui-select jointable" lay-search>
                <option value=""><?php echo lang('Select'); ?></option>
                {{#  layui.each(d.table, function(index, item){ }}
                <option value="{{d.table[index]}}">{{d.table[index]}}</option>
                {{# }) }}
            </select>
        </td>
        <td class="">
            <select name="joinMethod[{{d.index}}]"
                    id="joinMethod-{{d.index}}"  lay-verify="required"
                    class="layui-select " lay-search>
                <option value="hasOne">hasOne</option>
                <option value="belongsTo">belongsTo</option>
            </select>
        </td>
        <td class="">
            <select name="selectFields[{{d.index}}]"
                    id="selectFields-{{d.index}}"  lay-verify="required"
                    class="layui-select selectfields" lay-search>
                    <option value="title">title</option>
                    <option value="name">name</option>
            </select>
        </td>
        <td class="">
            <select name="joinForeignKey[{{d.index}}]"
                    id="joinForeignKey-{{d.index}}"  lay-verify="required" value="title"
                    class="layui-select joinforeignkey" lay-search>
            </select>
        </td>
        <td class="">
            <select name="joinPrimaryKey[{{d.index}}]"
                    id="joinPrimaryKey-{{d.index}}"  lay-verify="required"
                    class="layui-select joinprimarykey" lay-search>

            </select>
        </td>

        <td class="">
            <button type="button" id="relTab-delete-{{d.index}}"  class="layui-btn layui-btn-sm layui-btn-danger">
                <i class="layui-icon"></i>
            </button>
        </td>
    </tr>
</script>

</div>
</body>
</html>
<!--[if lt IE 9]>
<script src="https://cdn.staticfile.org/html5shiv/r29/html5.min.js"></script>
<script src="https://cdn.staticfile.org/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

<script defer src="/static/require.min.js?v=<?php echo syscfg('site','site_version'); ?>" data-main="/static/js/require-backend<?php echo syscfg('site','app_debug')?'':'.min'; ?>.js?v=<?php echo syscfg('site','app_debug')?time():syscfg('site','site_version'); ?>" charset="utf-8"></script>
