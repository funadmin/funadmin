define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: tableVars.elem,
        tableId: tableVars.id,
        requests: tableVars.requests,
    }
    let Controller = {
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    layui.each(tableVars.actions, function (i, v) {
        switch (v){
            case 'index':
                Controller[v] = function () {
                var table = Table.render(tableVars['index']);
                Table.api.bindEvent(Table.init.tableId) ;
                };
            break
            case 'add':
            case 'edit':
            case 'copy':
                Controller[v] = function () {
                    Controller.api.bindevent()
                };
                break;
            case 'recycle':
                Controller[v] = function () {
                    Table.render(tableVars['recycle']);
                    Table.api.bindEvent(Table.init.tableId);
                };
                break;
            default:
                Controller[v] = function () {
                    Controller.api.bindevent()
                };
                break;
        }
    });
    return Controller;
});
