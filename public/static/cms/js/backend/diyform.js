define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'backend.Diyform/index',
                    add_url: 'backend.Diyform/add',
                    edit_url: 'backend.Diyform/edit',
                    delete_url: 'backend.Diyform/delete',
                    modify_url: 'backend.Diyform/modify',
                    recycle_url: 'backend.Diyform/recycle',
                    destroy_url: 'backend.Diyform/destroy',
                    restore_url: 'backend.Diyform/restore',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: 'ID', width: 80,  sort: true},
                    {field: 'tablename', title: __('tablename'), width: 180, },
                    {field: 'name', title: __('Name'), minwidth: 150, },
                    {field: 'create_time', title: __('Createtime'), width: 180},
                    {field: 'fieldlist', title: __("Field"), width: 200,sort:true,
                        templet: function (d){
                            return  '<a class="layui-btn layui-btn-xs layui-bg-green" data-btn="false"  lay-event="open"  ' +
                                'data-url="backend.Field/index?diyformid='+ d.id +'">' +
                                '<i class="layui-icon layui-icon-list"></i>'+__('Fieldlist')+'</a>'+
                                '<a class="layui-btn layui-btn-xs layui-bg-blue" data-btn="false" lay-event="open"  ' +
                                'data-url="backend.Diyform/data?id='+ d.id +'">' +
                                '<i class="layui-icon layui-icon-template-1"></i>'+__('datalist')+'</a>'
                        }
                    },
                    {field: 'status',title: __("status"), width: 120,filter: 'status',sort:true,templet:Table.templet.switch},
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
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },

        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        data:function(){
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'backend.Diyform/data?id='+diyformid,
                    add_url: 'backend.Diyform/dataadd?diyformid='+diyformid,
                    edit_url: 'backend.Diyform/dataedit?diyformid='+diyformid,
                    delete_url: 'backend.Diyform/datadelete?diyformid='+diyformid,
                    modify_url: 'backend.Diyform/datamodify?diyformid='+diyformid,
                }
            }
            let cols = [
                {checkbox: true,},
                {field: 'id', name: 'ID', width: 80, sort: true},
            ];
            $.each(fieldList, function(k,v){
                let data = {field: v.field, title: __(v.name),sort: true};
                if (v.type === 'image') {
                    data.templet =  Table.api.templet.image;
                } else if (v.type === 'images') {
                    data.templet  = Table.api.templet.images;
                } else if (v.type === 'radio' || v.type === 'switch') {
                    data.templet  = Table.api.templet.switch;
                }else if(v.type ==='editor'){
                    data.templet  = Table.api.templet.content;
                }
                cols.push(data);
            })
            cols.push({field: 'status', filter: 'status',title: __('Status'), sort:true, templet: Table.templet.switch});
            cols.push({field: 'create_time',  title: __('Createtime'),sort:true,templet:Table.templet.time});
            cols.push({field: 'updatetime', title: __('Updatetime'),sort:true});
            cols.push( {
                width: 250, align: 'center', title: __('Operat'), init: Table.init,
                templet : Table.templet.operat, operat: ['edit','delete']
            });
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [cols],
                done: function(res){
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        dataadd:function(){
            $.get('getfield?diyformid='+diyformid+'&id='+id,function(res){
                $('.field').html(res);
                layui.form.render();
            });
            Controller.api.bindevent()

        },
        dataedit:function(){
            $.get('getfield?diyformid='+diyformid+'&id='+id,function(res){
                $('.field').html(res);
                layui.form.val("form", Config.formData);
                layui.form.render();
            });
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