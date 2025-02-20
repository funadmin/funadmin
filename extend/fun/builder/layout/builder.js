// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code
define(['table', 'form'], function (Table, Form) {
    let Controller = {
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    switch (window.ACTION) {
        case 'index':
            Controller[window.ACTION] = function () {
                if (typeof tableOptions === "object") {
                    layui.each(tableOptions, function (i, v) {
                        Table.init[i] = init = {
                            table_elem: tableOptions[i]['elem'],
                            tableId: tableOptions[i]['id'],
                            requests: requests,
                        };
                        tableOptions[i]['elem'] = '#' + tableOptions[i]['elem']
                        tableOptions[i]['init'] = init;
                        Table.render(tableOptions[i]);
                        Table.api.bindEvent(tableOptions[i]['id']);
                    })
                }
                if (typeof extraJs !=="undefined") {
                    eval(extraJs);
                }
            }
            break;
        case 'add':
        case 'edit':
        case 'copy':
            Controller[window.ACTION] = function () {
                if (typeof tableOptions === "object") {
                    layui.each(tableOptions, function (i, v) {
                        Table.init[i] = {
                            table_elem: tableOptions[i]['elem'],
                            tableId: tableOptions[i]['id'],
                            requests: requests,
                        };
                        tableOptions[i]['elem'] = '#' + tableOptions[i]['elem']
                        tableOptions[i]['init'] = Table.init;
                        Table.render(tableOptions[i]);
                        Table.api.bindEvent(tableOptions[i]['id']);
                    })
                }
                if (typeof extraJs !=="undefined") {
                    eval(extraJs);
                }
                Controller.api.bindevent()
            }
            break;
        case 'recycle':
            Controller[window.ACTION] = function () {
                if (typeof tableOptions === "object") {
                    layui.each(tableOptions, function (i, v) {
                        Table.init[i] = {
                            table_elem: tableOptions[i]['elem'],
                            tableId: tableOptions[i]['id'],
                            requests: requests,
                        };
                        tableOptions[i]['elem'] = '#' + tableOptions[i]['elem']
                        tableOptions[i]['init'] = Table.init;
                        Table.render(tableOptions[i]);
                        Table.api.bindEvent(tableOptions[i]['id']);
                    })
                }
                if (typeof extraJs !=="undefined") {
                    eval(extraJs);
                }
            }

            break;
        default:
            Controller[window.ACTION] = function () {
                if (typeof tableOptions === "object") {
                    layui.each(tableOptions, function (i, v) {
                        Table.init[i] = {
                            table_elem: tableOptions[i]['elem'],
                            tableId: tableOptions[i]['id'],
                            requests: requests,
                        };
                        tableOptions[i]['elem'] = '#' + tableOptions[i]['elem']
                        tableOptions[i]['init'] = Table.init;
                        Table.render(tableOptions[i]);
                        Table.api.bindEvent(tableOptions[i]['id']);
                    })
                }
                if (typeof extraJs !=="undefined") {
                    eval(extraJs);
                }
                Controller.api.bindevent()
            }
            break;
    }
    return Controller;
});
