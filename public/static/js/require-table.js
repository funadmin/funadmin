define(["jquery",'timePicker'], function ($,timePicker) {
    var form = layui.form,
        table = layui.table,
        laydate = layui.laydate,
    element = layui.element;
    var Table = {
        init: {
            table_elem: 'list',
            tableId: 'list',
            requests:{
                export_url :'ajax/export'
            },
        },
        render: function (options) {
            options.elem = options.elem || '#' + Table.init.table_elem;
            options.init = options.init || Table.init;
            options.id = options.id || Table.init.tableId;
            options.layFilter = options.id;
            options.url = options.url || window.location.href;
            options.toolbar = options.toolbar || '#toolbar';
            options.page = Fun.parame(options.page, true);
            options.search = Fun.parame(options.search, true);
            options.limit = options.limit || 15;
            options.limits = options.limits || [10, 15, 20, 25, 50, 100];
            options.defaultToolbar = options.defaultToolbar || ['filter', 'exports', 'print', {
                title: __("Search"),
                layEvent: 'TABLE_SEARCH',
                icon: 'layui-icon-search',
                extend: 'data-tableid="' + options.id + '"'
            }];
            // 初始化表格lay-filter
            $(options.elem).attr('lay-filter', options.layFilter);
            // 初始化表格搜索
            options.toolbar = options.toolbar || ['refresh', 'add', 'delete'];

            if (options.search === true) {
                Table.renderSearch(options.cols, options.elem, options.id);
            }
            // 初始化表格左上方工具栏
            options.toolbar = Table.renderToolbar(options.toolbar, options.elem, options.id);
            var newTable = table.render(options);
            // 监听表格开关切换
            Table.api.switch(options.cols, options.init, options.id);
            // 监听表格搜索开关和toolbar按钮显示等
            Table.api.toolbar(options.layFilter, options.id);
            // 监听表格双击事件
            Table.api.rowDouble(options.layFilter, options.id);
            // 监听表格编辑
            Table.api.edit(options.init, options.layFilter, options.id);
            return newTable;
        },
        renderToolbar: function (d, elem, tableId) {
            d = d || [];
            var toolbarHtml = '';
            $.each(d, function (i, v) {
                if (v === 'refresh') {
                    url = Fun.replaceurl(Table.init.requests.export_url,d);

                    toolbarHtml += ' <a class="layui-btn layui-btn-sm layui-btn-normal" lay-event="refresh" data-tableid="' + tableId + '"><i class="layui-icon layui-icon-refresh"></i> </a>\n';
                } else if (v === 'export') {
                    url = Fun.replaceurl(Table.init.requests.export_url,d);
                    toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger" lay-event="export" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</a>\n';
                } else if (v === 'add') {
                    url = Fun.replaceurl(Table.init.requests.add_url,d);
                    if (Fun.checkAuth('add')) {
                        toolbarHtml += '<a class="layui-btn layui-btn-sm"   lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '" title="' + __('Add') + '"><i class="layui-icon layui-icon-add-circle-fine"></i>' + __('Add') + '</a>\n';
                    }
                } else if (v === 'delete') {
                    url = Fun.replaceurl(Table.init.requests.delete_url,d);
                    if (Fun.checkAuth('delete')) {
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-danger" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-delete"></i>' + __('Delete') + '</a>\n';
                    }
                }   else if (v === 'destory') {
                    url = Fun.replaceurl(Table.init.requests.destory_url,d);
                    if (Fun.checkAuth('destory')) {
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="delete" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-delete"></i>' + __('Destroy') + '</a>\n';
                    }
                } else if ( typeof v === 'string' && typeof eval('Table.init.requests.' + v) === 'string') {
                    if (Fun.checkAuth(v)) {
                        url = Fun.replaceurl(eval(('Table.init.requests.'+v+'_url')),d);
                        toolbarHtml += '<a class="layui-btn layui-btn-sm layui-btn-warm" lay-event="open" data-tableid="' + tableId + '"  data-url="' + url + '"><i class="layui-icon layui-icon-delete"></i>' + __(v) + '</a>\n';
                    }
                }else if (typeof v==='string' && typeof eval('Table.init.requests.' + v) === 'object') {
                    v = eval('Table.init.requests.' + v);
                    url = Fun.replaceurl(v.url,d);
                    if (Fun.checkAuth(v.url)) {
                        v.full = v.full || 0;
                        v.resize = v.resize || 0;
                        if (v.type) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-full="' + v.full + '" data-resize="'+v.resize+'" lay-event="'+v.type+'" data-tableid="' + tableId + '"   data-url="' + url + '" title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n';
                        } else {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-full="' + v.full + '" data-resize="'+v.resize+'" lay-event="request" data-tableid="' + tableId + '" data-url="' +
                                url + '" title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n';
                        }
                    }
                }else if (typeof v === 'object') {
                    if (Fun.checkAuth(v.url)) {
                        v.full = v.full || 0;
                        v.resize = v.resize || 0;
                        if (v.type) {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-full="' + v.full + '" data-resize="'+v.resize+'" lay-event="'+v.type+'" data-tableid="' + tableId + '"   data-url="' + v.url + '" title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n';
                        } else {
                            toolbarHtml += '<a class="layui-btn layui-btn-sm ' + v.class + '" data-full="' + v.full + '" data-resize="'+v.resize+'" lay-event="request" data-tableid="' + tableId + '" data-url="' +
                                v.url + '" title="' + v.title + '" ><i class="layui-icon ' + v.icon + '"></i>' + v.title + '</a>\n';
                        }
                    }
                }

            });
            return '<div>' + toolbarHtml + '</div>';
        },
        renderSearch: function (cols, elem, tableId) {
            cols = cols[0] || {};
            var newCols = [];
            var formHtml = '';
            $.each(cols, function (i, d) {
                d.field = d.field || false;
                d.fieldAlias = Fun.parame(d.fieldAlias, d.field);
                d.title = d.title || d.field || '';
                d.selectList = d.selectList || {};
                d.search = Fun.parame(d.search, true);
                d.searchTip = d.searchTip || __('Input') + d.title || '';
                d.searchValue = d.searchValue || '';
                d.searchOp = d.searchOp || '%*%';
                d.timeType = d.timeType || 'datetime';
                if (d.field !== false && d.search !== false) {
                    switch (d.search) {
                        case true:
                            formHtml += '\t <div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline ">\n' +
                                '<label class="layui-form-label layui-col-xs4">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline layui-col-xs8">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '" data-searchop="' + d.searchOp + '" value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case  'select':
                            d.searchOp = '=';
                            var selectHtml = '';
                            $.each(d.selectList, function (sI, sV) {
                                var selected = '';
                                if (sI === d.searchValue) {
                                    selected = 'selected=""';
                                }
                                selectHtml += '<option value="' + sI + '" ' + selected + '>' + sV + '</option>/n';
                            });
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label layui-col-xs4 ">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline layui-col-xs8">\n' +
                                '<select class="layui-select" id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '"  data-searchop="' + d.searchOp + '" >\n' +
                                '<option value="">-' + __("All") + ' -</option> \n' +
                                selectHtml +
                                '</select>\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case 'between':
                            d.searchOp = 'between';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline layui-between">\n' +
                                    '<label class="layui-form-label layui-col-xs4 ">' + d.title + '</label>\n' +
                                    '<div class="layui-input-inline layui-col-xs4">\n' +
                                    '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                    '</div>\n' +
                                    '<div class="layui-input-inline layui-col-xs4">\n' +
                                    '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                    '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case 'not between':
                            d.searchOp = 'not between';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline layui-between">\n' +
                                '<label class="layui-form-label layui-col-xs4">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline layui-col-xs4">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + eval(d.fieldAlias+'[]') + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '<div class="layui-input-inline layui-col-xs4">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + eval(d.fieldAlias+'[]') + '"  data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case 'range':
                            d.searchOp = 'range';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="timePicker" data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case 'time':
                            d.searchOp = 'time';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="time" data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                        case 'timerange':
                            d.searchOp = 'range';
                            formHtml += '\t<div class="layui-col-xs12 layui-col-sm6 layui-col-md4 layui-col-lg3">' +
                                '<div class="layui-form-item layui-inline">\n' +
                                '<label class="layui-form-label">' + d.title + '</label>\n' +
                                '<div class="layui-input-inline">\n' +
                                '<input id="filed_' + d.fieldAlias + '" name="' + d.fieldAlias + '" lay-filter="timerange" data-searchop="' + d.searchOp + '"  value="' + d.searchValue + '" placeholder="' + d.searchTip + '" class="layui-input">\n' +
                                '</div>\n' +
                                '</div>' +
                                '</div>';
                            break;
                    }
                    newCols.push(d);
                }
            });
            if (formHtml !== '') {
                $(elem).before('<fieldset id="searchFieldList_' + tableId + '" class="layui-elem-field table-search-fieldset layui-hide">\n' +
                    '<legend>' + __('Search') + '</legend>\n' +
                    '<form class="layui-form"><div class="layui-row">\n' +
                    formHtml +
                    '</div><div class="layui-form-item layui-inline" style="margin-left: 80px;">\n' +
                    '<button type="submit" class="layui-btn layui-btn-green" data-type="tableSearch" data-tableid="' + tableId + '" lay-submit="submit" lay-filter="' + tableId + '_filter">' + __('Search') + '</button>\n' +
                    '<button type="reset" class="layui-btn layui-btn-primary" data-type="tableReset"  data-tableid="' + tableId + '" lay-filter="' + tableId + '_filter">' + __('Reset') + '</button>\n' +
                    ' </div>' +
                    '</form>' +
                    '</fieldset>');

                // 初始化form表单
                Table.api.tableSearch(tableId);
                form.render();
                $.each(newCols, function (ncI, ncV) {
                    if (ncV.search === 'range') {
                        var timeList = document.querySelectorAll("*[lay-filter='timePicker']");
                        if (timeList.length > 0) {
                            $.each(timeList, function () {
                                var id = $(this).prop('id');
                                layui.timePicker.render({
                                    elem: '#' + id, //定义输入框input对象
                                    options:{      //可选参数timeStamp，format
                                        timeStamp:false,//true开启时间戳 开启后format就不需要配置，false关闭时间戳 //默认false
                                        format:'YYYY-MM-DD HH:ss:mm',//格式化时间具体可以参考moment.js官网 默认是YYYY-MM-DD HH:ss:mm
                                    },
                                });

                            })

                        }                    }
                    if (ncV.search === 'time') {
                        laydate.render({type: ncV.timeType, elem: '[name="' + ncV.field + '"]'});
                    }
                    if (ncV.search === 'timerange') {
                        laydate.render({range: true, type: ncV.timeType, elem: '[name="' + ncV.field + '"]'});
                    }
                });

            }
            // console.log(layui.moment.moment().format('YYYY-MM-DD HH:mm:ss'))
        },
        templet: {
            //时间
            time: function (d) {
                var ele = $(this)[0];
                var time = eval('d.' + ele.field);
                if (time) {
                    return layui.util.toDateString(time * 1000,'yyyy-MM-dd')
                } else {
                    return '';
                }
            },
            //图片
            image: function (d) {
                var ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 40;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                var src = d[ele.field] ? d[ele.field] : '/static/common/images/image.gif',
                    title = d[ele.title];
                return '<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + src + '" data-title="' + title + '"  lay-event="photos" alt="">';
            },
            //多个图片
            images: function (d) {
                var ele = $(this)[0];
                ele.imageWidth = ele.imageWidth || 40;
                ele.imageHeight = ele.imageHeight || 40;
                ele.title = ele.title || ele.field;
                var src = d[ele.field] ? d[ele.field] : '/static/common/images/image.gif',
                    title = d[ele.title];
                src = src.split(',');
                var html = [];
                $.each(src, function (i, v) {
                    v = v ? v : '/static/common/images/image.gif';
                    html.push('<img style="max-width: ' + ele.imageWidth + 'px; max-height: ' + ele.imageHeight + 'px;" src="' + v + '" data-title="' + title + '"  lay-event="photos" alt="">');
                });
                return html.join(' ');
            },
            content: function (d) {
                var ele = $(this)[0]; content = d[ele.field] ? d[ele.field] : '';
                return "<div style='white-space: nowrap; text-overflow:ellipsis; overflow: hidden; max-width:60px;'>" + content + "</div>";
            },
            //选择
            select: function (d) {
                var ele = $(this)[0];
                ele.selectList = ele.selectList || {};
                var value = d[ele.field];
                if (ele.selectList[value] === undefined || ele.selectList[value] === '' || ele.selectList[value] == null) {
                    return value;
                } else {
                    return ele.selectList[value];
                }
            },
            url: function (d) {
                var ele = $(this)[0];
                var src = d[ele.field];
                return '<a class="layui-table-url" href="' + src + '" target="_blank" class="label bg-green">' + src + '</a>';
            },
            icon: function (d) {
                var ele = $(this)[0];
                var icon = d[ele.field];
                return '<i class="' + icon + '"></i>';

            },
            //开关
            switch: function (d) {
                var ele = $(this)[0];
                ele.filter = ele.filter || ele.field || null;
                ele.checked = ele.checked || 1;
                ele.tips = ele.tips || __('open') + '|' + __('close');
                var checked = d[ele.field] === ele.checked ? 'checked' : '';
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
                var requests = Table.init.requests;
                $.each(ele.operat, function (k, v) {
                    //曾删改查
                    var vv;
                    if (v === 'edit' || v === 'delete' || v === 'add' || v === 'destroy' || (typeof v !=="object" && typeof eval('requests.' + v +'_url')==='string')) {
                        if (v === 'add') {
                            vv = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Add'),
                                title: '',
                                url: requests.add_url,
                                icon: 'layui-icon layui-icon-add-circle-fine',
                                extend: "",
                                width: '800',
                                height: '600',
                            };
                        } else if (v === 'edit') {
                            vv = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-xs',
                                text: __('Edit'),
                                title: '',
                                url: requests.edit_url,
                                icon: 'layui-icon layui-icon-edit',
                                extend: "",
                                width: '800',
                                height: '600',
                            };
                        } else if(v === 'delete')  {
                            vv = {
                                type: 'delete',
                                event: 'request',
                                class: 'layui-btn layui-btn-danger',
                                text: __('Delete'),
                                title: __('Are you sure to delete'),
                                url: requests.delete_url,
                                icon: 'layui-icon layui-icon-delete',
                                extend: "",
                                width: '800',
                                height: '600',
                            };
                        } else if(v==='destroy') {
                            vv = {
                                type: 'delete',
                                event: 'request',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Destroy'),
                                title: __('Are you sure to Destroy'),
                                url: requests.destroy_url,
                                icon: 'layui-icon layui-icon-fonts-clear',
                                extend: "",
                                width: '800',
                                height: '600',
                            };
                        }else{
                            vv = {
                                type: 'open',
                                event: 'open',
                                class: 'layui-btn layui-btn-warm',
                                text: __('Open'),
                                title: __('Open'),
                                url: eval('requests.'+v +'_url'),
                                icon: 'layui-icon layui-icon-rate',
                                extend: "",
                                width: '800',
                                height: '600',
                            };
                        }
                        // 初始化数据
                        vv.type = vv.type || '';
                        vv.class = vv.class || '';
                        vv.class = vv.class ?vv.class+' layui-btn-xs':vv.class;
                        vv.event = vv.event || vv.event || '';
                        vv.icon = vv.icon || '';
                        vv.url = vv.url || '';
                        vv.text = vv.text || '';
                        vv.title = vv.title || vv.text || '';
                        vv.extend = vv.extend || '';
                        // 组合数据
                        vv.node = vv.url;
                        vv.url = vv.url.indexOf("?") !== -1 ? vv.url + '&id=' + d.id : vv.url + '?id=' + d.id;
                        vv.url = Fun.replaceurl(vv.url,d);
                        vv.width = vv.width !== '' ? 'data-width="' + vv.width + '"' : '';
                        vv.height = vv.height !== '' ? 'data-height="' + vv.height + '"' : '';
                        vv.type = vv.type !== '' ? 'data-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon !== '' ? '<i class="' + vv.icon + '"></i>' : '';
                        vv.class = vv.class !== '' ? 'class="' + vv.class + '" ' : '';
                        vv.url = vv.url !== '' ? 'data-url="' + vv.url + '" data-title="' + vv.title + '"' : '';
                        vv.title= vv.title !== '' ? 'title="' + vv.title +'"':'';
                        vv.event = vv.event !== '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.tableid = 'data-tableid="' + Table.init.table_elem + '"';
                        if(!vv.icon){
                            vv.icon =  vv.icon + vv.text
                        }
                        if (Fun.checkAuth(vv.node)) {
                            html += '<button ' + vv.class + vv.tableid + vv.width + vv.height + vv.url + vv.event + vv.type + vv.extend + '>' + vv.icon + '</button>';
                        }
                    } else if (typeof v==='string' && typeof eval('requests.' + v) === "object") {
                        vv = {};
                        v =  eval('requests.' + v);
                        // // 初始化数据
                        vv.class = v.class || '';
                        vv.class = vv.class ?vv.class+' layui-btn-xs':vv.class;
                        vv.full = v.full || '';
                        vv.btn = v.btn || '';
                        vv.align = v.align || '';
                        vv.width = v.width || '';
                        vv.height = v.height || '';
                        vv.type = v.type || '';
                        vv.event = v.event || v.type || '';
                        vv.icon = v.icon || '';
                        vv.text = v.text || '';
                        vv.title = v.title || vv.text || '';
                        vv.extend = v.extend || '';
                        vv.node = v.url;
                        vv.url = v.url.indexOf("?") !== -1 ? v.url + '&id=' + d.id : v.url + '?id=' + d.id;
                        vv.url = Fun.replaceurl(vv.url,d);
                        vv.width = vv.width !== '' ? 'data-width="' + vv.width + '"' : '';
                        vv.height = vv.height !== '' ? 'data-height="' + vv.height + '"' : '';
                        vv.type = vv.type !== '' ? 'data-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon !== '' ? '<i class="layui-icon ' + vv.icon + '"></i>' : '';
                        vv.class = vv.class !== '' ? 'class="layui-btn ' + vv.class + '"' : '';
                        vv.url = vv.url !== '' ? 'data-url="' + vv.url + '" data-title="' + vv.title + '"' : '';
                        vv.title= vv.title !== '' ? 'title="' + vv.title +'"':'';
                        vv.event = vv.event !== '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.full = vv.full !== '' ? 'data-full="' + vv.full + '" ' : '';
                        vv.btn = vv.btn !== '' ? 'data-btn="' + vv.btn + '" ' : '';
                        vv.align = vv.align !== '' ? 'data-align="' + vv.align + '" ' : '';
                        vv.tableid = 'data-tableid="' + Table.init.table_elem + '"';
                        if(!vv.icon){
                            vv.icon =  vv.icon + vv.text
                        }
                        if (Fun.checkAuth(vv.node)) {
                            html += '<button ' + vv.tableid + vv.class + vv.width + vv.height + vv.title + vv.url + vv.event + vv.type + vv.extend + vv.full + vv.btn + vv.align+ '>' + vv.icon + '</button>';
                        }
                    }else if(typeof v==='object'){
                        var vv = {};
                        // // 初始化数据
                        vv.class = v.class || '';
                        vv.class = vv.class ?vv.class+' layui-btn-xs':vv.class;
                        vv.full = v.full || '';
                        vv.btn = v.btn || '';
                        vv.align = v.align || '';
                        vv.btn = v.btn || '';
                        vv.width = v.width || '';
                        vv.height = v.height || '';
                        vv.type = v.type || '';
                        vv.event = v.event || v.type || '';
                        vv.icon = v.icon || '';
                        vv.text = v.text || '';
                        vv.title = v.title || vv.text || '';
                        vv.extend = v.extend || '';
                        vv.node = v.url;
                        vv.url = v.url.indexOf("?") !== -1 ? v.url + '&id=' + d.id : v.url + '?id=' + d.id;
                        vv.url = Fun.replaceurl(vv.url,d);
                        vv.width = vv.width !== '' ? 'data-width="' + vv.width + '"' : '';
                        vv.height = vv.height !== '' ? 'data-height="' + vv.height + '"' : '';
                        vv.type = vv.type !== '' ? 'data-type="' + vv.type + '" ' : '';
                        vv.icon = vv.icon !== '' ? '<i class="layui-icon ' + vv.icon + '"></i>' : '';
                        vv.class = vv.class !== '' ? 'class="layui-btn ' + vv.class + '"' : '';
                        vv.url = vv.url !== '' ? 'data-url="' + vv.url + '" data-title="' + vv.title + '"' : '';
                        vv.title= vv.title !== '' ? 'title="' + vv.title +'"':'';
                        vv.event = vv.event !== '' ? 'lay-event="' + vv.event + '" ' : '';
                        vv.full = vv.full !== '' ? 'data-full="' + vv.full + '" ' : '';
                        vv.btn = vv.btn !== '' ? 'data-btn="' + vv.btn + '" ' : '';
                        vv.align = vv.align !== '' ? 'data-align="' + vv.align + '" ' : '';
                        vv.tableid = 'data-tableid="' + Table.init.table_elem + '"';
                        if(!vv.icon){
                            vv.icon =  vv.icon + vv.text
                        }
                        if (Fun.checkAuth(vv.node)) {
                            html += '<button ' + vv.tableid + vv.class + vv.width + vv.height + vv.title + vv.url + vv.event + vv.type + vv.extend + vv.full +vv.btn + vv.align+ '>' + vv.icon + '</button>';
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
                Fun.events.open(othis);
            },
            photos: function (othis) {
                Fun.events.photos(othis);
            },
            refresh: function (othis) {
                var tableId = othis.data().value.tableId;
                if (tableId === undefined || tableId === '' || tableId == null) {
                    tableId = Table.init.tableId;
                }
                table.reload(tableId);
            },
            //切换选项卡
            tabswitch:function(othis){
                var field = othis.closest("[lay-field]").data("field");
                var value = othis.data("value");
                Table.api.reload();
                return false;
            },
            request: function (othis) {
                var data = othis.data('value');
                var title = data.title,
                    url = data.url?data.url:data.href,
                tableId = data.tableId;
                title = title || __('Are you sure');
                tableId = tableId || Table.init.tableId;
                url = url !== undefined ? url : window.location.href;
                Fun.toastr.confirm(title, function () {
                    Fun.ajax({
                        url: url,
                    }, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            table.reload(tableId)
                        });

                    }, function (res) {
                        Fun.toastr.error(res.msg, function () {
                            table.reload(tableId);
                        });
                    })
                    Fun.toastr.close();

                }, function (res) {
                    if (res === undefined) {
                        Fun.toastr.close();
                        return false;
                    }
                    Fun.toastr.success(res.msg, function () {
                        table.reload(tableId);
                    });
                });

                return false;
            },
            // 数据表格多删除
            delete: function (othis) {
                var tableId = othis.data('tableid'),
                    url = othis.data('url');
                tableId = tableId || Table.init.tableId;
                url = url !== undefined ? Fun.url(url) : window.location.href;
                var checkStatus = table.checkStatus(tableId),
                    data = checkStatus.data;
                var ids = [];
                if (url.indexOf('?id=all') !== -1) {
                    ids = 'all';
                    length = __('All');
                } else {
                    if (data.length <= 0) {
                        Fun.toastr.error(__('Please check data'));
                        return false;
                    }
                    $.each(data, function (k, v) {
                        ids.push(v.id);
                    });
                    length = ids.length;
                }
                Fun.toastr.confirm(__('Are you sure you want to delete or destory the %s selected item?', length),
                    function () {
                    Fun.ajax({
                        url: url,
                        data: {
                            ids: ids
                        },
                    }, function (res) {
                        Fun.toastr.success(res.msg, function () {
                        table.reload(tableId);
                        });
                    }, function (res) {
                        Fun.toastr.error(res.msg);
                    });
                });
                return false;
            },

            //返回页面
            closeOpen: function (othis) {
                Fun.api.closeCurrentOpen();
            },
        },
        api: {
            reload: function (tableId,$where = {}) {
                tableId = tableId ? tableId : Table.init.tableId;
                $map = {where: $where}
                table.reload(tableId, {$map});
            },
            //表格收索
            tableSearch: function (tableId) {
                form.on('submit(' + tableId + '_filter)', function (data) {
                    var dataField = data.field;
                    var formatFilter = {},
                        formatOp = {};
                    $.each(dataField, function (key, val) {
                        if (val !== '') {
                            formatFilter[key] = val;
                            var op = $('#filed_' + key).data('searchop');
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
                        if (v.filter !== false && tableInit.requests.modify_url !== false) {
                            form.on('switch(' + v.filter + ')', function (obj) {
                                var checked = obj.elem.checked ? 1 : 0;
                                var data = {
                                    id: obj.value,
                                    field: v.field,
                                    value: checked,
                                };
                                Fun.ajax({
                                    url: url,
                                    prefix: true,
                                    data: data,
                                }, function (res) {

                                    Fun.toastr.success(res.msg, function () {
                                        table.reload(tableId);
                                    });
                                }, function (res) {
                                    Fun.toastr.error(res.msg, function () {
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
                        case 'destroy':
                            Table.events.destroy(othis);
                            break;
                        case 'open':
                            Table.events.open(othis);
                            break;
                        case 'request':
                            Table.events.request(othis);
                            break;

                        default:
                            return true;
                    }
                });
            },
            /*
            双击事件
             */
            rowDouble:function (layFilter, tableId) {
                table.on('rowDouble(' + layFilter + ')', function (obj) {
                    var url = Table.init.requests.edit_url
                    if(url && Fun.checkAuth(url)){
                        url = url.indexOf('?')!=-1 ?url+'&id='+ obj.data.id :url+'?id=' +obj.data.id
                        options = {
                            url:url,
                        }
                        Fun.api.open(options);

                    }
                    return false;

                });
            },
            edit: function (tableInit, layFilter, tableId) {
                tableInit.requests.modify_url = tableInit.requests.modify_url || false;
                tableId = tableId || Table.init.tableId;
                if (tableInit.requests.modify_url !== false) {
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
                        Fun.ajax({
                            url: tableInit.requests.modify_url,
                            prefix: true,
                            data: _data,
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                table.reload(tableId);
                            });
                        }, function (res) {
                            Fun.toastr.error(res.msg, function () {
                                table.reload(tableId);
                            });
                        }, function () {
                            table.reload(tableId);
                        });
                    });
                }
            },
            bindEvent: function () {
                // // 监听点击事件
                $('body').on('click', '[lay-event]', function () {
                    var _that = $(this), attrEvent = _that.attr('lay-event');
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that)
                    }
                });
            },
        },
    };

    return Table;

})