define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsadv/index',
                    add_url: 'addons/cms/backend/cmsadv/add',
                    edit_url: 'addons/cms/backend/cmsadv/edit',
                    delete_url: 'addons/cms/backend/cmsadv/delete',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [[
                    {checkbox: true, fixed: true},

                    {field: 'name', title: __('Name'), minwidth: 150, fixed: true, sort: true},
                    {field: 'cmsPos.name', title: __('PositionName'), width: 150,templet: Table.templet.resolution, sort: true},
                    {field: 'image', title: __('Image'), width: 110, templet: Table.templet.image,sort: true},
                    {field: 'type', title: __('Type'), width: 110, sort: true},
                    {field: 'url', title: __('Url'), width: 110, sort: true},
                    {field: 'status', title: __('status'), minwidth: 150, sort: true},
                    {field: 'start_time', title: __('Starttime'), width: 180,templet: Table.templet.time, sort: true,},
                    {field: 'end_time', title: __('Endtime'), width: 180,templet: Table.templet.time ,sort: true,},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['edit','delete']
                    }
                ]],
                done: function(res){

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
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});