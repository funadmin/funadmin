// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

define(['jquery', 'timePicker'], function ($, timePicker) {
    var Table = {
        init: {table_elem: 'list', tableId: 'list', searchInput: true, requests: {export_url: 'ajax/export',import_url:"ajax/import"},},
        render: function (options) {
            options.elem = options.elem || '#' + options.init.table_elem;
            options.primaryKey = options.primaryKey || $('#'+options.id).data('primarykey') || 'id';
            options.init = options.init || Table.init;
            options.id = options.id || options.init.tableId;
            options.layFilter = options.id;
            options.url = options.url || window.location.href;
            options.toolbar = options.toolbar || '#toolbar';
            options.search = Fun.param(options.search, true);
            options.searchFormTpl = Fun.param(options.searchFormTpl || options.init.searchFormTpl, false);
            options.searchShow = Fun.param(options.searchShow || options.init.searchShow, false);
            options.rowDouble =  !(options.rowDouble != undefined && options.rowDouble == false && (options.init.rowDouble == undefined || (options.init.rowDouble == false)));
            options.searchInput = !(options.searchInput != undefined && options.searchInput == false && (options.init.searchInput == undefined || (options.init.searchInput == false)));
            options.searchName = Fun.param(options.searchName || options.init.searchName, 'id');
            options.searchOp = Fun.param(options.searchOp || options.init.searchOp, '%*%');
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
            options.toolbar = options.toolbar || ['refresh','add', 'delete', 'export', 'import' ,'recycle'];
            if (options.search === true && options.searchFormTpl !== false) {
                data = options.tpldata || {}
                layui.laytpl($('#' + options.searchFormTpl).html()).render(data, function (html) {
                    $('#' + options.id).before(html);
                    Table.api.tableSearch(options);cols = [];
                    var formVal = $('#layui-form-' + options.id + ' [name]').serializeArray()
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
                        cols.push(arr);
                    })
                    Table.timeRender(cols);
                    layui.form.render();
                    require(['form'], function (Form) {
                        Form.events.xmSelect();
                    })
                });
                layui.form.render();
            }
            if (options.search === true && options.searchFormTpl === false) {
                Table.renderSearch(options);
            }
            //修改或添加主键id
            $('#'+options.id).attr('data-primarykey',options.primaryKey);
            //是否字符串自定义模板
            options.toolbar = typeof options.toolbar === 'string' ? options.toolbar : Table.renderToolbar(options);
            var format = Table.getSearchField(layui.form.val('layui-form-'+options.id));
            options.where =  options.where || {filter:JSON.stringify(format.formatFilter), op:JSON.stringify(format.formatOp)};
            options.done = options.done || Table.done;
            if(options.tree){
                var newTable = layui.treeTable.render(options);
            }else{
                var newTable = layui.table.render(options);
            }
            Table.api.switch(options);
            Table.api.selects(options);
            Table.api.toolbar(options);
            Table.api.sort(options);
            Table.api.tool(options);
            Table.api.toolDouble(options);
            Table.api.import(options);
            if (options.rowDouble) {
                Table.api.rowDouble(options);
            }
            Table.api.edit(options);
            return newTable;
        },
        renderToolbar: function (options) {
            var d = options.toolbar, tableId = options.id,init = options.init, searchInput = options.searchInput;requests = options.init.requests;
            d = d || [];options.buttons = [];
            var toolbarHtml = '';
            var nodeArr = ['refresh','add', 'delete', 'destroy', 'export', 'import', 'recycle', 'restore'];
            layui.each(d, function (i, v) {
                if ($.inArray(v, nodeArr) !== -1) {
                    if (v !== 'refresh') url = Fun.replaceurl(eval('requests.' + v + '_url'), d);
                    if (v === 'refresh') {
                        toolbarHtml += ' <a class="layui-btn layui-btn-sm layui-btn-normal" data-tips="refresh" lay-event="refresh" data-tableid="' + tableId + '"><i class="layui-icon layui-icon-refresh"></i> </a>\n';
                    } else if (v === 'add') {
                        if (Fun.checkAuth('add', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm" data-tips="add" lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '" title="' + __('Add') + '"><i class="layui-icon layui-icon-add-circle-fine"></i>' + __('Add') + '</a>\n';
                        }
                    } else if (v === 'delete') {
                        if (Fun.checkAuth('delete', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger" data-tips="delete" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure to delete') + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</a>\n';
                        }
                    } else if (v === 'destroy') {
                        if (Fun.checkAuth('destroy', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" data-tips="destroy" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure  to destroy') + '"><i class="layui-icon layui-icon-delete"></i>' + __('Destroy') + '</a>\n';
                        }
                    } else if (v === 'export') {
                        if (Fun.checkAuth('export', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger"  data-tips="export"  lay-event="export" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-export"></i>' + __('Export') + '</a>\n';
                        }
                    } else if (v === 'import') {
                        if (Fun.checkAuth('import', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-normal"  data-tips="import"  lay-event="import" data-exts="csv,xls,xlsx" data-accept="*" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-upload-drag"></i>' + __('Import') + '</a>\n';
                        }
                    } else if (v === 'recycle') {
                        if (Fun.checkAuth('recycle', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-normal" data-tips="recycle" lay-event="open" data-btn="close" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-find-fill"></i>' + __('Recycle') + '</a>\n';
                        }
                    } else if (v === 'restore') {
                        if (Fun.checkAuth('restore', options.elem)) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" data-tips="restore"  lay-event="request" data-tableid="' + tableId + '"  data-url="' + url + '" data-text="' + __('Are you sure restore') + '"><i class="layui-icon layui-icon-find-fill"></i>' + __('Restore') + '</a>\n';
                        }
                    }
                } else if (typeof v === 'string' && (typeof eval('requests.' + v)=== 'string' || typeof eval('requests.' + v+ '_url')=== 'string')) {
                    if (Fun.checkAuth(v, options.elem)) {
                        url = eval(('requests.' + v + '_url'))  ||　eval(('requests.' + v ))
                        if(!url) return ;
                        url = Fun.replaceurl(url, d);
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-set-sm"></i>' + __(v) + '</a>\n';
                    }
                } else if (typeof v === 'string' && typeof eval('requests.' + v) === 'object' || typeof v === 'object') {
                    if (typeof v === 'string') {
                        v = eval('requests.' + v);
                    }
                    if(!v) return ;
                    url = Fun.replaceurl(v.url, d);
                    v.node = v.node === false ? v.node : Fun.common.getNode(v.url);
                    if (v.node === false || Fun.checkAuth(v.node, options.elem)) {
                        v = Table.getButtonsOptions(v);
                        v.tableid = init.table_elem ;
                        v.buttonsindex = i;v.rowindex = i;
                        options.buttons[i] = v;
                        icon = v.icon !== '' ? '<i class="layui-icon ' + v.icon + '"></i>' : '';
                        icon = icon + v.title;title = 'title="' + v.title + '"';
                        cls = v.class ? ' layui-btn layui-btn-sm '+v.class  : v.class;cls = 'class="' + cls + '"';
                        cls = cls.replaceAll('layui-btn-xs','');
                        var dataAttr = '';vv= v;
                        layui.each(vv, function (j, jj) {
                            if(j!=='callback'){
                                if(j==='btn' && v[j]!==undefined){
                                    dataAttr+=" data-"+j +"='"+vv[j]+"'";
                                }else if(j==='event'){
                                    dataAttr+=" lay-"+j +"='"+vv[j]+"'";
                                }else if(j==='extend' && typeof vv[j] ==='object'){
                                    dataAttr+=" data-"+j +"='"+JSON.stringify(vv[j])+"'";
                                }else if(j==='extend' && typeof vv[j] ==='string'){
                                    dataAttr+=" "+ vv[j]+" ";
                                }else{
                                    dataAttr+=" data-"+j +"='"+vv[j]+"'";
                                }
                            }
                        })
                        //会影响后面行
                        toolbarHtml += "<a "+ dataAttr + cls+ ">"+icon+'</a>\n';

                    }
                }
            });
            if (searchInput) {
                toolbarHtml += '<input id="layui-input-search-'+options.id+'" data-searchop="'+options.searchOp+'" name="' + options.searchName + '" value="" placeholder="' + __('Search') + '" class="layui-input layui-hide-xs" style="display:inline-block;width:auto;float: right;\n' + 'margin:2px 25px 0 0;height:30px;">\n';
            }
            return '<div>' + toolbarHtml + '</div>';
        },
        renderSearch: function (options) {
            tableId = options.id;
            cols = options.cols;
            show = Fun.param(options.searchShow, false) ? '' : 'layui-hide';
            cols = cols[0] || {};
            var newCols = [];
            var formHtml = '',formVal = {};
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
                    formVal[d.field] = d.searchValue;
                    d.search = typeof d.search ==='string' ?d.search.toLowerCase():d.search;
                    cls = 'layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3';
                    switch (d.search) {
                        case true:
                            formHtml += '<div class="'+cls+'">' + '<div class="layui-form-item layui-inline ">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n';
                            if (d.filter && d.filter.toLowerCase() == 'xmselect') {
                                d.searchOp = 'in';
                                formHtml += '<div ' + d.extend + ' lay-filter="xmSelect" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" style="height:32px;" class="' + d.class + '"></div>\n';
                            }else{
                                formHtml += '<input ' +d.extend +' lay-filter="'+d.filter+'" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n';
                            }
                            formHtml+= '</div>\n' + '</div>' + '</div>';
                            break;
                        case 'xmselect':
                            formHtml += '<div class="'+cls+'">' + '<div class="layui-form-item layui-inline ">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n';
                            d.searchOp = 'in';
                            formHtml += '<div ' + d.extend + ' lay-filter="xmSelect" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" style="height:32px;" class="' + d.class + '"></div>\n';
                            formHtml+= '</div>\n' + '</div>' + '</div>';
                            break;
                        case 'selectpage':
                            formHtml += '<div class="'+cls+'">' + '<div class="layui-form-item layui-inline ">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n';
                            formHtml += "<input " +d.extend + " data-url='"+ (d.url!==undefined?d.url:"")  + "'" + 'lay-filter="selectPage" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n';
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
                            formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<select ' +d.extend +' lay-filter="'+d.filter+'" class="layui-select '+d.class+'" id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-search="' + d.search + '"   data-searchop="' + d.searchOp + '" >\n' + '<option value="">-' + __("All") + ' -</option> \n' + selectHtml + '</select>\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'between':
                            d.searchOp = 'between';
                            formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" data-search="' + d.search + '"   data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'not between':
                            d.searchOp = 'not between';
                            formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + eval(d.fieldAlias + '[]') + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + eval(d.fieldAlias + '[]') + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'range':
                            if (d.searchOp && d.searchOp === 'between') {
                                formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +'  id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" lay-filter="time" data-timetype="' + d.timeType + '"  data-searchdateformat="' + d.searchdateformat + '"  data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"   data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '" lay-filter="time"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            } else {
                                d.searchOp = 'range';
                                formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +'  id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="time" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>'
                            }
                            break;
                        case'time':
                            if (d.searchOp && d.searchOp === 'between') {
                                formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline layui-between">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '_min" name="' + d.fieldAlias + '" lay-filter="time" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '<div class="layui-input-inline layui-col-xs4">\n' + '<input id="field_' + d.fieldAlias + '_max" name="' + d.fieldAlias + '" lay-filter="time"  data-searchop="between"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            } else {
                                formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '"  lay-filter="time" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            }
                            break;
                        case'timerange':
                            d.searchOp = 'range';
                            formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="time" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                        case'date':
                            d.searchOp = 'daterange';
                            formHtml += '\t<div class="'+cls+'">' + '<div class="layui-form-item layui-inline">\n' + '<label class="layui-form-label layui-col-xs4 ">' + __(d.title) + '</label>\n' + '<div class="layui-input-inline layui-col-xs8">\n' + '<input ' +d.extend +' id="field_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="time" data-timetype="' + d.timeType + '" data-searchdateformat="' + d.searchdateformat + '" data-timepickerformat="' + d.timepickerformat + '" data-search="' + d.search + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input '+d.class+'">\n' + '</div>\n' + '</div>' + '</div>';
                            break;
                    }
                    newCols.push(d);
                }
            });
            if (formHtml !== '') {
                $('#' + tableId).before('<fieldset id="layui-search-field-' + tableId + '" class="layui-elem-field layui-search-fieldset ' + show + '">\n' + '<legend>' + __('Search') + '</legend>\n' + '<form class="layui-form" lay-filter="layui-form-' + tableId + '" id="layui-form-' + tableId + '"><div class="layui-row">\n' + formHtml + '<div class="layui-form-item layui-inline" style="margin-left: 80px;">\n' + '<button type="submit" class="layui-btn layui-btn-normal" data-type="tableSearch" data-tableid="' + tableId + '" lay-submit="submit" lay-filter="' + tableId + '_filter">' + __('Search') + '</button>\n' + '<button type="reset" class="layui-btn layui-btn-primary" data-type="tableReset"  data-tableid="' + tableId + '" lay-filter="' + tableId + '_filter">' + __('Reset') + '</button>\n' + '</div>' + '</div>' + '</form>' + '</fieldset>');
                Table.api.tableSearch(options);
                layui.form.val('layui-form-'+tableId,formVal);
                layui.form.render();
                Table.timeRender(newCols)
                require(['form'], function (Form) {
                    Form.events.xmSelect();
                    Form.events.selectpage();
                })
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
                if (ncV.search === 'time' || ncV.search === 'date') {
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
                        case 'daterange':
                            layui.timePicker.render({
                                elem: '[name="' + ncV.field + '"]',
                                options: {timeStamp: false, format: ncV.timepickerformat,},
                            });
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
                d.init = options.init;
                newclos[i]['primaryKey'] = options.primaryKey;
                if (d.align === undefined) {
                    newclos[i]['align'] = 'center';
                }
                if (!d.filter) {
                    newclos[i]['filter'] = d.field;
                }
                if (d.operat === undefined && d.templet === Table.templet.operat) {
                    newclos[i]['operat'] = ['edit', 'delete']
                }
                sortFields = ['id', 'sort'];
                if (d.sort === undefined && sortFields.indexOf(d.field) !== -1) {
                    newclos[i]['sort'] = true;
                }
                if (d.filter === undefined && d.templet === Table.templet.switch) {
                    newclos[i]['filter'] = d.field;
                }
                if (d.imageHeight === undefined && (d.templet!==undefined && (d.templet == Table.templet.image || d.templet == Table.templet.images))) {
                    newclos[i]['imageHeight'] = 40;
                    newclos[i]['templet'] = Table.templet.image;
                }
                if (d.selectList !== undefined && d.search === undefined) {
                    newclos[i]['search'] = 'select';
                }
                if (d.selectList !== undefined && d.templet === undefined) {
                    newclos[i]['templet'] = Table.templet.select;
                }
                if (d.field !== undefined && d.field.split(".").length > 1 && d.templet === undefined) {
                    newclos[i]['templet'] = Table.templet.resolution;
                }
            })
            return [newclos]
        },
        templet: {
            laydate:function (d) {
                var ele = $(this)[0];
                var value = eval-('d.' + ele.field) || '';
                ele.saveurl = ele.saveurl ||  ele.init.requests.modify_url || Table.init.requests.modify_url || "";
                var format = ele.dateformat || 'yyyy-MM-dd HH:mm:ss';
                return '<input lay-event="laydate" lay-filter="laydate" data-tableid="'+ele.init.tableId+'" data-field="'+ele.field+'" data-url="' +  ele.saveurl + '"  class="layui-input laydate"  data-dateformat="'+format+'" placeholder="'+__('select date')+'" value="'+ value+ '">';
            },
            colorpicker:function(d){
                var ele = $(this)[0];
                var value = eval('d.' + ele.field) || '';
                ele.saveurl = ele.saveurl ||  ele.init.requests.modify_url || Table.init.requests.modify_url || "";
                var color =JSON.stringify ({color: value});
                return "<div lay-event='colorpicker' lay-filter='colorPicker' data-tableid='"+ele.init.tableId+"' data-field='"+ele.field+"' data-url='" +  ele.saveurl + "'  class='colorpicker' placeholder='"+__('select color')+"' lay-options='"+color+"' value='"+ value+ "'></div>";
            },
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
            },tags: function (d) {
                var ele = $(this)[0];ele.url = ele.url?(ele.url.indexOf('?')!==-1?ele.url+'&'+ ele.primaryKey+'='+d[ele.primaryKey]:ele.url+'?'+ele.primaryKey+'='+d[ele.primaryKey]) :'';
                var selectList = ele.selectList || Fun.api.getData(ele.url) || {};
                var content = eval('d.' + ele.field), prop =  (ele.extend || '').match(/data\-(?:attr|prop)\s*=\s*("|')(.*?)\1/);
                if(prop){ prop = prop[2];}else{prop = ele.prop;}
                op = d.search ? d.searchOp : '%*%';
                filter = {};ops = {};
                ops[ele.field] = op;
                op = JSON.stringify(ops);
                if (JSON.stringify(selectList) !== "{}" && content !== '' && content !== null) {
                    var reg = RegExp(/,/);
                    content = typeof content == 'string' && reg.test(content) ? content.split(',') : typeof content == 'object' ? content : [content];
                    html = '';
                    layui.each(content, function (i, v) {
                        filter[ele.field] = v;
                        filter = JSON.stringify(filter);
                        if (prop) {
                            prop = prop.split(',')
                            layui.each(selectList, function (ii, vv) {
                                if(vv[prop[1]]==v){
                                    html += Table.getBadge(d, ele, v, __(vv[prop[0]])) + ' '
                                }
                            })
                        }else if (selectList[v]) {
                            html += Table.getBadge(d, ele, v, __(selectList[v])) + ' '
                        }
                    })
                    if(html){
                        return html
                    }
                }
                filter[ele.field] = content;
                filter = JSON.stringify(filter);
                content = content ? __(content) : '-';
                return "<span lay-event='search'  data-filter='" + filter + "' data-op='" + op + "' data-tips='" + content + "' title='" + content + "' class='layui-btn layui-btn-xs layui-search layui-table-tags'>" + content + "</span>"
            },image: function (d) {
                var ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 40;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                var src = eval('d.' + ele.field);
                src = src ? src : '/static/common/images/image.png';
                title = d[ele.title] || src;
                src = src.split(',');
                var html = [];
                layui.each(src, function (i, v) {
                    v = v ? v : '/static/common/images/image.png';
                    html.push('<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + v + '" title="' + title + '"  lay-event="photos" alt="">')
                });
                return html.join(' ')
            },content: function (d) {
                var ele = $(this)[0];
                var content = Table.templet.resolution(d, ele)
                return "<div style='white-space: nowrap; text-overflow:ellipsis; overflow: hidden; max-width:80px;'>" + content + "</div>"
            },text: function (d) {
                var ele = $(this)[0];
                return Table.templet.resolution(d, ele)
            },dropdown: function (d) {
                var ele = $(this)[0];ele.url = ele.url?(ele.url.indexOf('?')!==-1?ele.url+'&'+ ele.primaryKey+'='+d[ele.primaryKey]:ele.url+'?'+ele.primaryKey+'='+d[ele.primaryKey]) :'';
                ele.selectList = ele.selectList || Fun.api.getData(ele.url) || {};
                value = Table.templet.resolution(d, ele);extend = [];
                init = ele.init;
                layui.each(ele.selectList, function (i, v) {
                    var url = ele.url || init.requests.modify_url || v.url;
                    if(url.indexOf('?')>=0){
                        url = url+"&"+ele.primaryKey+'='+d[ele.primaryKey]+'&field='+ele.field;
                    }else{
                        url = url+"?"+ele.primaryKey+'='+d[ele.primaryKey]+'&field='+ele.field;
                    }
                    extend.push({
                        field:ele.field,value:i,id:d[ele.primaryKey], url: url, title: ele.selectList[i] || v.title,
                        event: ele.event || v.event || 'request', icon: ele.icon || v.icon || "",class:ele.class,
                        callback: ele.callback || v.callback || '',templet:v.templet||ele.templet,
                    })
                })
                alert(1)
                return $html = "<a class= '' lay-event='dropdown' data-extend = '"+JSON.stringify(extend)+"' > "+ele.selectList[value]+"   <i class='layui-icon layui-icon-down layui-font-12'></i></a>";
            },selects: function (d) {
                var ele = $(this)[0];ele.url = ele.url?(ele.url.indexOf('?')!==-1?ele.url+'&'+ ele.primaryKey+'='+d[ele.primaryKey]:ele.url+'?'+ele.primaryKey+'='+d[ele.primaryKey]) :'';
                ele.selectList = ele.selectList || Fun.api.getData(ele.url) || {};ele.filter = ele.filter || ele.field;
                ele.saveurl = ele.saveurl ||  ele.init.requests.modify_url || Table.init.requests.modify_url || "";
                value = Table.templet.resolution(d, ele)
                $html = '<select class="layui-border" data-url="'+ ele.saveurl +'" data-id="'+d[ele.primaryKey]+'" name="' + ele.field + '" lay-filter="' + ele.filter  + '"   lay-search="">\n' +
                    '<option value="">' + __('Select') + '</option>\n'
                layui.each(ele.selectList, function (i, v) {
                    selected = value === i ? 'selected="selected"' : '';
                    $html += '<option ' + selected + ' value="' + i + '">' + __(ele.selectList[i]) + '</option>'
                })
                $html += '</select>';
                return $html;
            }, switch: function (d) {
                var ele = $(this)[0];ele.filter = ele.filter || ele.field || null;ele.saveurl = ele.saveurl ||  ele.init.requests.modify_url || Table.init.requests.modify_url || '' ;
                ele.selectListTips = ele.selectList && JSON.stringify(ele.selectList) !== '{}' ? __(ele.selectList[1]) + '|' + __(ele.selectList[0]) : '';
                ele.text = ele.text || ele.selectListTips || __('open') + '|' + __('close');
                ele.tips = ele.tips || 'switch';
                var value = Table.templet.resolution(d, ele);
                var checked = value > 0 ? 'checked="checked"' : '';
                return '<input data-url="' + ele.saveurl  + '" data-tips="'+ele.tips+'" type="checkbox" name="' + ele.field + '" value="' + d[ele.primaryKey] + '" lay-skin="switch" lay-text="' + ele.text + '" lay-filter="' + ele.filter + '" ' + checked + ' >'
            },select: function (d) {
                var ele = $(this)[0];ele.url = ele.url?(ele.url.indexOf('?')!==-1?ele.url+'&'+ ele.primaryKey+'='+d[ele.primaryKey]:ele.url+'?'+ele.primaryKey+'='+d[ele.primaryKey]) :'';
                ele.selectList = ele.selectList || Fun.api.getData((ele.url)) || {};
                value = Table.templet.resolution(d, ele);
                if (!ele.selectList[value]) {
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
            },iframe: function (d) {
                var ele = $(this)[0];
                var value = Table.templet.resolution(d, ele);
                html = '';url = ele.url || location.href;ele.primaryKey = ele.primaryKey || 'id';id = d[ele.primaryKey];
                if(value){
                    value = value.split(',');
                    for(var i=0;i<value.length;i++){
                        url = url.indexOf('?')!==-1?(url+"&"+ele.primaryKey+"="+id+"&value="+value[i]):(url+"?"+ele.primaryKey+"="+id+"&value="+value[i]);
                        html+='<a class="layui-table-url layui-font-blue" data-title="' + value[i] + '" lay-event="iframe" data-event="iframe" data-type="iframe" data-url="' + url + '"  class="label bg-green"> '+ value[i] +' </a>'
                    }
                }
                return html;
            },open: function (d) {
                var ele = $(this)[0];
                var value = Table.templet.resolution(d, ele);
                html = '';url = ele.url || location.href;ele.primaryKey = ele.primaryKey || 'id';id = d[ele.primaryKey];
                if(value){
                    value = value.split(',');
                    for(var i=0;i<value.length;i++){
                        url = url.indexOf('?')!==-1?(url+"&"+ele.primaryKey+"="+id+"&value="+value[i]):(url+"?"+ele.primaryKey+"="+id+"&value="+value[i]);
                        html+='<a class="layui-table-url layui-font-blue" data-title="' + value[i] +'" lay-event="open" data-event="open" data-type="open" data-url="' + url + '"  class="label bg-green">'+ value[i] +'</a>'
                    }
                }
                return html;
            },icon: function (d) {
                var ele = $(this)[0];
                var icon = Table.templet.resolution(d, ele);
                return '<i class="' + icon + '"></i>'
            }, resolution: function (d, ele) {
                var ele = ele || $(this)[0];
                ele.field = ele.field || ele.filter || null;
                return eval('d.' + ele.field) || eval('d.'+ele.field) === 0 ? eval('d.' + ele.field) : '-';
            }, number: function (d) {
                var ele = $(this)[0];
                var toFixed = ele.toFixed || 2;
                value = Table.templet.resolution(d, ele)
                return value === '-' ? value : parseFloat(value).toFixed(toFixed)
            }, operat: function (d) {
                var ele = $(this)[0];init = ele.init;
                d.primaryKey = ele.primaryKey ||'id';
                d.primaryKeyValue = d[d.primaryKey];
                var ele = $(this)[0];
                ele.operat = ele.operat || ['edit', 'delete'];
                var html = '';
                var requests = init['requests'] || d.init['requests'];
                if(typeof ele.operat=="object"){
                    var buttons=[];
                    layui.each(ele.operat, function (i, v) {
                        var vv = {};
                        var va = {};
                        if (typeof v === "string" && (typeof eval('requests.' + v + '_url') === 'string' || typeof eval('requests.' + v) === 'string')) {
                            if (v === 'add') {
                                va = {
                                    type: 'open',
                                    event: 'open',
                                    class: 'layui-btn layui-btn-warm',
                                    text: __('Add'),
                                    title: __('Add'),
                                    url: requests.add_url,
                                    icon: 'layui-icon layui-icon-add-circle-fine',
                                    extend: "", width: '800', height: '100%', tips: 'add',
                                }
                            } else if (v === 'edit' || v==='copy') {
                                icon = v==='edit'? 'layui-icon-edit': 'layui-icon-file-b';
                                va = {
                                    type: 'open',
                                    event: 'open',
                                    class: 'layui-btn layui-btn-normal',
                                    text: __(v),
                                    title: __(v),
                                    url: requests[v+'_url'],
                                    icon: 'layui-icon '+icon,
                                    extend: "", width: '800', height: '100%', tips: v,
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
                                    extend: "", width: '800', height: '100%',tips: 'delete',
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
                                    extend: "", width: '800', height: '100%',tips: 'destroy',
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
                                    extend: "", width: '800', height: '100%',tips: 'restore',
                                }
                            } else {
                                va = {
                                    type: 'open',
                                    event: 'open',
                                    class: 'layui-btn layui-btn-warm',
                                    text: __('Open'),
                                    title: __('Open'),
                                    url: eval('requests.' + v + '_url') || eval('requests.' + v),
                                    icon: 'layui-icon layui-icon-radio',
                                    extend: "", width: '800', height: '100%',tips: '',
                                }
                            }
                        } else if (typeof v === 'string' && typeof eval('requests.' + v) === "object" || typeof v === 'object') {
                            if (typeof v === 'string') {
                                va = eval('requests.' + v)
                            } else {
                                va = v
                            }
                        }
                        if($.isEmptyObject(va)){ return ;}
                        vv = Table.getButtonsOptions(va);
                        vv.hidden = typeof va.hidden === 'function' ? va.hidden.call(this,d) : (typeof va.hidden !== 'undefined' ? va.hidden : false);
                        vv.url = va.url.indexOf("?") !== -1 ? va.url + '&'+d.primaryKey+'=' + d.primaryKeyValue : va.url + '?'+d.primaryKey+'=' + d.primaryKeyValue;
                        vv.id = d.primaryKeyValue;vv.url = Fun.replaceurl(vv.url, d);vv.tableid = init.table_elem ;
                        vv.buttonsindex = i;vv.rowindex = i;
                        buttons[i] = vv;icon = vv.icon !== '' ? '<i class="layui-icon ' + vv.icon + '"></i>' : '';
                        icon = icon + vv.title;title = 'title="' + vv.title + '"';
                        cls = vv.class ? ' layui-btn layui-btn-xs '+vv.class  : vv.class;cls = 'class="' + cls + '"';
                        cls = cls.replaceAll('layui-btn-sm','');
                        var dataAttr = '';
                        layui.each(vv, function (j, jj) {
                            if(j!=='callback'){
                                if(j==='btn' && v[j]!==undefined){
                                    dataAttr+=" data-"+j +"='"+vv[j]+"'";
                                }else if(j==='event'){
                                    dataAttr+=" lay-"+j +"='"+vv[j]+"'";
                                }else if(j==='extend' && typeof vv[j] ==='object'){
                                    dataAttr+=" data-"+j +"='"+JSON.stringify(vv[j])+"'";
                                }else if(j==='extend' && typeof vv[j] ==='string'){
                                    dataAttr+=" "+ vv[j]+" ";
                                }else{
                                    dataAttr+=" data-"+j +"='"+vv[j]+"'";
                                }
                            }
                        })
                        if ( vv.hidden===false && (vv.node === false || (vv.node && Fun.checkAuth(vv.node, '#' + init.tableId)))) {
                            html += '<button ' + title + cls + dataAttr +' >' + icon + '</button>'
                        }
                    });
                    if(layui.table.cache[init.table_elem] && layui.table.cache[init.table_elem][d.LAY_INDEX]){
                        layui.table.cache[init.table_elem][d.LAY_INDEX]['buttons'] = buttons
                    }
                    return html;
                }else if(typeof ele.operat == 'string' || typeof ele.operat == 'function'){
                    tpl = ele.operat;
                    if(typeof ele.operat == 'function'){
                        tpl = ele.operat();
                    }else if(typeof ele.operat == 'string' && ele.operat.indexOf('#')===0){
                        tpl = $(ele.operat).html();
                    }
                    if(tpl){
                        layui.laytpl(tpl).render(d, function(html){
                             htmlTpl = html;
                        });
                        return htmlTpl;
                    }
                }
            },
        },
        on: function (filter) {

        },
        done: function (res, curr, count) {
            if($('.colorpicker').length>0){
                $('.colorpicker').trigger('click');
            }
        },
        getButtonsOptions:function(va){
            var vv = {};
            vv.type = va.type || '';
            vv.class = va.class || '';
            vv.full = va.full || '';
            vv.align = va.align || '';
            vv.width = va.width || '';
            vv.height = va.height || '';
            vv.event = va.event || vv.type || '';
            vv.icon = va.icon || '';
            vv.url = va.url || '';
            vv.text = va.text || '';
            vv.title = va.title || vv.text || '';
            vv.tips = va.tips || '';
            vv.extend = va.extend || '';
            vv.callback = va.callback || '';
            vv.node = va.node === false ? va.node : (va.node?va.node:Fun.common.getNode(va.url));
            if(typeof va.btn !=='undefined'){vv.btn = va.btn;}
            return vv;
        },
        getBadge: function (d, ele, key, value ,type) {
            key =  key!==undefined?key:0;
            value = value!==undefined?value:'';
            type = type!==undefined?type:1;
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
        getRowData:function(elem,tableId){
            var index = $(elem).closest('tr').data('index');
            return layui.table.cache[tableId][index] || {};
        },
        //获取搜索数据
        getSearchField:function(dataField){
            var format=[], formatFilter = {}, formatOp = {};
            layui.each(dataField, function (key, val) {
                if (val !== '') {
                    formatFilter[key] = val;
                    var op = $('#field_' + key).attr('data-searchop');
                    var min, max;
                    if ($('#field_' + key + '_min').length > 0) {
                        min = $('#field_' + key + '_min').val();
                        max = $('#field_' + key + '_max').val();
                    }
                    if (max || min) {
                        formatFilter[key] = min + ',' + max;
                        op = $('#field_' + key + '_min').attr('data-searchop');
                    }
                    formatOp[key] = op;
                }
            });
            format['formatFilter'] = formatFilter
            format['formatOp'] = formatOp
            return format;
        },
        getIds: function (url, tableId) {
            url = url !== undefined ? Fun.url(url) : window.location.href;
            table = layui.table ||　layui.treeTable;
            var checkStatus = table.checkStatus(tableId), data = checkStatus.data;
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
        getOptions:function (tableId){
            return layui.table.getOptions(tableId);
        },
        getTableObj:function(tableId){
            options = Table.getOptions(tableId);
            if(options.tree){
                return layui.treeTable;
            }
            return layui.table;
        },
        events: {
            //链接
            href:function (othis,data,tableOptions){
                window.open(othis.data('url'),'_blank');
            }, iframe: function (othis,data,tableOptions){
                Fun.events.iframe(othis)
            }, open: function (othis,data,tableOptions) {
                Fun.events.open(othis)
            }, photos: function (othis,data,tableOptions) {
                Fun.events.photos(othis)
            }, refresh: function (othis,data,tableOptions) {
                var tableId = othis.data('tableid');
                if (tableId === undefined || tableId === '' || tableId == null) {
                    tableId = Table.init.tableId
                }
                Table.api.reload(tableId)
            },tabswitch: function (othis,data,tableOptions) {  //切换选项卡重载表格
                var field = othis.closest("[data-field]").data("field"), value = othis.data("value");
                $where = {};
                $where[field] = value;
                Table.api.reload(Table.init.tableId, $where);
                return false
            },export: function (othis,data,tableOptions) {
                var url = othis.data('url'),tableId = othis.data('tableid'),primaryKey = $('#'+tableId).data('primarykey');
                var dataField = $('#layui-search-field-' + tableId + ' .layui-form [name]').serializeArray();
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
                var status = Table.getTableObj(tableId).checkStatus(tableId);
                if(status.data.length>0){
                    var ids = [];
                    layui.each(status.data, function (i) {
                        ids[i] = status.data[i][primaryKey];
                    });
                    formatFilter[primaryKey] = ids
                    formatOp[primaryKey] = 'in';
                }
                if (url.indexOf('?') !== -1) {
                    where = "&filter=" + JSON.stringify(formatFilter) + '&op=' + JSON.stringify(formatOp)
                } else {
                    where = "?filter=" + JSON.stringify(formatFilter) + '&op=' + JSON.stringify(formatOp)
                }
                window.open(Fun.url(url + where), '_blank')
            },request: function (othis,data,tableOptions) {
                Fun.events.request(othis, null, Table)
            }, delete: function (othis,data,tableOptions) {
                var tableId = othis.data('tableid');
                url = othis.data('url');
                tableId = tableId || Table.init.tableId
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
                return false;
            }, dropdown: function (othis,data,tableOptions) {
                Fun.events.dropdown(othis,data,tableOptions)
            }, closeOpen: function (othis,data,tableOptions) {
                Fun.api.close();
            },laydate:function (othis,data,tableOptions) {
                // laydate
                layui.laydate.render({
                    elem: '.laydate',
                    done: function(value, date, endDate){
                        var data = Table.getRowData(this.elem, othis.data('tableid')); // 获取当前行数据(如 id 等字段，以作为数据修改的索引)
                        // 更新数据中对应的字段
                        var primaryKey = $('#'+tableId).data('primarykey');
                        var url = othis.data('url');field = othis.data('field');
                        Fun.ajax({url: url, data: {id: data[primaryKey], field: field, value: value,},}, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Table.api.reload(tableId)
                            })
                        }, function (res) {
                            Fun.toastr.error(res.msg)
                        })
                        return false;
                    }
                });
            }, colorpicker:function (othis,data,tableOptions) {
                layui.colorpicker.render({
                    elem: '.colorpicker',
                    predefine: true,
                    alpha: true,
                    done: function (value) {
                        var data = Table.getRowData(this.elem, othis.data('tableid')); // 获取当前行数据(如 id 等字段，以作为数据修改的索引)
                        // 更新数据中对应的字段
                        var primaryKey = $('#' + tableId).data('primarykey');
                        var url = othis.data('url');field = othis.data('field');
                        Fun.ajax({url: url, data: {id: data[primaryKey], field: field, value: value,},}, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Table.api.reload(tableId)
                            })
                        }, function (res) {
                            Fun.toastr.error(res.msg)
                        })
                        return false;
                    }
                });
            },common: function (othis,data,tableOptions) {
                Fun.api.callback(othis,data,tableOptions)
            },
        },
        api: {
            colorpicker:function (options) {
                othis = $('[lay-event="colorpicker"]');
                Table.events.colorpicker(othis);
            },
            //必须先加载否则上传无效
            import: function (options){
                othis = $('[lay-event="import"]');
                if(othis.length>0){
                    require(['upload'], function (Upload) {
                        Upload.api.uploads(othis,{},
                            function (res) {
                                if(res.code==0){
                                    Fun.toastr.error(res.msg);
                                    return false;
                                }
                                Fun.ajax({
                                    url: options.init.requests.import_url ||　options.init.requests.import,
                                    data: {file: res.url},
                                }, function (res) {
                                    if(res.code>0){
                                        Fun.toastr.success(res.msg);
                                    }else{
                                        Fun.toastr.error(res.msg);
                                    }
                                    Table.api.reload();

                                });
                            }
                        );
                    });
                }
            },
            toolDouble:function (options){
                //单元格工具事件 - 双击触发 注：v2.7.0 新增
                Table.getTableObj(options.id).on('toolDouble('+options.id+')', function(obj){
                    // 用法跟 tool 事件完全相同
                    // 这里写你的逻辑
                });
            },

            tool:function (options){
                //原来的点击事件失效改为此处
                Table.getTableObj(options.id).on('tool('+options.id+')', function (obj) {
                    var _that = $(this);
                    var attrEvent = obj.event || _that.attr('lay-event'); //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this,_that,obj,options);
                    } else {
                        Table.events.common(_that,obj,options);
                    }
                    return false;
                });
            },
            tableSearch: function (options) {
                layui.form.on('submit(' + options.id + '_filter)', function (data) {
                    var dataField = data.field;
                    var format = Table.getSearchField(dataField);
                    Table.api.reload(options.id, {
                        filter: JSON.stringify(format.formatFilter),
                        op: JSON.stringify(format.formatOp)
                    }, true, false);
                    return false;
                })
            },
            reload: function (tableId, $where, $deep, $parent,$page) {
                $deep = typeof $deep ==='undefined'?true:$deep;
                $parent = typeof $parent ==='undefined'?true:$parent;
                $page = typeof $page ==='undefined'?1:$parent;
                tableId = tableId ? tableId : Table.init.tableId;
                $where = $where || {};
                $map = {where: $where};
                if($page>=1){
                    $map.page = {
                        curr: $page //重新从第 1 页开始
                    }
                }
                table = layui.table || layui.treeTable;
                table.reloadData(tableId, $map, $deep);
                if ($parent && parent.layui.layer && parent.layui.layer.getFrameIndex(window.name)) {
                    parent.table.reloadData(tableId, {}, $deep);
                }
            },
            toolbar: function (options) {
                tableId = options.id || Table.init.tableId;
                Table.getTableObj(tableId).on('toolbar(' + options.layFilter + ')', function (obj) {
                    var othis = $(this);
                    switch (obj.event) {
                        case'TABLE_SEARCH':
                            var id = othis.parents('div[lay-id]').attr('lay-id');
                            var searchFieldsetId = 'layui-search-field-' + id;
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
                        case'href':
                            Table.events.href(othis);
                            break;
                        case'dropdown':
                            Table.events.dropdown(othis,'',options);
                            break;
                        default:
                            Table.events.common(othis,'',options);
                    }
                })
            },
            rowDouble: function (options) {
                var layFilter = options.layFilter, ops = options.init.requests.edit_url;
                Table.getTableObj(options.id).on('rowDouble(' + layFilter + ')', function (obj) {
                    url = typeof ops==="object"?ops.url:ops;
                    if (url && Fun.checkAuth(Fun.common.getNode(url), options.elem)) {
                        url = url.indexOf('?') !== -1 ? url + '&'+options.primaryKey+'=' + obj.data[options.primaryKey] : url + '?'+options.primaryKey+'=' + obj.data[options.primaryKey];
                        var opt = {};if(typeof ops==="object"){opt = ops;}
                        opt.url = url;opt.type = opt.hasOwnProperty("type") && opt.type==1?1:2;
                        Fun.api.open(opt);
                    }
                })
            },
            edit: function (options) {
                var url = options.init.requests.modify_url ? options.init.requests.modify_url : false;
                tableId = options.id || Table.init.tableId;
                if(!url || url=='undefined') return ;
                Table.getTableObj(tableId).on('edit(' + options.layFilter + ')', function (obj) {
                        var value = obj.value, data = obj.data, id = data[options.primaryKey], field = obj.field;
                        var _data = {id: id, field: field, value: value,};
                        Fun.ajax({url: url, prefix: true, data: _data,}, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Table.api.reload(tableId,{},true,true,0)
                            })
                        }, function (res) {
                            Fun.toastr.error(res.msg, function () {
                                Table.api.reload(tableId)
                            })
                        }, function () {
                            Table.api.reload(tableId)
                        })
                    })
            },
            sort: function (options) {
                tableId = options.id || Table.init.tableId;
                Table.getTableObj(tableId).on('sort(' + tableId + ')', function (obj) {
                    $where ={
                        field: obj.field
                        ,order: obj.type //排序方式
                    };
                    Table.api.reload(tableId,$where)
                })
            },
            switch: function (options) {
                layui.form.on('switch', function (obj) {
                    //获取当前table id;
                    url = $(this).attr('data-url') || options.init.requests.modify_url || false;
                    if(!url || url=='undefined') return ;
                    var filter = $(this).attr('lay-filter');
                    if(!filter) return ;
                    var checked = obj.elem.checked ? 1 : 0;
                    var data = {id: this.value, field: this.name, value: checked};
                    Fun.ajax({url: url, prefix: true, data: data,}, function (res) {
                        Fun.toastr.success(res.msg);
                        Table.api.reload(tableId,{},true,true,0)
                    }, function (res) {
                        obj.elem.checked = !checked;
                        layui.form.render();
                        Fun.toastr.error(res.msg)
                    }, function () {
                    })
                    return ;
                })
            },
            selects: function (options) {
                layui.form.on('select', function (obj) {
                    url = $(obj.elem).attr('data-url') || options.init.requests.modify_url || false;
                    if(!url || url=='undefined') return ;
                    tableId = options.id || Table.init.tableId;
                    filter = $(obj.elem).attr('lay-filter');
                    if(!filter) return ;
                    //兼容表单
                    if($(obj.othis).parents('form').length>0){return false;}
                    var id = $(obj.elem).attr('data-id');
                    name = $(obj.elem).attr('name');
                    var data = {id: id, field: name, value: obj.value};
                    Fun.ajax({url: url, prefix: true, data: data,}, function (res) {
                        Fun.toastr.success(res.msg)
                        Table.api.reload(tableId,{},true,true,0)
                    }, function (res) {
                        layui.form.render();
                        Fun.toastr.error(res.msg);
                    }, function () {
                    })
                    return ;
                })
            },
            bindEvent: function (tableId) {
                tableId = tableId || Table.init.tableId;
                $(document).on('focus','*[lay-event]',function(){
                    var _that = $(this),attrEvent = _that.attr('lay-event'); //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that)
                    } else {
                        Table.events.common(_that);
                    }
                    return false;
                })
                $(document).on('click','*[lay-event]',function(obj){

                    var _that = $(this),attrEvent = _that.attr('lay-event'); //获得 lay-event 对应的值（也可以是表头的 event 参数对应的值）
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that)
                    } else {
                        Table.events.common(_that);
                    }
                    return false;
                });
                //重置按钮，重新刷新表格
                $(document).on('click', 'button[type="reset"]', function () {
                    Table.api.reload($(this).data('tableid') || tableId, {}, false);
                });
                /**
                 * tips
                 */
                $(document).on('mouseenter', '*[data-tips]', function () {
                    var that = this;
                    if($(this).attr('data-tips')){layui.layer.tips(__($(this).attr('data-tips')),that,{tips: 1,time:1500,})}
                });
                $(document).on('blur', '#layui-input-search-'+tableId, function (event) {
                    var text = $(this).val();
                    var name = $(this).prop('name').split(',');
                    if (name.length === 1) {
                        var formatFilter = {}, formatOp = {};
                        formatFilter[name] = text;
                        formatOp[name] = $(this).data('searchop') || '%*%';
                        where = {}
                        if(text) {
                            where = {
                                filter: JSON.stringify(formatFilter),
                                op: JSON.stringify(formatOp)
                            }
                        }
                        Table.api.reload(tableId, where, true, false);
                        return false
                    } else {
                        Table.api.reload(tableId, {search: text, searchName: name}, true, true);
                        return false
                    }
                }).unbind('blur', '#layui-input-search-'+tableId, function (event) {
                    return false
                })
            },
        }
    };
    return Table;
})
