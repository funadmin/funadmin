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
    switch (ACTION) {
        case 'index':
            if (typeof options !== "undefined" && options[ACTION]) {
                Controller[ACTION] = function () {
                    Table.init = {
                        table_elem: options[ACTION]['elem'],
                        tableId: options[ACTION]['id'],
                        requests: requests,
                    };
                    options[ACTION]['elem'] = '#' + options[ACTION]['elem']
                    options[ACTION]['init'] = Table.init;
                    op = options[ACTION];
                    console.log(op)
                    var table = Table.render(options[ACTION]);
                    Table.api.bindEvent(Table.init.tableId);
                };
            }
            break;
        case 'add':
        case 'edit':
        case 'copy':
            if (typeof options !== "undefined" && options[ACTION]) {
                Controller[ACTION] = function () {
                    Table.init = {
                        table_elem: options[ACTION]['elem'],
                        tableId: options[ACTION]['id'],
                        requests: requests,
                    };
                    options[ACTION]['elem'] = '#' + options[ACTION]['elem'];
                    options[ACTION]['init'] = Table.init;
                    var table = Table.render(options[ACTION]);
                    Table.api.bindEvent(options[ACTION]['id']);
                };
            }
            Controller[ACTION] = function () {
                Controller.api.bindevent()
            };
            break;
        case 'recycle':
            if (typeof options !== "undefined" && options[ACTION]) {
                Controller[ACTION] = function () {
                    Table.init = {
                        table_elem: options[ACTION]['elem'],
                        tableId: options[ACTION]['id'],
                        requests: requests,
                    };
                    options[ACTION]['elem'] = '#' + options[ACTION]['elem'];
                    options[ACTION]['init'] = Table.init;
                    var table = Table.render(options[ACTION]);
                    Table.api.bindEvent(Table.init.tableId);
                };
            }
            break;
        default:
            if (typeof options !== "undefined" && options[ACTION]) {
                Controller[ACTION] = function () {
                    Table.init = {
                        table_elem: options[ACTION]['elem'],
                        tableId: options[ACTION]['id'],
                        requests: requests,
                    };
                    options[ACTION]['elem'] = '#' + options[ACTION]['elem'];
                    options[ACTION]['init'] = Table.init;
                    var table = Table.render(options[ACTION]);
                    Table.api.bindEvent(options[ACTION]['id']);
                };
            }
            Controller[ACTION] = function () {
                Controller.api.bindevent()
            };
            break;
    }
    return Controller;
});
