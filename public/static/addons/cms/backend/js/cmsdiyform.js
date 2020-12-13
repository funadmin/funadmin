define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsdiyform/index',
                    add_url: 'addons/cms/backend/cmsdiyform/add',
                    edit_url: 'addons/cms/backend/cmsdiyform/edit',
                    delete_url: 'addons/cms/backend/cmsdiyform/delete',
                    modify_url: 'addons/cms/backend/cmsdiyform/modify',
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
                            return  '<a class="layui-btn layui-btn-xs layui-bg-green" lay-btn="false"  lay-event="open"  lay-url="addons/cms/backend/cmsfield/index?diyformid='+ d.id +'"><i class="layui-icon layui-icon-list"></i>'+__('Filelist')+'</a>'+
                           '<a class="layui-btn layui-btn-xs layui-bg-blue" lay-btn="false" lay-event="open"  lay-url="addons/cms/backend/cmsdiyform/data?id='+ d.id +'"><i class="layui-icon layui-icon-template-1"></i>'+__('datalist')+'</a>'
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
                    index_url: 'addons/cms/backend/cmsdiyform/data?id='+id,
                    add_url: 'addons/cms/backend/cmsdiyform/dataadd',
                    edit_url: 'addons/cms/backend/cmsdiyform/dataedit',
                    delete_url: 'addons/cms/backend/cmsdiyform/datadelete',
                    modify_url: 'addons/cms/backend/cmsdiyform/datamodify',
                }
            }
            let cols = [
                {checkbox: true,},
                {field: 'id', title: 'ID', width: 80, sort: true},

            ];
            $.each(fieldlist, function(k,v){
                let data = {field: v.field, title: __(v.title),sort: true};
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

            console.log(cols)
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
            Controller.api.bindevent()
        },
        dataedit:function(){
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