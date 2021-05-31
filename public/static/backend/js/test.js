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
                  {field:'cate_id', title: __('分类ID'),align: 'center',sort:'sort'},
                  {field:'cate_ids', title: __('分类IDS'),align: 'center',sort:'sort'},
                  {field: 'week',search: 'select',selectList:{'monday':'星期一','tuesday':'星期二','wednesday':'星期三',},title: __('星期'),sort:true,templet: Table.templet.switch},
                  {field: 'sexdata',search: 'select',selectList:{'male':'男','female':'女','secret':'保密',},title: __('性别'),sort:true,templet: Table.templet.switch},
                  {field:'textarea', title: __('内容'),align: 'center',sort:'sort'},
                  {field: 'image',title: __('图片'),sort:true,templet: Table.templet.image},
                  {field:'images', title: __('图片集合'),align: 'center',sort:'sort'},
                  {field: 'attach_file',title: __('附件'),sort:true,templet: Table.templet.image},
                  {field:'attach_files', title: __('附件'),align: 'center',sort:'sort'},
                  {field:'keywords', title: __('关键字'),align: 'center',sort:'sort'},
                  {field:'price', title: __('价格'),align: 'center',sort:'sort'},
                  {field:'startdate', title: __('开始日期'),align: 'center',sort:'sort'},
                  {field:'activitytime', title: __('活动时间'),align: 'center',sort:'sort'},
                  {field:'timestaptime', title: __('时间戳'),align: 'center',sort:'sort'},
                  {field:'year', title: __('年'),align: 'center',sort:'sort'},
                  {field:'times', title: __('时间'),align: 'center',sort:'sort'},
                  {field: 'switch',search: 'select',  selectList: {'0':'下架','1':'正常',},title: __('上架状态'),sort:true,templet: Table.templet.switch},
                  {field: 'open_switch',search: 'select',  selectList: {'0':'OFF','1':'ON',},title: __('开关'),sort:true,templet: Table.templet.switch},
                  {field: 'teststate',search: 'select',selectList:{'1':'选项1','2':'选项2','3':'选项3',},title: __('复选'),sort:true,templet: Table.templet.switch},
                  {field: 'test2state',search: 'select',selectList:{'0':'唱歌','1':'跳舞','2':'游泳',},title: __('爱好'),sort:true,templet: Table.templet.switch},
                  {field:'editor_content', title: __('富文本'),align: 'center',sort:'sort'},
                  {field:'description', title: __('描述'),align: 'center',sort:'sort'},
                  {field:'test_color', title: __('颜色'),align: 'center',sort:'sort'},
                  {field: 'status',search: 'select',selectList:{0:__("enabled"),1:__("disabled")},title: __('状态'),sort:true,templet: Table.templet.switch},
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