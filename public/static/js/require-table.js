// +----------------------------------------------------------------------
// | FunAdmin极速开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

define(['jquery', 'timePicker','fu'], function ($, timePicker,Fu) {
    var Table = {
        init: {table_elem: 'list', tableId: 'list', searchInput: true, requests: {export_url: '/ajax/export'},},
        render: function (options) {
            options.elem = options.elem || '#' + Table.init.table_elem;
            options.primaryKey = options.primaryKey || $('#'+options.id).data('primaryKey') || 'id';
            options.init = options.init || Table.init;
            options.id = options.id || Table.init.tableId;
            options.layFilter = options.id;
            options.url = options.url || window.location.href;
            options.toolbar = options.toolbar || '#toolbar';
            options.search = Fun.param(options.search, true);
            options.searchFormTpl = Fun.param(options.searchFormTpl || Table.init.searchFormTpl, false);
            options.searchShow = Fun.param(options.searchShow || Table.init.searchShow, false);
            options.rowDouble =  !(options.rowDouble != undefined && options.rowDouble == false && (Table.init.rowDouble == undefined || (Table.init.rowDouble == false)));
            options.searchInput = !(options.searchInput != undefined && options.searchInput == false && (Table.init.searchInput == undefined || (Table.init.searchInput == false)));
            options.searchName = Fun.param(options.searchName || Table.init.searchName, 'id');
            options.cols = Table.colsRender(options);
            options.page = Fun.param(options.page, true);
            options.limit = options.limit || 15;
            options.limits = options.limits || [10, 15, 20, 25, 50, 100];
            options.defaultToolbar = options.defaultToolbar || ['filter', 'exports', 'print',];
            if (options.search) {
                options.defaultToolbar.push({
                    title: __("Search"),
                    layEvent: 'TABLE_SEARCH',
                    icon: 'layui-icon-search',
                    extend: 'data-tableid="' + options.id + '"'
                })
            }
            $(options.elem).attr('lay-filter', options.layFilter);
            options.toolbar = options.toolbar || ['refresh', 'export', 'add', 'delete', 'recycle'];
            if (options.search === true && options.searchFormTpl !== false) {
                data = options.tpldata || {}
                layui.laytpl($('#' + options.searchFormTpl).html()).render(data, function (html) {
                    $('#' + options.id).before(html);
                    Table.api.tableSearch(options.id);
                    var formVal = $('#layui-form-' + options.id + ' [name]').serializeArray()
                    cols = [];
                    layui.each(formVal, function (i, v) {
                        var O = $('[name="' + v.name + '"]');
                        arr = {
                            field: v.name,
                            search: O.data('search'),
                            searchOp: O.data('searchop'),
                            timepickerformat: Fun.param(O.data('timepickerformat'), 'YYYY-MM-DD HH:mm:ss'),
                            searchdateformat: Fun.param(O.data('searchdateformat'), 'yyyy-MM-dd HH:mm:ss'),
                            timeType: Fun.param(O.data('timetype'), 'datetime'),
                        }
                        cols.push(arr)
                    })
                    Table.timeRender(cols)
                    layui.form.render()
                    Fu.events.xmSelect();
                });
                layui.form.render()
            }
            if (options.search === true && options.searchFormTpl === false) {
                Table.renderSearch(options)
            }
            //修改或添加主键id
            $('#'+options.id).attr('data-primarykey',options.primaryKey);
            //是否字符串自定义模板
            options.toolbar = typeof options.toolbar === 'string' ? options.toolbar : Table.renderToolbar(options);
            var newTable = layui.table.render(options);
            Table.api.switch(options);
            Table.api.selects(options);
            Table.api.toolbar(options);
            Table.api.sort(options);
            if (options.rowDouble) {
                Table.api.rowDouble(options)
            }
            Table.api.edit(options);
            return newTable
        },
        renderToolbar: function (options) {
            var d = options.toolbar, tableId = options.id, searchInput = options.searchInput;
            d = d || [];
            var toolbarHtml = '';
            var nodeArr = ['refresh', 'export', 'add', 'delete', 'destroy', 'recycle', 'restore'];
            layui.each(d, function (i, v) {
                if ($.inArray(v, nodeArr) !== -1) {
                    if (v !== 'refresh') url = Fun.replaceurl(eval('Table.init.requests.' + v + '_url'), d);
                    if (v === 'refresh') {
                        toolbarHtml += ' <a class="layui-btn layui-btn-sm layui-btn-normal" lay-event="refresh" data-tableid="' + tableId + '"><i class="layui-icon layui-icon-refresh"></i> </a>\n'
                    } else if (v === 'export') {
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger" lay-event="export" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-export"></i>' + __('Export') + '</a>\n'
                    } else if (v === 'add') {
                        if (Fun.checkAuth('add', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm"   lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '" title="' + __('Add') + '"><i class="layui-icon layui-icon-add-circle-fine"></i>' + __('Add') + '</a>\n'
                        }
                    } else if (v === 'delete') {
                        if (Fun.checkAuth('delete', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure to delete') + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</a>\n'
                        }
                    } else if (v === 'destroy') {
                        if (Fun.checkAuth('destroy', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure  to destroy') + '"><i class="layui-icon layui-icon-delete"></i>' + __('Destroy') + '</a>\n'
                        }
                    } else if (v === 'recycle') {
                        if (Fun.checkAuth('recycle', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-normal" lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-find-fill"></i>' + __('Recycle') + '</a>\n'
                        }
                    } else if (v === 'restore') {
                        if (Fun.checkAuth('restore', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="request" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure restore') + '"><i class="layui-icon layui-icon-find-fill"></i>' + __('Restore') + '</a>\n'
                        }
                    }
                } else if (typeof v === 'string' && typeof eval('Table.init.requests.' + v) === 'string') {
                    if (Fun.checkAuth(v, options.elem)) {
                        url = Fun.replaceurl(eval(('Table.init.requests.' + v + '_url')), d);
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-set-sm"></i>' + __(v) + '</a>\n'
                    }
                } else if (typeof v === 'string' && typeof eval('Table.init.requests.' + v) === 'object' || typeof v === 'object') {
                    if (typeof v === 'string') {
                        v = eval('Table.init.requests.' + v)
                    }
                    v.extend = typeof v.extend === "object" ? "data-extend='" + JSON.stringify(v.extend) + "'" : v.extend;
                    url = Fun.replaceurl(v.url, d);
                    v.node = v.node === false ? v.node : Fun.common.getNode(v.url);
                    if (v.node === false || Fun.checkAuth(v.node, options.elem)) {
                        v.full = v.full || 0;
                        v.resize = v.resize || 0;
                        v.width = v.width || 800;
                        v.height = v.height || 800;
                        v.extend = v.extend || '';
                        if (v.type) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-width="' + v.width + '" data-height="' + v.height + '" data-full="' + v.full + '" data-resize="' + v.resize + '" lay-event="' + v.type + '" data-tableid="' + tableId + '"   data-url="' + url + '" data-text="' + v.text + '" data-title="'+ v.title +'" title="' + v.title + '" ' + v.extend + '><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n'
                        } else {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-width="' + v.width + '" data-height="' + v.height + '" data-full="' + v.full + '" data-resize="' + v.resize + '" lay-event="request" data-tableid="' + tableId + '" data-url="' + url + '" data-text="' + v.text + '"  data-title="'+ v.title +'" title="' + v.title + '"' + v.extend + '><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n'
                        }
                    }
                }
            });
            if (searchInput) {
                toolbarHtml += '<input id="layui-input-search"  name="' + options.searchName + '" value="" placeholder="' + __('Search') + '" class="layui-input layui-hide-xs" style="display:inline-block;width:auto;float: right;\n' + 'margin:2px 25px 0 0;height:30px;">\n'
            }
            return '<div>' + toolbarHtml + '</div>'
        },
        renderSearch: function (options) {
            tableId = options.id;
            cols = options.cols;
            show = Fun.param(options.searchShow, false) ? '' : 'layui-hide';
            cols = cols[0] || {};
            var newCols = [];
            var formHtml = '';
            layui.each(cols, function (i, d) {
                d.field = d.field || false;
                d.fieldAlias = Fun.param(d.fieldAlias, d.field);
                d.title = d.title || d.field || '';
                d.filter = d.filter || d.field || '';
                d.class = d.class || '';
                d.search = Fun.param(d.search, true);
                d.searchTip = d.searchTip || __('Input') + d.title || '';
                d.searchValue = d.searchValue || '';
                d.searchOp = d.searchOp || '%*%';
                d.searchOp = d.searchOp.toLowerCase();
                d.timeType = d.timeType || 'datetime';
                d.dateformat = d.dateformat || 'yyyy-MM-dd HH:mm:ss';
                d.timepickerformat = d.timepickerformat || 'YYYY-MM-DD HH:mm:ss';
                d.searchdateformat = d.searchdateformat || d.dateformat;
                d.extend = d.extend || '';
                d.extend = typeof d.extend === "object" ? "data-extend='" + JSON.stringify(d.extend) + "'" : d.extend;
                if (d.field !== false && d.search !== false) {
                    d.search = typeof d.search ==='string' ?d.search.toLowerCase():d.search;
                    switch (d.search) {
                        case true:
                            formHtml += '<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline ">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n';
                            if (d.filter && d.filter.toLowerCase() == 'xmselect') {
                                d.searchOp = 'in';
                                formHtml += '<div ' + d.extend + ' lay-filter="xmSelect" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" style="height:32px;" class="' + d.class + '"></div>\n';
                            }else{
                                formHtml += '<input ' +d.extend +' lay-filter="'+d.filter+'" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n';
                            }
                            formHtml+= '</div>\n' + '</div>' + '</div>';
                            break;
                        case 'xmselect':
                            formHtml += '<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline ">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n';
                            d.searchOp = 'in';
                            formHtml += '<div ' + d.extend + ' lay-filter="xmSelect" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" style="height:32px;" class="' + d.class + '"></div>\n';
                            formHtml+= '</div>\n' + '</div>' + '</div>';
                            break;
                        case'select':
                            d.searchOp = '=';
                            var selectHtml = '';
                            d.selectList = d.selectList || Fun.api.getData(d.url) || {};
                            layui.each(d.selectList, function (i, v) {
                                var selected = '';
                                if (i === d.searchValue) {
                                    selected = 'selected=""'
                                }
                                selectHtml += '<option value="' + i + '" ' + selected + '>' + __(v) + '</option>/n'
                            });
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<select ' +d.extend +' lay-filter="'+d.filter+'" class="layui-select '+d.class+'" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '"   data-searchop="' + d.searchOp + '" >\n' + '<option value="">-' + __("All") + ' -</option> \n' + selectHtml + '</select>\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'between':
                            d.searchOp = 'between';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" data-search="' + d.search + '"   data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'not between':
                            d.searchOp = 'not between';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + eval(d.fieldAlias + '[]') + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + eval(d.fieldAlias + '[]') + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'range':
                            if (d.searchOp && d.searchOp === 'between') {
                                formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +'  id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" lay-filter="timePicker" data-timetype="' + d.timeType + '"  data-searchdateformat="' + d.searchdateformat + '"  data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"   data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '" lay-filter="timePicker"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>'
                            } else {
                                d.searchOp = 'range';
                                formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +'  id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="timePicker" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>'
                            }
                            break;
                        case'time':
                            if (d.searchOp && d.searchOp === 'between') {
                                formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" lay-filter="timePicker" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '" lay-filter="timePicker"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>'
                            } else {
                                formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-filter="timePicker" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>'
                            }
                            break;
                        case'timerange':
                            d.searchOp = 'range';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="timePicker" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break
                    }
                    newCols.push(d)
                }
            });
            if (formHtml !== '') {
                $('#' + tableId).before('<fieldset id="searchFieldList_' + tableId + '" class="layui-elem-field table-search-fieldset ' + show + '">\n' + '<legend>' + __('Search') + '</legend>\n' + '<form class="layui-form" id="layui-form-' + tableId + '"><div class="layui-row">\n' + formHtml + '<div class="layui-form-item layui-inline" style="margin-left: 80px;">\n' + '<button type="submit" class="layui-btn layui-btn-normal" data-type="tableSearch" data-tableid="' + tableId + '" lay-submit="submit" lay-filter="' + tableId + '_filter">' + __('Search') + '</button>\n' + '<button type="reset" class="layui-btn layui-btn-primary" data-type="tableReset"  data-tableid="' + tableId + '" lay-filter="' + tableId + '_filter">' + __('Reset') + '</button>\n' + '</div>' + '</div>' + '</form>' + '</fieldset>');
                Table.api.tableSearch(tableId);
                layui.form.render();
                Table.timeRender(newCols)
                Fu.events.xmSelect();
            }
        },
        timeRender: function (newCols) {
            layui.each(newCols, function (ncI, ncV) {
                if (ncV.search === 'range') {
                    switch (ncV.searchOp) {
                        case 'between':
                            layui.laydate.render({
                                elem: '[id="field_' + ncV.field + '_min"]',
                                format: ncV.searchdateformat,
                                type: ncV.timeType
                            });
                            layui.laydate.render({
                                elem: '[id="field_' + ncV.field + '_max"]',
                                format: ncV.searchdateformat,
                                type: ncV.timeType
                            })
                            break;
                        default:
                            layui.timePicker.render({
                                elem: '[name="' + ncV.field + '"]',
                                options: {timeStamp: false, format: ncV.timepickerformat},
                            })
                            break
                    }
                }
                if (ncV.search === 'time') {
                    switch (ncV.searchOp) {
                        case 'between':
                            layui.laydate.render({
                                elem: '[id="field_' + ncV.field + '_min"]',
                                format: ncV.searchdateformat,
                                type: ncV.timeType
                            });
                            layui.laydate.render({
                                elem: '[id="field_' + ncV.field + '_max"]',
                                format: ncV.searchdateformat,
                                type: ncV.timeType
                            })
                            break;
                        case 'range':
                            layui.timePicker.render({
                                elem: '[name="' + ncV.field + '"]',
                                options: {timeStamp: false, format: ncV.timepickerformat,},
                            })
                            break;
                        default:
                            layui.laydate.render({
                                elem: '[name="' + ncV.field + '"]',
                                type: ncV.timeType,
                                format: ncV.searchdateformat
                            })
                            break
                    }
                }
                if (ncV.search === 'timerange') {
                    layui.laydate.render({
                        elem: '[name="' + ncV.field + '"]',
                        range: true,
                        type: ncV.timeType,
                        format: ncV.searchdateformat
                    })
                }
            })
        },
        //格式化列
        colsRender: function (options) {
            var newclos = options.cols[0];
            layui.each(newclos, function (i, d) {
                newclos[i]['primaryKey'] = options.primaryKey;
                if (d.align === undefined) {
                    newclos[i]['align'] = 'center'
                }
                if (!d.filter) {
                    newclos[i]['filter'] = d.field
                }
                if (d.operat === undefined && d.templet === Table.templet.operat) {
                    newclos[i]['operat'] = ['edit', 'delete']
                }
                sortFields = ['id', 'sort'];
                if (d.sort === undefined && sortFields.indexOf(d.field) !== -1) {
                    newclos[i]['sort'] = true;
                }
                if (d.filter === undefined && d.templet === Table.templet.switch) {
                    newclos[i]['filter'] = d.field
                }
                if (d.imageHeight === undefined && (d.templet!==undefined && (d.templet == Table.templet.image || d.templet == Table.templet.images))) {
                    newclos[i]['imageHeight'] = 40;
                    newclos[i]['templet'] = Table.templet.image;
                }
                if (d.selectList !== undefined && d.search === undefined) {
                    newclos[i]['search'] = 'select'
                }
                if (d.selectList !== undefined && d.templet === undefined) {
                    newclos[i]['templet'] = Table.templet.select
                }
                if (d.field !== undefined && d.field.split(".").length > 1 && d.templet === undefined) {
                    newclos[i]['templet'] = Table.templet.resolution
                }
            })
            return [newclos]
        },
        templet: {
            time: function (d) {
                var ele = $(this)[0];
                var time = eval('d.' + ele.field);
                var format = ele.dateformat || 'yyyy-MM-dd HH:mm:ss';
                if (time && isNaN(Date.parse(time))) {
                    return layui.util.toDateString(time * 1000, format)
                } else if (time && !isNaN(Date.parse(time))) {
                    return layui.util.toDateString(Date.parse(time), format)
                } else {
                    return '-';
                }
            }, tags: function (d) {
                var ele = $(this)[0];
                var selectList = ele.selectList || Fun.api.getData(ele.url) || {};
                var content = eval('d.' + ele.field);
                op = d.search ? d.searchOp : '%*%';
                filter = {};
                ops = {};
                ops[ele.field] = op;
                op = JSON.stringify(ops);
                if (JSON.stringify(selectList) !== "{}" && content !== '' && content !== null) {
                    var reg = RegExp(/,/);
                    content = typeof content == 'string' && reg.test(content) ? content.split(',') : typeof content == 'object' ? content : [content];
                    html = '';
                    layui.each(content, function (i, v) {
                        filter[ele.field] = v;
                        filter = JSON.stringify(filter);
                        if (selectList[v]) {
                            html += Table.getBadge(d, ele, v, __(selectList[v])) + ' '
                        }
                    });
                    return html
                }
                filter[ele.field] = content;
                filter = JSON.stringify(filter);
                content = content ? __(content) : '-';
                return "<span lay-event='search'  data-filter='" + filter + "' data-op='" + op + "' data-tips='" + content + "' title='" + content + "' class='layui-btn layui-btn-xs layui-search layui-table-tags'>" + content + "</span>"
            }, image: function (d) {
                var ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 40;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                var src = eval('d.' + ele.field);
                src = src ? src : '/static/common/images/image.gif';
                title = d[ele.title];
                src = src.split(',');
                var html = [];
                layui.each(src, function (i, v) {
                    v = v ? v : '/static/common/images/image.gif';
                    html.push('<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + v + '" title="' + title + '"  lay-event="photos" alt="">')
                });
                return html.join(' ')
            }, content: function (d) {
                var ele = $(this)[0];
                var content = Table.templet.resolution(d, ele)
                return "<div style='white-space: nowrap; text-overflow:ellipsis; overflow: hidden; max-width:80px;'>" + content + "</div>"
            }, text: function (d) {
                var ele = $(this)[0];
                return Table.templet.resolution(d, ele)
            }, selects: function (d) {
                var ele = $(this)[0];
                ele.selectList = ele.selectList || Fun.api.getData(ele.url) || {};
                value = Table.templet.resolution(d, ele)
                $html = '<div class="layui-table-select"><select data-id="'+d[d.LAY_COL.primaryKey]+'" name="' + ele.field + '" lay-filter="' + ele.field + '"  lay-search="">\n' +
                    '<option value="">' + __('Select') + '</option>\n'
                layui.each(ele.selectList, function (i, v) {
                    selected = value === i ? 'selected="selected"' : '';
                    $html += '<option ' + selected + ' value="' + i + '">' + ele.selectList[i] + '</option>'
                })
                $html += '</select></div><script>$(".layui-table-box, .layui-table-body").css("overflow","visible");$(".layui-table-select").parent("div").css("overflow","visible")</script>';
                return $html;
            }, select: function (d) {
                var ele = $(this)[0];
                ele.selectList = ele.selectList || Fun.api.getData(ele.url) || {};
                value = Table.templet.resolution(d, ele)
                if (ele.selectList[value] === undefined || ele.selectList[value] === '' || ele.selectList[value] == null) {
                    return Table.getBadge(d, ele, value, __(value), 2)
                } else {
                    return Table.getBadge(d, ele, value, __(ele.selectList[value]), 2)
                }
            }, url: function (d) {
                var ele = $(this)[0];
                var value = Table.templet.resolution(d, ele);
                html = '';
                if(value){
                    value = value.split(',');
                    for(var i=0;i<value.length;i++){
                        html+='<a class="layui-table-url layui-btn-normal layui-btn layui-btn-xs" href="' + value[i] + '" target="_blank" class="label bg-green"> <i class="layui-icon layui-icon-link"></i>  </a>'
                    }
                }
                return html;
            }, icon: function (d) {
                var ele = $(this)[0];
                var icon = Table.templet.resolution(d, ele);
                return '<i class="' + icon + '"></i>'
            }, switch: function (d) {
                var ele = $(this)[0];
                ele.filter = ele.filter || ele.field || null;
                ele.selectListTips = ele.selectList && JSON.stringify(ele.selectList) !== '{}' ? __(ele.selectList[1]) + '|' + __(ele.selectList[0]) : '';
                ele.tips = ele.tips || ele.selectListTips || __('open') + '|' + __('close');
                var value = Table.templet.resolution(d, ele);
                var checked = value > 0 ? 'checked="checked"' : '';
                return '<input type="checkbox" name="' + ele.field + '" value="' + d[d.LAY_COL.primaryKey] + '" lay-skin="switch" lay-text="' + ele.tips + '" lay-filter="' + ele.filter + '" ' + checked + ' >'
            }, resolution: function (d, ele = '') {
                var ele = ele || $(this)[0];
                ele.field = ele.field || ele.filter || null;
                return eval('d.' + ele.field) ? eval('d.' + ele.field) : '-'
            }, number: function (d) {
                var ele = $(this)[0];
                var toFixed = ele.toFixed || 2;
                value = Table.templet.resolution(d, ele)
                return value === '-' ? value : parseFloat(value).toFixed(toFixed)
            }, operat: function (d) {
                d.primaryKey = typeof d.LAY_COL!=='undefined'?d.LAY_COL.primaryKey:'id';
                d.primaryKeyValue = d[d.primaryKey];
                var ele = $(this)[0];
                ele.operat = ele.operat || ['edit', 'delete'];
                var html = '';
                var requests = Table.init.requests;
                layui.each(ele.operat, function (k, v) {
                    var vv = {};
                    var va = {};
                    if (v === 'add' || v === 'edit' || v === 'delete' || v === 'destroy' || v === 'restore' || (typeof v !== "object" && typeof eval('requests.' + v + '_url') === 'string')) {
                        if (v === 'add') {
                            va = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Add'),
                                title: __('Add'),
                                url: requests.add_url,
                                icon: 'layui-icon layui-icon-add-circle-fine',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        } else if (v === 'edit') {
                            va = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-xs',
                                text: __('Edit'),
                                title: __('Edit'),
                                url: requests.edit_url,
                                icon: 'layui-icon layui-icon-edit',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        } else if (v === 'delete') {
                            va = {
                                type: 'delete',
                                event: 'request',
                                class: 'layui-btn layui-btn-danger',
                                text: __('Are you sure to delete'),
                                title: __('Delete'),
                                url: requests.delete_url,
                                icon: 'layui-icon layui-icon-delete',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        } else if (v === 'destroy') {
                            va = {
                                type: 'delete',
                                event: 'request',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Are you sure to Destroy'),
                                title: __('Destroy'),
                                url: requests.destroy_url,
                                icon: 'layui-icon layui-icon-fonts-clear',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        } else if (v === 'restore') {
                            va = {
                                type: 'request',
                                event: 'request',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Are you sure to restore'),
                                title: __('Restore'),
                                url: requests.restore_url,
                                icon: 'layui-icon layui-icon-refresh-1',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        } else {
                            va = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Open'),
                                title: __('Open'),
                                url: eval('requests.' + v + '_url'),
                                icon: 'layui-icon layui-icon-rate',
                                extend: "",
                                width: '800',
                                height: '600',
                            }
                        }
                        vv.type = va.type || '';
                        vv.class = va.class || '';
                        vv.event = va.event || va.event || '';
                        vv.icon = va.icon || '';
                        vv.url = va.url || '';
                        vv.text = va.text || '';
                        vv.title = va.title || va.text || '';
                        vv.extend = va.extend || '';
                        vv.extend = typeof vv.extend === "object" ? "data-extend='" + JSON.stringify(vv.extend) + "'" : vv.extend;
                        vv.node = vv.node === false ? vv.node : Fun.common.getNode(va.url);
                        vv.class = vv.class ? vv.class + ' layui-btn-xs' : vv.class;
                        vv.url = vv.url.indexOf("?") !== -1 ? vv.url + '&id=' + d.primaryKeyValue : vv.url + '?id=' + d.primaryKeyValue;
                        vv.url = Fun.replaceurl(vv.url, d);
                        vv.width = va.width !== '' ? 'data-width="' + va.width + '"' : '';
                        vv.height = va.height !== '' ? 'data-height="' + va.height + '"' : '';
                        vv.type = vv.type !== '' ? 'data-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon !== '' ? '<i class="' + vv.icon + '"></i>' : '';
                        vv.class = vv.class !== '' ? 'class="layui-event-tips ' + vv.class + '" ' : '';
                        vv.url = vv.url !== '' ? 'data-url="' + vv.url + '" title="' + vv.title + '"' : '';
                        if (!vv.icon) vv.icon = vv.icon + vv.title;
                        vv.title = vv.title !== '' ? 'data-title="' +vv.title + '" title="' + vv.title + '"' : '';
                        vv.event = vv.event !== '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.tableid = 'data-tableid="' + Table.init.table_elem + '"';
                        vv.text = 'data-text="' + vv.text + '"';
                        if (vv.node === false || (vv.node && Fun.checkAuth(vv.node, '#' + Table.init.tableId))) {
                            html += '<button ' + vv.class + vv.tableid + vv.width + vv.height + vv.url + vv.event + vv.type + vv.extend + vv.text + '>' + vv.icon + '</button>'
                        }
                    } else if (typeof v === 'string' && typeof eval('requests.' + v) === "object" || typeof v === 'object') {
                        if (typeof v === 'string') {
                            va = eval('requests.' + v)
                        } else {
                            va = v
                        }
                        vv = {};
                        vv.type = va.type || '';
                        vv.class = va.class || '';
                        vv.class = vv.class ? ' layui-btn layui-btn-xs '+vv.class  : vv.class;
                        vv.full = va.full || '';
                        vv.btn = va.btn || '';
                        vv.align = va.align || '';
                        vv.width = va.width || '';
                        vv.height = va.height || '';
                        vv.event = va.event || vv.type || '';
                        vv.icon = va.icon || '';
                        vv.url = va.url || '';
                        vv.text = va.text || '';
                        vv.title = va.title || vv.text || '';
                        vv.extend = va.extend || '';
                        vv.extend = typeof vv.extend === "object" ? "data-extend='" + JSON.stringify(vv.extend) + "'" : vv.extend;
                        vv.node = va.node === false ? va.node : Fun.common.getNode(va.url);
                        vv.url = va.url.indexOf("?") !== -1 ? va.url + '&id=' + d.primaryKeyValue : va.url + '?id=' + d.primaryKeyValue;
                        vv.url = Fun.replaceurl(vv.url, d);
                        vv.width = vv.width !== '' ? 'data-width="' + vv.width + '"' : '';
                        vv.height = vv.height !== '' ? 'data-height="' + vv.height + '"' : '';
                        vv.type = vv.type !== '' ? 'data-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon !== '' ? '<i class="layui-icon ' + vv.icon + '"></i>' : '';
                        vv.icon = vv.icon + vv.title;
                        vv.class = vv.class ? 'class="layui-event-tips ' + vv.class + '"' : vv.class;
                        vv.url = vv.url !== '' ? 'data-url="' + vv.url + '" title="' + vv.title + '"' : '';
                        vv.title = vv.title !== '' ? 'data-title="' +vv.title + '" title="' + vv.title + '"' : '';
                        vv.event = vv.event !== '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.full = vv.full !== '' ? 'data-full="' + vv.full + '" ' : '';
                        vv.btn = vv.btn !== '' ? 'data-btn="' + vv.btn + '" ' : '';
                        vv.align = vv.align !== '' ? 'data-align="' + vv.align + '" ' : '';
                        vv.tableid = 'data-tableid="' + Table.init.table_elem + '"';
                        vv.text = 'data-text="' + vv.text + '"';
                        if (vv.node === false || (vv.node && Fun.checkAuth(vv.node, '#' + Table.init.tableId))) {
                            html += '<button ' + vv.tableid + vv.class + vv.width + vv.height + vv.text + vv.title + vv.url + vv.event + vv.type + vv.extend + vv.full + vv.btn + vv.align + '>' + vv.icon + '</button>'
                        }
                    }
                });
                return html
            },
        },
        on: function (filter) {
        },
        getBadge: function (d, ele, key = 0, value = '', type = 1) {
            op = d.search ? d.searchOp : '%*%';
            var badge = [
                '<span class="layui-badge-dot" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-blue" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-green" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-black" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-orange" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-cyan" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-plum"  title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-yellow"  title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-pink" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-purple" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-brown" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-violet" title="' + value + '"></span> ' + value,
                '<span class="layui-badge-dot layui-bg-gray" title="' + value + '"></span> ' + value,
            ];
            if (type === 2) {
                badge = [

                    '<span class="layui-badge" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-blue" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-green" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-black" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-orange" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-cyan" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-plum" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-yellow" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-pink" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-purple" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-brown" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-violet" title="' + value + '">' + value + '</span>',
                    '<span class="layui-badge layui-bg-gray" title="' + value + '">' + value + '</span>',

                ]
            }
            var filter = {}, ops = {};
            filter[ele.field] = key;
            ops[ele.field] = op;
            filter = JSON.stringify(filter);
            op = JSON.stringify(ops);
            if (badge[key]) {
                return "<span data-event='search' data-filter='" + filter + "'  data-op='" + op + "'  class='layui-search' data-tips='" + value + "'  title='" + value + "'>" + badge[key] + "</span>"
            } else {
                return "<span data-event='search'  data-filter='" + filter + "'  data-op='" + op + "' class='layui-search'  data-tips='" + value + "'  title='" + value + "'>" + badge[0] + "</span>"
            }
        },
        getIds: function (url, tableId) {
            url = url !== undefined ? Fun.url(url) : window.location.href;
            var checkStatus = layui.table.checkStatus(tableId), data = checkStatus.data;
            var ids = [];
            if (url.indexOf('id=all') !== -1) {
                ids = 'all';
                length = __('All')
            } else if (url.indexOf('id=') !== -1) {
                ids = [];
                length = 1
            } else if (data.length > 0) {
                layui.each(data, function (k, v) {
                    var  primaryKey = $('#'+tableId).data('primarykey');
                    ids.push(v[primaryKey])
                });
                length = ids.length
            }
            return [ids, length]
        },
        events: {
            iframe: function (othis, options = null) {
                if (options) {
                    Fun.api.iframe(options);
                    return
                }
                Fun.events.iframe(othis)
            }, open: function (othis, options = null) {
                if (options) {
                    Fun.api.open(options);
                    return ;
                }
                Fun.events.open(othis)
            }, photos: function (othis) {
                Fun.events.photos(othis)
            }, refresh: function (othis) {
                var tableId = othis.data('tableId');
                if (tableId === undefined || tableId === '' || tableId == null) {
                    tableId = Table.init.tableId
                }
                Table.api.reload(tableId)
            }, tabswitch: function (othis) {  //切换选项卡重载表格
                var field = othis.closest("[data-field]").data("field"), value = othis.data("value");
                $where = {};
                $where[field] = value;
                Table.api.reload(Table.init.tableId, $where);
                return false
            }, export: function (othis) {
                var url = othis.data('url');
                var dataField = $('#searchFieldList_' + Table.init.tableId + ' .layui-form [name]').serializeArray();
                var formatFilter = {}, formatOp = {};
                layui.each(dataField, function () {
                    var key = this.name, val = this.value;
                    if (val !== '') {
                        formatFilter[key] = val;
                        var op = $('#field_' + key).attr('data-searchop');
                        var min, max;
                        if ($('#field_' + key + '_min').length > 0) {
                            min = $('#field_' + key + '_min').val();
                            max = $('#field_' + key + '_max').val()
                        }
                        if (max || min) {
                            formatFilter[key] = min + ',' + max;
                            op = $('#field_' + key + '_min').attr('data-searchop')
                        }
                        formatOp[key] = op
                    }
                });
                if (url.indexOf('?') !== -1) {
                    where = "&filter=" + JSON.stringify(formatFilter) + '&op=' + JSON.stringify(formatOp)
                } else {
                    where = "?filter=" + JSON.stringify(formatFilter) + '&op=' + JSON.stringify(formatOp)
                }
                window.open(Fun.url(url + where), '_blank')
            }, request: function (othis, options = null) {
                Fun.events.request(othis, options, Table)
            }, delete: function (othis, options = null) {
                var tableId = othis.data('tableid');
                if (options) {
                    url = options.url;
                    tableId = options.tableId || Table.init.tableId
                } else {
                    url = othis.data('url');
                    tableId = tableId || Table.init.tableId
                }
                arr = Table.getIds(url, tableId);
                ids = arr[0]
                length = arr[1];
                if (length <= 0) {
                    Fun.toastr.error(__('Please check data'));
                    return false
                }
                Fun.toastr.confirm(__('Are you sure you want to delete the %s selected item?', length), function () {
                    Fun.ajax({url: url, data: {ids: ids},}, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            Table.api.reload(tableId)
                        })
                    }, function (res) {
                        Fun.toastr.error(res.msg)
                    })
                });
                return false
            }, dropdown: function (othis) {
                Fun.events.dropdown(othis, Table)
            }, closeOpen: function (othis) {
                Fun.api.close()
            }, common: function (othis) {
                callback = othis.data('callback');
                if (callback) {
                    callback = callback.replace('obj','othis').replace('_that','othis');
                    callback = callback.indexOf('(') !== -1 ? callback : callback + '(othis)';
                    callback = callback.indexOf('othis') !== -1 ? callback : callback.replace('(','(othis,');
                    eval(callback);
                    return true;
                }
                return true;
            },
        },
        api: {
            reload: function (tableId, $where, $deep = true, $parent = true) {
                tableId = tableId ? tableId : Table.init.tableId;
                $where = $where || {};
                $map = {where: $where}
                layui.table.reload(tableId, $map, $deep);
                if ($parent && parent.layer && parent.layer.getFrameIndex(window.name)) {
                    parent.layui.table.reload(tableId, {}, $deep);
                }
            },
            tableSearch: function (tableId) {
                layui.form.on('submit(' + tableId + '_filter)', function (data) {
                    var dataField = data.field;
                    var formatFilter = {}, formatOp = {};
                    layui.each(dataField, function (key, val) {
                        if (val !== '') {
                            formatFilter[key] = val;
                            var op = $('#field_' + key).attr('data-searchop');
                            var min, max;
                            if ($('#field_' + key + '_min').length > 0) {
                                min = $('#field_' + key + '_min').val();f
                                max = $('#field_' + key + '_max').val()
                            }
                            if (max || min) {
                                formatFilter[key] = min + ',' + max
                                op = $('#field_' + key + '_min').attr('data-searchop')
                            }
                            formatOp[key] = op
                        }
                    });
                    Table.api.reload(tableId, {
                        filter: JSON.stringify(formatFilter),
                        op: JSON.stringify(formatOp)
                    }, true, false);
                    return false
                })
            },
            switch: function (options) {
                url = options.init.requests.modify_url ? options.init.requests.modify_url : false;
                cols = options.cols[0] || {};
                tableId = options.id || Table.init.tableId;
                if (cols.length > 0) {
                    layui.each(cols, function (i, v) {
                        v.filter = v.filter || false;
                        if (v.filter !== false && url !== false) {
                            layui.form.on('switch(' + v.filter + ')', function (obj) {
                                var checked = obj.elem.checked ? 1 : 0;
                                var data = {id: obj.value, field: v.field, value: checked,};
                                Fun.ajax({url: url, prefix: true, data: data,}, function (res) {
                                    Fun.toastr.success(res.msg)
                                    Table.api.reload(tableId)
                                }, function (res) {
                                    obj.elem.checked = !checked;
                                    layui.form.render();
                                    Fun.toastr.error(res.msg)
                                }, function () {
                                })
                            })
                        }
                    })
                }
            },
            selects: function (options) {
                url = options.init.requests.modify_url ? options.init.requests.modify_url : false;
                cols = options.cols[0] || {};
                tableId = options.id || Table.init.tableId;
                if (cols.length > 0) {
                    layui.each(cols, function (i, v) {
                        v.filter = v.filter || false;
                        if (v.filter !== false && url !== false) {
                            layui.form.on('select(' + v.filter + ')', function (obj) {
                                //兼容表单
                                if($(obj.othis).parents('form').length>0){return false;}
                                var id = $(obj.elem).attr('data-id');
                                var data = {id: id, field: v.field, value: obj.value};
                                Fun.ajax({url: url, prefix: true, data: data,}, function (res) {
                                    Fun.toastr.success(res.msg)
                                }, function (res) {
                                    layui.form.render();
                                    Fun.toastr.error(res.msg);
                                }, function () {
                                })
                                Table.api.reload(tableId);
                            })
                        }
                    })
                }
            },
            toolbar: function (options) {
                tableId = options.id || Table.init.tableId;
                layui.table.on('toolbar(' + options.layFilter + ')', function (obj) {
                    var othis = $(this)
                    switch (obj.event) {
                        case'TABLE_SEARCH':
                            var searchFieldsetId = 'searchFieldList_' + tableId;
                            var _that = $("#" + searchFieldsetId);
                            if (_that.hasClass("layui-hide")) {
                                _that.removeClass('layui-hide')
                            } else {
                                _that.addClass('layui-hide')
                            }
                            break;
                        case'refresh':
                            Table.events.refresh(othis);
                            break;
                        case'delete':
                            Table.events.delete(othis);
                            break;
                        case'destroy':
                            Table.events.destroy(othis);
                            break;
                        case'open':
                            Table.events.open(othis);
                            break;
                        case'export':
                            Table.events.export(othis);
                            break;
                        case'request':
                            Table.events.request(othis);
                            break;
                        case'iframe':
                            Table.events.iframe(othis);
                            break;
                        case'dropdown':
                            Table.events.dropdown(othis);
                            break;
                        default:
                            Table.events.common(othis)
                    }
                })
            },
            rowDouble: function (options) {
                var layFilter = options.layFilter, ops = options.init.requests.edit_url;
                layui.table.on('rowDouble(' + layFilter + ')', function (obj) {
                    url = typeof ops==="object"?ops.url:ops;
                    if (url && Fun.checkAuth(Fun.common.getNode(url), options.elem)) {
                        url = url.indexOf('?') !== -1 ? url + '&id=' + obj.data[options.primaryKey] : url + '?id=' + obj.data[options.primaryKey];
                        var opt = {};if(typeof ops==="object"){opt = ops;}
                        opt.url = url;opt.type = opt.hasOwnProperty("type") && opt.type==1?1:2;
                        Fun.api.open(opt);
                    }
                    return false
                })
            },
            edit: function (options) {
                url = options.init.requests.modify_url ? options.init.requests.modify_url : false;
                tableId = options.id || Table.init.tableId;
                if (url !== false) {
                    layui.table.on('edit(' + options.layFilter + ')', function (obj) {
                        var value = obj.value, data = obj.data, id = data[options.primaryKey], field = obj.field;
                        var _data = {id: id, field: field, value: value,};
                        Fun.ajax({url: url, prefix: true, data: _data,}, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Table.api.reload(tableId)
                            })
                        }, function (res) {
                            Fun.toastr.error(res.msg, function () {
                                Table.api.reload(tableId)
                            })
                        }, function () {
                            Table.api.reload(tableId)
                        })
                    })
                }
            }, sort: function (options) {
                tableId = options.id || Table.init.tableId;
                layui.table.on('sort(' + tableId + ')', function (obj) {
                    $where ={
                        field: obj.field
                        ,order: obj.type //排序方式
                    };
                    Table.api.reload(tableId,$where)
                })
            },
            bindEvent: function () {
            //原来的点击事件失效改为此处
             	layui.table.on('tool('+Table.init.table_elem+')', function (obj) {
                    var _that = $(this);
                    var  data = obj.data; //获得当前行数据
                    var attrEvent = obj.event || _that.attr('lay-event'); //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that)
                    } else {
                        Table.events.common(_that);
                    }
                    return false;
                });
                $(document).on('click','*[lay-event]',function(){
                    var _that = $(this);
                    var attrEvent = _that.attr('lay-event'); //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that)
                    } else {
                        Table.events.common(_that);
                    }
                    return false;
                });
                //单元格工具事件 - 双击触发 注：v2.7.0 新增
                layui.table.on('toolDouble('+Table.init.tableId+')', function(obj){
                    // 用法跟 tool 事件完全相同
                    // 这里写你的逻辑
                });
                //重置按钮，重新刷新表格
                $(document).on('click', 'button[type="reset"]', function () {
                    Table.api.reload($(this).data('tableid'), {}, false)
                });
                $(document).on('blur', '#layui-input-search', function (event) {
                    var text = $(this).val();
                    var name = $(this).prop('name').split(',');
                    if (name.length === 1) {
                        var formatFilter = {}, formatOp = {};
                        formatFilter[name] = text;
                        formatOp[name] = $(this).data('searchop') || '%*%';
                        Table.api.reload(Table.init.tableId, {
                            filter: JSON.stringify(formatFilter),
                            op: JSON.stringify(formatOp)
                        }, true, false);
                        $('#layui-input-search').prop("value", $(this).val());
                        return false
                    } else {
                        Table.api.reload(tableId, {search: text, searchName: name}, true, true);
                        $('#layui-input-search').prop("value", $(this).val());
                        return false
                    }
                }).unbind('blur', '#layui-input-search', function (event) {
                    return false
                })
            },
        }
    };
    return Table;
})
