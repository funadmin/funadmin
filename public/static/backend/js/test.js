define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url:'test/index',
add_url:'test/add',
edit_url:'test/edit',
delete_url:'test/delete',
deleteAll_url:'test/deleteAll',
import_url:'test/import',
export_url:'test/export',
modify_url:'test/modify',

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true,},
                  { field: 'id', title: __('ID'), sort:true,},
                  {field:'cate_id', title: __('CateId'),align: 'center',sort:'sort'},
                  {field:'cate_ids', title: __('CateIds'),align: 'center',sort:'sort'},
                  {field: 'week',search: 'select',title: __('Week'),selectList:{'monday':'Week monday','tuesday':'Week tuesday','wednesday':'Week wednesday',},sort:true,templet: Table.templet.switch},
                  {field: 'sexdata',search: 'select',title: __('Sexdata'),selectList:{'male':'Sexdata male','female':'Sexdata female','secret':'Sexdata secret',},sort:true,templet: Table.templet.switch},
                  {field:'textarea', title: __('Textarea'),align: 'center',sort:'sort'},
                  {field: 'image',title: __('Image'),sort:true,templet: Table.templet.image},
                  {field:'images', title: __('Images'),align: 'center',sort:'sort'},
                  {field: 'attach_file',title: __('AttachFile'),sort:true,templet: Table.templet.image},
                  {field:'attach_files', title: __('AttachFiles'),align: 'center',sort:'sort'},
                  {field:'keywords', title: __('Keywords'),align: 'center',sort:'sort'},
                  {field:'price', title: __('Price'),align: 'center',sort:'sort'},
                  {field:'startdate', title: __('Startdate'),align: 'center',sort:'sort'},
                  {field:'activitytime', title: __('Activitytime'),align: 'center',sort:'sort'},
                  {field:'timestaptime', title: __('Timestaptime'),align: 'center',sort:'sort'},
                  {field:'year', title: __('Year'),align: 'center',sort:'sort'},
                  {field:'times', title: __('Times'),align: 'center',sort:'sort'},
                  {field: 'switch',search: 'select',title: __('Switch'), filter: 'switch',  selectList: {'0':'Switch 0','1':'Switch 1',},sort:true,templet: Table.templet.switch},
                  {field: 'open_switch',search: 'select',title: __('OpenSwitch'), filter: 'open_switch',  selectList: {'0':'OpenSwitch 0','1':'OpenSwitch 1',},sort:true,templet: Table.templet.switch},
                  {field: 'teststate',search: 'select',title: __('Teststate'),selectList:{'1':'Teststate 1','2':'Teststate 2','3':'Teststate 3',},sort:true,templet: Table.templet.switch},
                  {field: 'test2state',search: 'select',title: __('Test2state'),selectList:{'0':'Test2state 0','1':'Test2state 1','2':'Test2state 2',},sort:true,templet: Table.templet.switch},
                  {field:'editor_content', title: __('EditorContent'),align: 'center',sort:'sort'},
                  {field:'description', title: __('Description'),align: 'center',sort:'sort'},
                  {field:'test_color', title: __('TestColor'),align: 'center',sort:'sort'},
                  {field: 'status',search: 'select',title: __('Status'),selectList:{0:__("enabled"),1:__("disabled")},sort:true,templet: Table.templet.switch},
                  {
                        minwidth: 250,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["edit", "destroy","delete"]
                    },
                ]],
                done: function(res){
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
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
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});