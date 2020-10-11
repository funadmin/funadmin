define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/database/backend/index/index',
                    repair_url:{
                        type: 'repair',
                        class: 'layui-btn-sm layui-btn-danger',
                        icon:'',
                        url: 'addons/database/backend/index/repair',
                        text: __('Repaire'),
                        title: __('Repaire'),
                    },
                    backup_url:{
                        type: 'backup',
                        class: 'layui-btn-sm  layui-btn-normal',
                        icon:'',
                        url: 'addons/database/backend/index/backup',
                        text: __('Backup'),
                        title: __('Backup'),
                    },
                    optimize_url:{
                        type: 'optimize',
                        class: 'layui-btn-sm layui-btn-green',
                        icon:'',
                        url: 'addons/database/backend/index/optimize',
                        text: __('Optimize'),
                        title: __('Optimize'),
                    },
                    query_url:{
                        type: 'open',
                        class: 'layui-btn-sm layui-btn-danger',
                        icon:'',
                        url: 'addons/database/backend/index/querysql',
                        text: __('Sql'),
                        title: __('Sql'),
                    },

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','repair_url','backup_url','optimize_url','query_url'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'name', title: __('TableName'), minwidth: 150, fixed: true, sort: true},
                    {field: 'rows', title: __('Rows'), width: 150, sort: true},
                    {field: 'size', title: __('Size'), width: 150, templet: '#size', sort: true},
                    {field: 'engine', title: __('Engine'), width: 110, sort: true},
                    {field: 'collation', title: __('Coding'), minwidth: 150, sort: true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'comment', title: __('Description'), minwidth: 180},
                    // {
                    //     width: 250, align: 'center', title: __('Operat'), init: Table.init,
                    //     templet : Table.templet.operat, operat: ['optimize_url','repair_url']
                    // }
                ]],
                done: function(res){
                    $('.count').html(res.tableNum);
                    $('.size').html(res.total);
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            layui.table.on('toolbar(list)', function(obj) {
                var _that = $(this);
                var title = _that.html()
                var event = obj.event;
                if(event==='open'){
                    Table.events.open(_that);
                    return false;
                }
                if(event==='refresh'){
                    Table.api.reload();
                    return false;
                }
                var checkStatus = layui.table.checkStatus(Table.init.table_elem); //test即为参数id设定的值
                var tables = [];
                $(checkStatus.data).each(function(i,o){
                    tables.push(o.name);
                });
                if(tables.length===0){
                    return Fun.msg.error(__('Please choose data'));
                }
                _that.html(title+__(' processing...'));

                _that.addClass('layui-btn-disabled');
                var url = Fun.url(eval('Table.init.requests.'+event+'_url.url'))
                Fun.ajax({url:url,data:{tables:tables}},function(res){
                    Fun.msg.success(res.msg);
                    _that.html(title);
                    _that.removeClass('layui-btn-disabled');
                    Table.api.reload();

                },function (res){
                    Fun.msg.error(res.msg);
                });
            })
        },

        restore:function (){
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/database/backend/index/restore',
                    delete_url:'addons/database/backend/index/delete',
                    download_url:{
                        type: 'href',
                        class: 'layui-btn-xs layui-btn-danger',
                        icon:'',
                        url: 'addons/database/backend/index/download',
                        text: __('Download'),
                        title: __('Download'),
                    },
                    recover_url:{
                        type: 'recover',
                        class: 'layui-btn-xs layui-btn-normal',
                        icon:'',
                        url: 'addons/database/backend/index/recover',
                        text: __('Recover'),
                        title: __('Recover'),
                    },
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh'],
                cols: [[
                    {field:'name', title: __('name'), minwidth:250},
                    {field:'size', title: __('Size'), width:200,sort:true},
                    {field:'time', title: __('Backuptime'), width:200,sort:true,templet: '#time'},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['download_url','recover_url','delete']
                    }
                ]],
                done: function(res){
                    $('.count').html(res.tableNum);
                    $('.size').html(res.total);
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            layui.table.on('tool('+Table.init.table_elem+')', function(obj) {
                var data = obj.data;
                var othis = $(this)
                var url = Fun.url(othis.attr('lay-url'));
                url = url+"&time="+data.time;
                if (obj.event === 'href') {
                    window.location.href = url+'&time='+data.time
                    return ;
                }else if (obj.event === 'recover') {
                    title = __('Are you sure you want to recover it')
                }else if(obj.event === 'request'){
                    title = __('Are you sure you want to delete it')
                }
                Fun.msg.confirm(title,function () {
                    loading = layer.load(1, {shade: [0.1, '#fff']});
                    Fun.ajax({url:url,method:'get'},function (res){
                        Fun.msg.success(res.msg);
                        Table.api.reload()
                    },function (res){
                        Fun.msg.error(res.msg);
                        Table.api.reload()
                    });
                });
            });
        },
        querysql:function (){

        },

    };
    return Controller;
});