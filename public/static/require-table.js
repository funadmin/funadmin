define(["jquery"], function ($) {

    var form = layui.form,
        layer = layui.layer,
        table = layui.table,
        laydate = layui.laydate;
    var Table = {
        init: {
            table_elem: 'list',
            tablId: 'list',
        },
        render: function (options) {
            options.elem = options.elem || '#' + Table.init.table_elem;
            options.init = options.init || Table.init;
            options.id = options.id || Table.init.tableId;
            options.layFilter = options.id;
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
            options.toolbar = Table.initToolbar(options.toolbar, options.elem, options.id,);
            var newTable = table.render(options);
            // 监听表格开关切换
            Table.api.switch(options.cols, options.init,options.id);
            // 监听表格搜索开关和toolbar按钮显示等
            Table.api.toolbar(options.layFilter, options.id);
            // 监听表格编辑
            Table.api.edit(options.init, options.layFilter, options.id);
            return newTable;
        },
        initToolbar: function (d, elem, tableId) {
            d = d || [];
            var toolbarHtml = '';
            var requests = Table.init.requests;
            $.each(d, function (i, v) {
                if (v == 'refresh') {
                    toolbarHtml += ' <button class="layui-btn layui-btn-sm layui-btn-normal" lay-event="refresh" lay-table-id="' + tableId + '"><i class="layui-icon layui-icon-refresh"></i> </button>\n';
                } else if (v == 'export') {
                    toolbarHtml += '<button class="layui-btn layui-btn-sm layui-btn-danger" lay-event="export" lay-table-id="' + tableId + '"  lay-url="' + Table.init.requests.export_url + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</button>\n';
                } else if (v == 'add') {
                    if (Speed.checkAuth('add')) {
                        toolbarHtml += '<button class="layui-btn layui-btn-sm"   lay-event="open" lay-table-id="' + tableId + '"  lay-url="' + Table.init.requests.add_url + '" lay-title="' + __('Add') + '"><i class="layui-icon layui-icon-add-circle-fine"></i>' + __('Add') + '</button>\n';
                    }
                } else if (v == 'delete') {
                    if (Speed.checkAuth('delete')) {
                        toolbarHtml += '<button class="layui-btn layui-btn-sm layui-btn-danger" lay-event="delete" lay-table-id="' + tableId + '"  lay-url="' + Table.init.requests.del_url + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</button>\n';
                    }
                } else if (typeof eval('Table.init.requests.' + v) == 'object') {
                    var v = eval('Table.init.requests.' + v);
                    if (Speed.checkAuth(v.url)) {
                        v.full = v.full || 0;
                        if (v.type == 'open') {
                            toolbarHtml += '<button class="layui-btn layui-btn-sm ' + v.class + '" lay-full="' + v.full + '" lay-event="open" lay-table-id="' + tableId + '"   lay-url="' + v.url + '" lay-title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</button>\n';
                        } else if (v.type == 'delete') {
                            toolbarHtml += '<button class="layui-btn layui-btn-sm ' + v.class + '" lay-full="' + v.full + '" lay-event="delete" lay-table-id="' + tableId + '" lay-url="' +
                                v.url + '" lay-title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</button>\n';
                        } else {
                            toolbarHtml += '<button class="layui-btn layui-btn-sm ' + v.class + '" lay-full="' + v.full + '" lay-event="delete" lay-table-id="' + tableId + '" lay-url="' +
                                v.url + '" lay-title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</button>\n';
                        }
                    }
                }

            });
            return '<div>' + toolbarHtml + '</div>';
        },
        initSearch: function (cols, elem, tableId) {
            cols = cols[0] || {};
            var newCols = [];
            var formHtml = '';
            $.each(cols, function (i, d) {
                d.field = d.field || false;
                d.fieldAlias = Speed.parame(d.fieldAlias, d.field);
                d.title = d.title || d.field || '';
                d.selectList = d.selectList || {};
                d.search = Speed.parame(d.search, true);
                d.searchTip = d.searchTip || __('Input') + d.title || '';
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
                            var selectHtml = '';
                            $.each(d.selectList, function (sI, sV) {
                                var selected = '';
                                if (sI == d.searchValue) {
                                    selected = 'selected=""';
                                }
                                selectHtml += '<option value="' + sI + '" ' + selected + '>' + sV + '</option>/n';
                            });
                            formHtml += '\t<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<select class="layui-select" id="filed-' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-search-op="' + d.searchOp + '" >\n' +
                                '<option value="">-' + __("All") + ' -</option> \n' +
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
                    '<legend>' + __('Search') + '</legend>\n' +
                    '<form class="layui-form layui-form-pane">\n' +
                    formHtml +
                    '<div class="layui-form-item layui-inline">\n' +
                    '<button type="submit" class="layui-btn layui-btn-green" lay-type="tableSearch" lay-table-id="' + tableId + '" lay-submit="submit" lay-filter="' + tableId + '_filter">' + __('Search') + '</button>\n' +
                    '<button type="reset" class="layui-btn layui-btn-primary" lay-type="tableReset"  lay-table-id="' + tableId + '" lay-filter="' + tableId + '_filter">' + __('Reset') + '</button>\n' +
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
        templet: {
            //时间
            time: function (d) {
                var ele = $(this)[0];
                var time = eval('d.' + ele.field)
                if (time) {
                    return layui.util.toDateString(time * 1000)
                } else {
                    return '';
                }
            },
            //图片
            image: function (d) {
                var ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 200;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                var src = d[ele.field] ? d[ele.field] : '/static/common/images/image.gif',
                    title = d[ele.title];
                return '<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + src + '" lay-title="' + title + '"  lay-event="photos">';
            },
            //选择
            select: function (d) {
                var ele = $(this)[0];
                ele.selectList = ele.selectList || {};
                var value = d[ele.field];
                if (ele.selectList[value] == undefined || ele.selectList[value] == '' || ele.selectList[value] == null) {
                    return value;
                } else {
                    return ele.selectList[value];
                }
            },
            //url
            url: function (d) {
                var ele = $(this)[0];
                var src = d[ele.field];
                return '<a class="layui-table-url" href="' + src + '" target="_blank" class="label bg-green">' + src + '</a>';
            },
            icon: function (d) {
                var ele = $(this)[0];
                var icon = d[ele.field];
                return '<span class="' + icon + '"></span>';

            },
            //开关
            switch: function (d) {
                var ele = $(this)[0];
                ele.filter = ele.filter || ele.field || null;
                ele.checked = ele.checked || 1;
                ele.tips = ele.tips || __('open') + '|' + __('close');
                var checked = d[ele.field] == ele.checked ? 'checked' : '';
                return '<input type="checkbox" name="' + ele.field + '" value="' + d.id + '" lay-skin="switch" lay-text="' + ele.tips + '" lay-filter="' + ele.filter + '" ' + checked + ' >';
            },
            //解析
            resolution: function (d) {
                var ele = $(this)[0];
                ele.field = ele.filter || ele.field || null;
                return eval('d.' + ele.field);
            },
            //操作
            operat: function (d) {
                var ele = $(this)[0];
                ele.operat = ele.operat || ['edit', 'delete'];
                var html = '';
                var requests = ele.init.requests;
                $.each(ele.operat, function (k, v) {
                    if (v == 'edit' || v == 'delete' || v == 'add') {
                        var vv = {};
                        if (v == 'add') {
                            vv = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-xs layui-btn-warm',
                                text: __('Add'),
                                title: '',
                                url: ele.init.requests.edit_url,
                                extend: "",
                                width: '600',
                                height: '600',
                            };
                        } else if (v == 'edit') {
                            vv = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-xs',
                                text: __('Edit'),
                                title: '',
                                url: ele.init.requests.edit_url,
                                extend: "",
                                width: '600',
                                height: '600',
                            };
                        } else {
                            vv = {
                                type: 'delete',
                                event: 'request',
                                class: 'layui-btn layui-btn-danger layui-btn-xs',
                                text: __('Delete'),
                                title: __('Are you sure to delete'),
                                url: ele.init.requests.del_url,
                                extend: "",
                                width: '600',
                                height: '600',
                            };
                        }
                        // 初始化数据
                        vv.type = vv.type || '';
                        vv.class = vv.class || '';
                        vv.text = vv.text || '';
                        vv.event = vv.event || vv.type || '';
                        vv.icon = vv.icon || '';
                        vv.url = vv.url || '';
                        vv.title = vv.title || vv.text || '';
                        vv.extend = vv.extend || '';
                        // 组合数据
                        vv.node = vv.url;
                        vv.url = vv.url.indexOf("?") != -1 ? vv.url + '&id=' + d.id : vv.url + '?id=' + d.id;
                        vv.width = vv.width != '' ? 'lay-width="' + vv.width + '"' : '';
                        vv.height = vv.height != '' ? 'lay-height="' + vv.height + '"' : '';
                        vv.type = vv.type != '' ? 'lay-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon != '' ? '<i class="' + vv.icon + '"></i>' : '';
                        vv.class = vv.class != '' ? 'class="' + vv.class + '" ' : '';
                        vv.url = vv.url != '' ? 'lay-url="' + vv.url + '" lay-title="' + vv.title + '" ' : '';
                        vv.event = vv.event != '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.tableid = 'lay-table-id="' + Table.init.table_elem + '"';
                        if (Speed.checkAuth(vv.node)) {
                            html += '<a ' + vv.class + vv.tableid + vv.width + vv.height + vv.url + vv.event + vv.type + vv.extend + '>' + vv.icon + vv.text + '</a>';
                        }
                    } else if (typeof eval('requests.' + v) == "object") {
                        v = eval('requests.' + v)
                        // 初始化数据
                        v.class = v.class || '';
                        v.full = v.full || '';
                        v.width = v.width || '';
                        v.height = v.height || '';
                        v.text = v.text || '';
                        v.type = v.type || '';
                        v.event = v.event || v.type || '';
                        v.icon = v.icon || '';
                        v.url = v.url || v.url;
                        v.title = v.title || v.text || '';
                        v.extend = v.extend || '';
                        v.node = v.url;
                        v.url = v.url.indexOf("?") != -1 ? v.url + '&id=' + d.id : v.url + '?id=' + d.id;
                        v.width = v.width != '' ? 'lay-width="' + v.width + '"' : '';
                        v.height = v.height != '' ? 'lay-height="' + v.height + '"' : '';
                        v.type = v.type != '' ? 'lay-type="' + v.type + '" ' : '';
                        v.icon = v.icon != '' ? '<i class="layui-icon ' + v.icon + '"></i>' : '';
                        v.class = v.class != '' ? 'class="layui-btn ' + v.class + '" ' : '';
                        v.url = v.url != '' ? 'lay-url="' + v.url + '" lay-title="' + v.title + '" ' : '';
                        v.event = v.event != '' ? 'lay-event="' + v.event + '" ' : '';
                        v.full = v.full != '' ? 'lay-full="' + v.full + '" ' : '';
                        if (Speed.checkAuth(v.node)) {
                            html += '<button ' + v.class + v.width + v.height + v.url + v.event + v.type + v.extend + v.full + '>' + v.icon + v.text + '</button>';
                        }
                    }
                });
                return html;
            },
        },
        on: function (fitler) {

        },
        //事件
        events: {
            open: function (othis) {
                Speed.events.open(othis);
            },
            photos: function (othis) {
                Speed.events.photos(othis);
            },
            refresh: function (othis) {
                var tableId = othis.attr('lay-table-id');
                if (tableId == undefined || tableId == '' || tableId == null) {
                    tableId = Table.init.tableId;
                }
                table.reload(tableId);
            },
            request: function (othis) {
                console.log(othis)
                var title = othis.attr('lay-title'),
                    url = othis.attr('lay-url'),
                    tableId = othis.attr('lay-table-id'),
                    title = title || __('Are you sure');
                tableId = tableId || Table.init.tableId;
                Speed.msg.confirm(title, function (res) {
                    Speed.ajax({
                        url: url,
                    }, function (res) {
                        Speed.msg.success(res.msg, function () {
                            table.reload(tableId)
                        });

                    }, function (res) {
                        Speed.msg.error(res.msg, function () {
                            table.reload(tableId);
                        });
                    })
                    Speed.msg.close();

                }, function (res) {
                    if (res == undefined) {
                        Speed.msg.close();
                        return false;
                    }
                    Speed.msg.success(res.msg, function () {
                        table.reload(tableId);
                    });
                });

                return false;
            },
            // 数据表格多删除
            delete: function (othis) {
                var tableId = othis.attr('lay-table-id'),
                    url = othis.attr('lay-url');
                tableId = tableId || Table.init.tableId;
                url = url != undefined ? Speed.url(url) : window.location.href;
                var checkStatus = table.checkStatus(tableId),
                    data = checkStatus.data;
                var ids = [];
                if (url.indexOf('?id=all') != -1) {
                    ids = 'all';
                } else {
                    if (data.length <= 0) {
                        Speed.msg.error(__('Please check data'));
                        return false;
                    }
                    $.each(data, function (k, v) {
                        ids.push(v.id);
                    });
                }
                Speed.msg.confirm(__('Are you sure you want to delete the %s selected item?', ids.length), function () {
                    Speed.ajax({
                        url: url,
                        data: {
                            ids: ids
                        },
                    }, function (res) {
                        Speed.msg.success(res.msg, function () {
                            table.reload(tableId);
                            Speed.msg.close()
                        });
                    },function (res) {
                        Speed.msg.error(res.msg, function () {
                            Speed.msg.close()
                        });
                    });
                });
                return false;
            },
            //返回页面
            closeOpen: function (othis) {
                Speed.api.closeCurrentOpen();
            },
        },
        api: {
            reload: function (tableId) {
                tableId = tableId ? tableId : Table.init.tablId;
                table.reload(tableId)
            },
            //表格收索
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
            //开关
            switch: function (cols, tableInit, tableId) {
                url = tableInit.requests.modify_url ? tableInit.requests.modify_url : false;
                cols = cols[0] || {};
                tableId = tableId || Table.init.tableId;
                if (cols.length > 0) {
                    $.each(cols, function (i, v) {
                        v.filter = v.filter || false;
                        if (v.filter != false && tableInit.requests.modify_url != false) {
                            form.on('switch(' + v.filter + ')', function (obj) {
                                var checked = obj.elem.checked ? 1 : 0;
                                var data = {
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
                                        table.reload(tableId);
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
                    });
                }
            },
            toolbar: function (layFilter, tableId) {
                table.on('toolbar(' + layFilter + ')', function (obj) {
                    // 搜索表单的显示
                    var othis = $(this)
                    switch (obj.event) {
                        case 'TABLE_SEARCH':
                            var searchFieldsetId = 'searchFieldList_' + tableId;
                            var _that = $("#" + searchFieldsetId);
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
                        var value = obj.value,
                            data = obj.data,
                            id = data.id,
                            field = obj.field;
                        var _data = {
                            id: id,
                            field: field,
                            value: value,
                        };
                        Speed.ajax({
                            url: tableInit.requests.modify_url,
                            prefix: true,
                            data: _data,
                        }, function (res) {
                            Speed.msg.success(res.msg, function () {
                                table.reload(tableId);
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
            bindEvent: function (table) {
                // // 监听点击事件
                $('body').on('click', '[lay-event]', function () {
                    var _that = $(this), attrEvent = _that.attr('lay-event');
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] &&  Table.events[attrEvent].call(this,_that)
                    }
                });



            },
        },
    }

    return Table;

})