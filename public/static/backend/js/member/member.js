define(['jquery','table','form'], function ($,Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            modify_url: 'member.member/modify',
            index_url: 'member.member/index',
            recycle_url: 'member.member/recycle',
            delete_url: 'member.member/delete',
            destroy_url: 'member.member/destroy',
            export_url: 'member.member/export',
            import_url: 'member.member/import',
            restore_url: 'member.member/restore',
            copy_url: 'member.member/copy',
            // add_url: 'member.member/add',
            // edit_url: 'member.member/edit',
            add_full:{
                type: 'open',
                class: 'layui-btn-sm layui-btn-green',
                url: 'member.member/add',
                icon: 'layui-icon layui-icon-add',
                text: __('Add'),
                title: __('Add'),
                // node:false,//不使用节点权限
                // full: 1,
                width:'800',
            },
            edit_url:{
                type: 'open',
                event: 'open',
                class: 'layui-btn-xs layui-btn-green',
                url: 'member.member/edit',
                icon: 'layui-icon layui-icon-edit',
                text: __('Edit'),
                title: __('Edit'),
                // full: 1,
                width:'800',
                extend:'',
                // btn:'close',
                // hidden:function(row){
                //     console.log(row)
                //     return true;
                // },
                // callback:function (data,row) {
                //     console.log(data);
                //     var res = {
                //         code:0,
                //         msg:"ok",
                //     }
                //     Fun.toastr.success("a'"+ res.msg +"'sa");
                // }
            },
            dropdown:{
                type: 'dropdown',
                event: 'dropdown',
                class: 'layui-btn-xs layui-btn-green',
                url: 'member.member/edit',
                icon: 'layui-icon layui-icon-edit',
                text: __('Edit'),
                title: __('Edit'),
                // full: 1,
                width:'800',
                extend:[
                    {
                        title: 'add'
                        ,id: 101
                        ,type: 'qita'
                        ,event: 'qita'
                        ,url: 'member.member/add'
                        ,icon: 'layui-icon layui-icon-edit'
                        ,callback: function(obj){
                            console.log("eee");
                            console.log(obj);
                            $.get("member.member/index", function(res){
                                console.log(res)
                                Fun.toastr.error("ok")
                            });
                        }
                    },
                    {
                        title: 'add'
                        ,id: 101
                        ,type: 'open'
                        ,event: 'open'
                        ,url: 'member.member/add'
                        ,icon: 'layui-icon layui-icon-edit'
                    }]
            }
        },
    };
    demo = function (obj,data){
        console.log(obj)
        console.log(data)
    }
    let Controller = {
        index: function () {
            var options = {
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                css: '.layui-table-cell{height: 50px; line-height: 40px; overflow: visible;}',
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                primaryKey: 'id',
                // primaryKey:"member_id",
                searchShow:true,
                // searchFormTpl:'search',//模板ID
                toolbar: ['refresh','add_full','destroy','import','export','recycle'],
                // toolbar: ['refresh','add_full','edit_url','destroy','import','export','recycle'],
                cols: [[
                    {checkbox: true,},
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'username', title: __('memberName'), width: 120,
                        // searchValue:'测试'
                    },
                    {field: 'email', title: __('Email'), width: 120,},
                    {field: 'mobile', title: __('mobile'), width: 120,edit: 'text'},
                    {
                        field: 'sex',
                        title: __('Sex'),
                        filter: 'sex',
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Secret'), 1: __('Male'), 2: __('Female')},
                        // templet: Table.templet.selects,
                        tips: __('Female')+'|'+  __('Male')
                    },
                    {
                        field: 'memberLevel.name',
                        title: __('MemberLevel'),
                        width: 120,
                        selectList: {1: __('Secret'), 2: __('Male'), 3: __('Female')},
                        templet: Table.templet.tags
                    },
                    {field: 'avatar', title: __('Avatar'), width: 120, templet: Table.templet.image},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Registertime'),dateformat:'yyyy-MM-dd HH:mm:ss', width: 180,search:'range'},
                    // {field: 'last_login', title: __('Lastlogintime'), width: 180,search:'timerange', templet: Table.templet.time},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit_url', 'destroy',],
                        // operat: ['edit_url','copy', 'destroy','dropdown'],
                        // operat:function(d){
                        //     return '<div><a href="/detail/{{=d.id}}" className="layui-table-link">{{=d.avatar}}</a> </div>'
                        // }
                        // operat:'<div><a href="/detail/{{=d.id}}" class="layui-table-link">{{=d.status}}</a></div>',
                        // operat:'#demo',
                    //     operat: ['edit_url', 'destroy',
                    //         // {
                    //         //     type: 'x',
                    //         //     event: 'x',
                    //         //     class: 'layui-btn-xs layui-btn-green',
                    //         //     url: 'member.member/edit',
                    //         //     icon: 'layui-icon layui-icon-edit',
                    //         //     text: __('Edit'),
                    //         //     title: __('Edit'),
                    //         //     // full: 1,
                    //         //     width:'800',
                    //         //     extend:'',
                    //         //     // btn:'close',
                    //         //     callback:function (obj) {
                    //         //     console.log($(obj).data());
                    //         // }
                    //         // } //使用方法三
                    // ]
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: true
                ,done: function (res, curr, count) {
                    this.limits.push(count) ;
                }
            }
            var table = Table.render(options);
            Table.api.bindEvent(Table.init.tableId) ;
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent();

        },
        copy:function () {
            Controller.api.bindevent();
        },
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true,},
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'username', title: __('memberName'), width: 120},
                    {field: 'email', title: __('Email'), width: 120,},
                    {field: 'mobile', title: __('mobile'), width: 120,edit: 'text'},
                    {
                        field: 'sex',
                        title: __('Sex'),
                        filter: 'sex',
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Secret'), 1: __('Male'), 2: __('Female')},
                        templet: Table.templet.select,
                        tips: __('Female')+'|'+  __('Male')
                    },
                    {
                        field: 'memberLevel.name',
                        title: __('MemberLevel'),
                        width: 120,
                        templet: Table.templet.text
                    },
                    {field: 'avatar', title: __('Avatar'), width: 120, templet: Table.templet.image},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Registertime'), width: 180,search:'range',},
                    // {field: 'last_login', title: __('Lastlogintime'), width: 180,search:'timerange', templet: Table.templet.time},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['restore', 'delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100,500,1000,5000],
                limit: 15,
                page: true
            });
            Table.api.bindEvent(Table.init.tableId);
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});
