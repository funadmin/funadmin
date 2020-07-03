define(["jquery",'upload'], function ($,Upload) {

    let form = layui.form,
        layer = layui.layer,
        table = layui.table,
        laydate = layui.laydate,
        element = layui.element;

    let Table = {
        init:{
            table_elem: 'list',
            tablId: 'list',
        },
        render: function (options) {
            options.elem = options.elem || '#' + Table.init.table_elem;
            options.init = options.init || Table.init;
            options.id = options.id || Table.init.tableId;
            options.layFilter = options.id ;
            options.url = options.url || window.location.href;
            options.toolbar = options.toolbar || '#toolbar';
            options.page = Speed.parame(options.page, true);
            options.search = Speed.parame(options.search, true);
            options.limit = options.limit || 15;
            options.limits = options.limits || [10, 15, 20, 25, 50, 100];
            options.defaultToolbar = options.defaultToolbar || ['filter', 'exports', 'print', {
                title: __("Search"),
                layEvent: 'TABLE_SEARCH',
                icon: 'layui-icon-search',
                extend: 'lay-table-id="' + options.id + '"'
            }];
            // 初始化表格lay-filter
            $(options.elem).attr('lay-filter', options.layFilter);

            // 初始化表格搜索
            options.toolbar = options.toolbar || ['refresh', 'add', 'delete'];

            if (options.search == true) {
                Table.initSearch(options.cols, options.elem, options.id);
            }
            // 初始化表格左上方工具栏
            options.toolbar = Table.initToolbar(options.toolbar, options.elem, options.id, options.init);

            let newTable = table.render(options);

            // 监听表格开关切换
            Table.api.switch(options.cols, options.init);

            // 监听表格搜索开关 和toolbar 按钮显示
            Table.api.toolbar(options.layFilter, options.id);

            // 监听表格编辑
            Table.api.edit(options.init, options.layFilter, options.id);

            return newTable;
        },
        initToolbar: function (d, elem, tableId, init) {
            d = d || [];
            let toolbarHtml = '';
            let requests = Table.init.requests;
            $.each(d, function (i, v) {
                if (v == 'refresh') {
                    toolbarHtml += ' <button class="layui-btn layui-btn-sm layui-btn-normal" lay-event="refresh" lay-table-id="' + tableId + '"><i class="layui-icon layui-icon-refresh"></i> </button>\n';
                } else if (v == 'add') {
                    if (Speed.checkAuth('add')) {
                        toolbarHtml += '<button class="layui-btn layui-btn-sm"   lay-event="open" lay-table-id="' + tableId + '"  lay-request="' + Table.init.requests.add_url + '" lay-title="'+__('Add')+'"><i class="layui-icon layui-icon-add-circle-fine"></i>'+__('Add')+'</button>\n';
                    }
                } else if (v == 'delete') {
                    if (Speed.checkAuth('delete')) {
                        toolbarHtml += '<button class="layui-btn layui-btn-sm layui-btn-danger" lay-event="delete" lay-table-id="' + tableId + '"  lay-request="' + Table.init.requests.del_url + '"><i class="layui-icon layui-icon-delete"></i>'+__('Delete')+'</button>\n';
                    }
                } else if (v == 'export') {
                    if (Speed.checkAuth('export')) {
                        toolbarHtml += '<button class="layui-btn layui-btn-sm layui-btn-danger" lay-event="export" lay-table-id="' + tableId + '"  lay-request="' + Table.init.requests.export_url + '"><i class="layui-icon layui-icon-delete"></i>'+__('Delete')+'</button>\n';
                    }
                }  else if(typeof  eval('requests.'+v)=='object'){
                    var requests_index = eval('requests.'+v);
                    if (Speed.checkAuth(v) && requests_index) {
                        if(requests_index.type=='open'){
                            toolbarHtml += '<button class="layui-btn layui-btn-sm ' + Table.init.requests[v].class + '"  lay-event="open" lay-table-id="' + tableId + '"   lay-request="' + Table.init.requests[v].url + '" lay-title="' +Table.init.requests[v].title + '" ><i class="layui-icon ' + Table.init.requests[v].icon + '"></i>' + Table.init.requests[v].title + '</button>\n';
                        }else if(requests_index.type=='delete'){
                            toolbarHtml += '<button class="layui-btn layui-btn-sm ' + Table.init.requests[v].class + '" lay-event="delete" lay-table-id="' + tableId + '" lay-request="' + Table.
                                Table.init.requests[v].url + '" lay-title="' + Table.init.requests[v].title + '" ><i class="layui-icon ' + Table.init.requests[v].icon + '"></i>' + Table.init.requests[v].title + '</button>\n';
                        }
                    }
                }

            });
            return '<div>' + toolbarHtml + '</div>';
        },
        initSearch: function (cols, elem, tableId) {
            // TODO 只初始化第一个table搜索字段，如果存在多个(绝少数需求)，得自己去扩展
            cols = cols[0] || {};
            let newCols = [];
            let formHtml = '';
            $.each(cols, function (i, d) {
                d.field = d.field || false;
                d.fieldAlias = Speed.parame(d.fieldAlias, d.field);
                d.title = d.title || d.field || '';
                d.selectList = d.selectList || {};
                d.search = Speed.parame(d.search, true);
                d.searchTip = d.searchTip || '请输入' + d.title || '';
                d.searchValue = d.searchValue || '';
                d.searchOp = d.searchOp || '%*%';
                d.timeType = d.timeType || 'datetime';
                if (d.field != false && d.search != false) {
                    switch (d.search) {
                        case true:
                            formHtml += '\t<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed-' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-search-op="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>';
                            break;
                        case  'select':
                            d.searchOp = '=';
                            let selectHtml = '';
                            $.each(d.selectList, function (sI, sV) {
                                let selected = '';
                                if (sI == d.searchValue) {
                                    selected = 'selected=""';
                                }
                                selectHtml += '<option value="' + sI + '" ' + selected + '>' + sV + '</option>/n';
                            });
                            formHtml += '\t<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<select class="layui-select" id="filed-' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-search-op="' + d.searchOp + '" >\n' +
                                '<option value="">- 全部 -</option> \n' +
                                selectHtml +
                                '</select>\n' +
                                '</div>\n' +
                                '</div>';
                            break;
                        case 'range':
                            d.searchOp = 'range';
                            formHtml += '\t<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed-' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-search-op="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>';
                            break;
                        case 'time':
                            d.searchOp = '=';
                            formHtml += '\t<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed-' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-search-op="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>';
                            break;
                    }
                    newCols.push(d);
                }
            });
            if (formHtml != '') {
                $(elem).before('<fieldset id="searchFieldList_' + tableId + '" class="layui-elem-field table-search-fieldset layui-hide">\n' +
                    '<legend>'+__('Search')+'</legend>\n' +
                    '<form class="layui-form layui-form-pane">\n' +
                    formHtml +
                    '<div class="layui-form-item layui-inline">\n' +
                    '<button type="submit" class="layui-btn layui-btn-primary" lay-type="tableSearch" lay-type="tableSearch" lay-table-id="' + tableId + '" lay-submit="submit" lay-filter="' + tableId + '_filter"><i class="layui-icon">&#xe615;</i> 搜 索</button>\n' +
                    ' </div>' +
                    '</form>' +
                    '</fieldset>');
                // 初始化form表单
                Table.api.tableSearch(tableId);
                form.render();
                $.each(newCols, function (ncI, ncV) {
                    if (ncV.search == 'range') {
                        laydate.render({range: true, type: ncV.timeType, elem: '[name="' + ncV.field + '"]'});
                    }
                    if (ncV.search == 'time') {
                        laydate.render({type: ncV.timeType, elem: '[name="' + ncV.field + '"]'});
                    }
                });
            }
        },
        templet:{
            time:function(d){
                let ele = $(this)[0];
                var time  = eval('d.'+ele.field)
                if(time){
                    return  layui.util.toDateString(time*1000)
                }else{
                    return '';
                }
            },
            image: function (d) {
                let ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 200;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                let src = d[ele.field],
                    title = d[ele.title];
                return '<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + src + '" lay-title="' + title + '"  lay-event="photos">';
            },
            select: function (d) {
                let ele = $(this)[0];
                ele.selectList = ele.selectList || {};
                let value = d[ele.field];
                if (ele.selectList[value] == undefined || ele.selectList[value] == '' || ele.selectList[value] == null) {
                    return value;
                } else {
                    return ele.selectList[value];
                }
            },
            url: function (d) {
                let ele = $(this)[0];
                let src = d[ele.field];
                return '<a class="layui-table-url" href="' + src + '" target="_blank" class="label bg-green">' + src + '</a>';
            },
            switch: function (d) {
                let ele = $(this)[0];
                ele.filter = ele.filter || ele.field || null;
                ele.checked = ele.checked || 1;
                ele.tips = ele.tips || __('open')|__('close');
                let checked = d[ele.field] == ele.checked ? 'checked' : '';
                return '<input type="checkbox" name="' + ele.field + '" value="' + d.id + '" lay-skin="switch" lay-text="' + ele.tips + '" lay-filter="' + ele.filter + '" ' + checked + ' >';
            },
            //多便利解析
            resolution : function (d) {
                let ele = $(this)[0];
                ele.field = ele.filter || ele.field || null;
                return eval('d.'+ele.field);
            },
            //操作
            operat: function (d) {
                let ele = $(this)[0];
                ele.operat = ele.operat || ['edit', 'delete'];
                let html = '';
                let  requests = ele.init.requests;
                $.each(ele.operat, function (k, v) {
                    if (v == 'edit' || v == 'delete') {
                        let vv = {};
                        if (v == 'edit') {
                            vv = {
                                type:'open',
                                event:'open',
                                class: 'layui-btn layui-btn-xs',
                                text: __('Edit'),
                                title:'',
                                request: ele.init.requests.edit_url,
                                extend: ""
                            };
                        } else {
                            vv = {
                                type:'delete',
                                event:'request',
                                class: 'layui-btn layui-btn-danger layui-btn-xs',
                                text: __('Delete'),
                                title:__('Are you sure to delete'),
                                request: ele.init.requests.del_url,
                                extend: ""
                            };
                        }
                        // 初始化数据
                        vv.type = vv.type || '';
                        vv.class = vv.class || '';
                        vv.text = vv.text || '';
                        vv.event = vv.event || vv.type || '';
                        vv.icon = vv.icon || '';
                        vv.request = vv.request || '';
                        vv.title = vv.title || vv.text || '';
                        vv.extend = vv.extend || '';
                        // 组合数据
                        vv.request = vv.request.indexOf("?") != -1 ? vv.request + '&id=' + d.id : vv.request + '?id=' + d.id;
                        vv.type = vv.type != '' ? 'lay-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon != '' ? '<i class="' + vv.icon + '"></i>' : '';
                        vv.class = vv.class != '' ? 'class="' + vv.class + '" ' : '';
                        vv.request = vv.request != '' ? 'lay-request="' + vv.request + '" lay-title="' + vv.title + '" ' : '';
                        vv.event = vv.event != '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.tableid = 'lay-table-id="'+Table.init.table_elem +'"';
                        let check = (vv.request == '' || vv.request == undefined) ? true : Speed.checkAuth(vv.request);
                        if (check == true) {
                            html += '<a ' + vv.class + vv.tableid + vv.request + vv.event + vv.type+ vv.extend + '>' + vv.icon + vv.text + '</a>';
                        }
                    } else if (typeof eval('requests.'+v) == "object") {
                        $.each(v, function (kk, vv) {
                            // 初始化数据
                            vv.class = vv.class || '';
                            vv.text = vv.text || '';
                            vv.type = vv.type || '';
                            vv.event = vv.event || vv.type || '';
                            vv.icon = vv.icon || '';
                            vv.request = vv.request || '';
                            vv.title = vv.title || vv.text || '';
                            vv.extend = vv.extend || '';

                            vv.request = vv.request.indexOf("?") != -1 ? vv.request + '&id=' + d.id : vv.request + '?id=' + d.id;
                            // 组合数据
                            vv.type = vv.type != '' ? 'lay-type"' + vv.type + '" ' : '';
                            vv.icon = vv.icon != '' ? '<i class="' + vv.icon + '"></i>' : '';
                            vv.class = vv.class != '' ? 'class="' + vv.class + '" ' : '';
                            vv.request = vv.request != '' ? 'lay-request="' + vv.request + '" lay-title="' + vv.title + '" ' : '';
                            vv.event = vv.event != '' ? 'lay-event="' + vv.event + '" ' : '';
                            // vv.auth = vv.auth != '' ? 'auth="' + vv.auth + '" ' : '';
                            let check = (vv.auth == '' || vv.auth == undefined) ? true : Speed.checkAuth(vv.auth);
                            if (check == true) {
                                html += '<button ' + vv.class + vv.request + vv.event  + vv.type + vv.extend+ '>' + vv.icon + vv.text + '</button>';
                            }
                        });
                    }
                });
                return html;
            },
        },
        on:function(fitler){

        },

        //事件
        events:{
            open:function (othis) {
                Speed.api.open(
                    othis.attr('lay-title'),
                    Speed.url(othis.attr('lay-request')),
                    othis.attr('lay-width'),
                    othis.attr('lay-height')
                );
            },
            photos: function (othis) {
                let title = othis.attr('lay-image'),
                    src = othis.attr('src'),
                    alt = othis.attr('alt');
                let photos = {
                    "title": title,
                    "id": Math.random(),
                    "data": [
                        {
                            "alt": alt,
                            "pid": Math.random(),
                            "src": src,
                            "thumb": src
                        }
                    ]
                };
                layer.photos({
                    photos: photos,
                    anim: 5
                });
                return false;
            },
            refresh:function (othis) {
                let tableId = othis.attr('lay-table-id');
                if (tableId == undefined || tableId == '' || tableId == null) {
                    tableId = Table.init.tableId;
                }
                table.reload(tableId);
            },
            request:function (othis) {
                let title = othis.attr('lay-title'),
                    url = othis.attr('lay-request'),
                    tableId = othis.attr('lay-table-id'),
                    addons = othis.attr('lay-addons');
                if (addons != true && addons != 'true') {
                    url = Speed.url(url);
                }
                title = title || __('Are you sure');
                tableId = tableId || Table.init.tableId;
;                Speed.msg.confirm(title, function () {
                    Speed.ajax({
                        url: url,
                        method:'get',
                    }, function (res) {
                        Speed.msg.success(res.msg, function () {
                            table.reload(tableId);
                        });
                    })
                });
                return false;
            },
            // 数据表格多删除
            delete:function(othis){
                let tableId = othis.attr('lay-table-id'),
                    url = othis.attr('lay-request');
                tableId = tableId || Table.init.tableId;
                url =  url != undefined ? Speed.url(url) : window.location.href;
                let checkStatus = table.checkStatus(tableId),
                    data = checkStatus.data;
                if (data.length <= 0) {
                    Speed.msg.error(__('Please check data'));
                    return false;
                }
                let ids = [];
                $.each(data, function (k, v) {
                    ids.push(v.id);
                });
                Speed.msg.confirm(__('Are you sure you want to delete the %s selected item?',ids.length), function () {
                    Speed.ajax({
                        url: url,
                        data: {
                            ids: ids
                        },
                    }, function (res) {
                        Speed.msg.success(res.msg, function () {
                            table.reload(tableId);
                        });
                    });
                });
                return false;
            },
            //返回页面
            closeOpen:function (othis) {
                Speed.api.closeCurrentOpen();
            },
            uploads:function () {
                Upload.api.upload();
            },
        },
        api:{

            tableSearch: function (tableId) {
                form.on('submit(' + tableId + '_filter)', function (data) {
                    var dataField = data.field;
                    var formatFilter = {},
                        formatOp = {};
                    $.each(dataField, function (key, val) {
                        if (val != '') {
                            formatFilter[key] = val;
                            var op = $('#filed-' + key).attr('lay-search-op');
                            op = op || '%*%';
                            formatOp[key] = op;
                        }
                    });
                    table.reload(tableId, {
                        page: {
                            curr: 1
                        }
                        , where: {
                            filter: JSON.stringify(formatFilter),
                            op: JSON.stringify(formatOp)
                        }
                    }, 'data');
                    return false;
                });
            },
            switch : function (cols, tableInit, tableId) {
                url = tableInit.requests.modify_url ? tableInit.requests.modify_url : false;
                cols = cols[0] || {};
                tableId = tableId || Table.init.tableId;
                if (cols.length > 0) {
                    $.each(cols, function (i, v) {
                        v.filter = v.filter || false;
                        if (v.filter != false && tableInit.requests.modify_url != false) {
                            form.on('switch(' + v.filter + ')', function (obj) {
                                let checked = obj.elem.checked ? 1 : 0;
                                    let data = {
                                        id: obj.value,
                                        field: v.field,
                                        value: checked,
                                    };
                                    Speed.ajax({
                                        url: url,
                                        prefix: true,
                                        data: data,
                                    }, function (res) {

                                        Speed.msg.success(res.msg, function () {
                                            // table.reload(option.tableId);
                                        });
                                    }, function (res) {
                                        Speed.msg.error(res.msg, function () {
                                            // table.reload(option.tableId);
                                        });
                                    }, function () {
                                        table.reload(v.tableId);
                                    });
                            });
                        }
                    });
                }
            },
            toolbar: function (layFilter, tableId) {
                table.on('toolbar(' + layFilter + ')', function (obj) {
                    // 搜索表单的显示
                    var othis = $(this)
                    switch (obj.event) {
                        case 'TABLE_SEARCH':
                            let searchFieldsetId = 'searchFieldList_' + tableId;
                            let _that = $("#" + searchFieldsetId);
                            if (_that.hasClass("layui-hide")) {
                                _that.removeClass('layui-hide');
                            } else {
                                _that.addClass('layui-hide');
                            }
                            break;
                        case 'refresh':
                            Table.events.refresh(othis);
                            break;
                        case 'delete':
                            Table.events.delete(othis);
                            break;
                        case 'open':
                            Table.events.open(othis);
                            break;
                        default:
                            return true;
                    }
                });
            },
            edit: function (tableInit, layFilter, tableId) {
                tableInit.requests.modify_url = tableInit.requests.modify_url || false;
                tableId = tableId || Table.init.tableId;
                if (tableInit.requests.modify_url != false) {
                    table.on('edit(' + layFilter + ')', function (obj) {
                        let value = obj.value,
                            data = obj.data,
                            id = data.id,
                            field = obj.field;
                        let _data = {
                            id: id,
                            field: field,
                            value: value,
                        };
                        Speed.ajax({
                            url: tableInit.requests.modify_url,
                            prefix: true,
                            data: _data,
                        }, function (res) {
                            Speed.msg.error(res.msg, function () {
                                // table.reload(tableId);
                            });
                        }, function (res) {
                            Speed.msg.error(res.msg, function () {
                                table.reload(tableId);
                            });
                        }, function () {
                            table.reload(tableId);
                        });
                    });
                }
            },

            bindEvent :function (table) {
                // // 监听点击事件
                $('body').on('click', '[lay-event]', function () {
                    var _that = $(this), attrEvent = _that.attr('lay-event');
                    eval('Table.events.'+attrEvent+'(_that)');

                });
                // 表格修改
                $("body").on("mouseenter", ".table-edit-tips", function () {
                    let openTips = layer.tips(__('Click the content to edit'), $(this), {tips: [2, '#e74c3c'], time: 4000});
                });


            },
        },
    }

    return Table;

})