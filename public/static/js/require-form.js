    // +----------------------------------------------------------------------
    // | FunAdmin全栈开发框架 [基于thinkphp8+layui开发]
    // +----------------------------------------------------------------------
    // | Copyright (c) 2020-2030 http://www.funadmin.com
    // +----------------------------------------------------------------------
    // | git://github.com/funadmin/funadmin.git 994927909
    // +----------------------------------------------------------------------
    // | Author: yuege <994927909@qq.com> Apache 2.0 License Code
    
    define(['upload'], function (Upload) {
            var $ = layui.$;
            var Form = {
                init: {},
                //事件
                events: {
                    events: function () {
                        list = $("*[lay-event]");
                        if (list.length > 0) {
                            layui.each(list, function () {
                                $(this).click(function (e) {
                                    if ($(this).attr('lay-event') === 'open') {
                                        Fun.events.open($(this));
                                        return true;
                                    }
                                    if ($(this).attr('lay-event') === 'request') {
                                        Fun.events.request($(this));
                                        return true;
                                    }
                                    if ($(this).attr('lay-event') === 'iframe') {
                                        Fun.events.iframe($(this));
                                        return true;
                                    }
                                    if ($(this).attr('lay-event') === 'dropdown') {
                                        Fun.events.dropdown($(this));
                                        return true;
                                    }
                                })
                            })
                        }
                    },
                    autocomplete: function (formObj) {
                        Form.api.autocomplete(formObj)
                    },
                    select: function (formObj) {
                        Form.api.select(formObj)
                    },
                    selects: function (formObj) {
                        Form.api.selects(formObj);
                    },
                    selectcx: function (formObj) {
                        Form.api.selectcx(formObj)
                    },
                    selectpage: function (formObj) {
                        Form.api.selectpage(formObj)
                    },
                    xmselect: function (formObj) {
                        Form.api.xmselect(formObj)
                    },
                    editor: function (formObj) {
                        Form.api.editor(formObj)
                    },
                    tags: function (formObj) {
                        Form.api.tags(formObj)
                    },
                    icon: function (formObj) {
                        Form.api.icon(formObj)
                    },
                    color: function (formObj) {
                        Form.api.color(formObj)
                    },
                    city: function (formObj) {
                        Form.api.city(formObj)
                    },
                    timepicker: function (formObj) {
                        Form.api.timepicker(formObj)
                    },
                    datepicker: function (formObj) {
                        Form.api.datepicker(formObj)

                    },
                    date: function (formObj) {
                        Form.api.date(formObj)
                    },
                    rate: function (formObj) {
                        Form.api.rate(formObj)
                    },
                    slider: function (formObj) {
                        Form.api.slider(formObj)
                    },
                    formarray: function (formObj) {
                        Form.api.formarray(formObj)

                    },
                    uploads: function (formObj) {
                        Upload.api.uploads();
                    },
                    cropper: function (formObj) {
                        Upload.api.cropper();
                    },
                    //选择文件
                    choosefiles: function (formObj) {
                        Form.api.chooseFiles(formObj)
                    },
                    //选择文件
                    selectfiles: function (formObj) {
                        Form.api.selectFiles(formObj)
                    },
                    json:function (formObj){
                        Form.api.json(formObj)
                    },
                    transfer:function (formObj){
                        Form.api.transfer(formObj)
                    },
                    //验证
                    verifys: function (formObj) {
                        Form.api.verifys(formObj)
                    },
                    //必填项
                    required: function (formObj) {
                        Form.api.required(formObj)
                    },
                    submit: function (formObj, success, error, submit) {
                        var formList = $("[lay-submit]");
                        // 表单提交自动处理
                        if (formList.length > 0) {
                            layui.each(formList, function (i) {
                                var filter = $(this).attr('lay-filter');
                                var dataOptions = Fun.api.getElementData($(this));
                                var type = dataOptions.type,refresh = dataOptions.refresh,
                                url = dataOptions.request || dataOptions.url || $('form[lay-filter="' + filter + '"]').attr('action');
                                // 表格搜索不做自动提交
                                if (type === 'tableSearch') {
                                    return false;
                                }
                                // 判断是否需要刷新表格
                                if (refresh === undefined) refresh = true;
                                if (refresh === 'false') refresh = false;
                                if (refresh === '') refresh = false;
                                // 自动添加layui事件过滤器
                                if (filter === undefined || filter === '') {
                                    filter = 'form-' + (i + 1);
                                    $(this).attr('lay-filter', filter)
                                }
                                if (url === undefined || url === '' || url == null) {
                                    url = location.href;
                                }
                                layui.form.on('submit(' + filter + ')', function (data) {
                                    if ($('select[multiple]').length > 0) {
                                        var $select = $("select[multiple]");
                                        layui.each($select, function () {
                                            var field = $(this).attr('name');
                                            var vals = [];
                                            $(this).children('option:selected').each(function () {
                                                vals.push($(this).val());
                                            })
                                            data.field[field] = vals.join(',');
                                        })
                                    }
                                    var dataField = data.field;
                                    if (typeof formObj == 'function') {
                                        formObj(url, dataField);
                                    } else if (typeof submit == 'function') {
                                        submit(url, dataField);
                                    } else {
                                        Form.api.formSubmit(url, dataField, success, error, refresh);
                                    }
                                    return false;
                                });
                            })
                        }
                    },
                    //图片
                    photos: function (otihs) {
                        Fun.events.photos(otihs)
                    },
                    //删除
                    filedelete: function (othis) {
                        Form.api.filedelete(othis)
                    },
                    bindevent: function (formObj) {
                        formObj.on('click', '[lay-event]', function () {
                            var _t = $(this),
                                attrEvent = _t.attr('lay-event');
                            if (Form.events.hasOwnProperty(attrEvent)) {
                                Form.events[attrEvent] && Form.events[attrEvent].call(this, _t);
                            }
                        });
                        require(['table'], function (Table) {
                            $('body').on('click', '[lay-event]', function () {
                                if ($('table').length > 0) {
                                    var _t = $(this), attrEvent = _t.attr('lay-event');
                                    if (Table.events.hasOwnProperty(attrEvent)) {
                                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _t);
                                    }
                                }
                            });
                        })
                    },
                },
                api: {
                    /**
                     * 关闭窗口
                     * @param option
                     * @returns {boolean}
                     */
                    closeOpen: function (option) {
                        option = option || {};
                        option.refreshTable = option.refreshTable || false;
                        option.refreshFrame = option.refreshFrame || false;
    
                        var index = parent.layui.layer.getFrameIndex(window.name);
                        if (index) {
                            parent.layui.layer.close(index);
                        }
                        if (option.refreshTable === true) {
                            require(['table'], function (Table) {
                                option.refreshTable = option.tableid || Table.init.tableId;
                                if (self !== top && parent.$('#' + option.refreshTable).length > 0) {
                                    Table.api.reload(option.refreshTable)
                                } else {
                                    setTimeout(function () {
                                        location.reload();
                                    }, 2000)
                                    return false;
                                }
                            })
                        }
                        if (!option.refreshFrame) {
                            return false;
                        }
                        setTimeout(function () {
                            location.reload();
                        }, 2000)
                        return false;
                    },
                    /**
                     * 提交
                     * @param url
                     * @param data
                     * @param success
                     * @param error
                     * @param refresh
                     * @returns {boolean}
                     */
                    formSubmit: function (url, data, success, error, refresh) {
                        success = success ||
                            function (res) {
                                res.msg = res.msg || 'success';
                                Fun.toastr.success(res.msg);
                                Form.api.closeOpen({refreshTable: refresh, refreshFrame: refresh});
                                return false;
                            };
                        error = error ||
                            function (res) {
                                res.msg = res.msg || 'error';
                                Fun.toastr.error(res.msg, function () {
                                });
                                return false;
                            };
                        Fun.ajax({
                            url: url,
                            data: data,
                            // tips:__('loading'),
                            complete: function (xhr) {
                                var token = xhr.getResponseHeader('__token__');
                                if (token) {
                                    $("input[name='__token__']").val(token);
                                }
                            },
                        }, success, error);
                        return false;
                    },
                    //必填项
                    required: function (formObj) {
                        var vfList = $("[lay-verify]");
                        if (vfList.length > 0) {
                            layui.each(vfList, function () {
                                var verify = $(this).attr('lay-verify');
                                // todo 必填项处理
                                if (verify && verify.indexOf("required")>=0) {
                                    var label = $(this).parent().prev();
                                    if (label.is('label') && !label.hasClass('required')) {
                                        label.addClass('required');
                                    }
                                    if (typeof $(this).attr('lay-reqtext') === 'undefined' && typeof $(this).attr('placeholder') !== 'undefined') {
                                        $(this).attr('lay-reqtext', $(this).attr('placeholder'));
                                    }
                                    if (typeof $(this).attr('placeholder') === 'undefined' && typeof $(this).attr('lay-reqtext') !== 'undefined') {
                                        $(this).attr('placeholder', $(this).attr('layreqtext'));
                                    }
                                }
                            });
                            //div标签不需要lay-verify验证
                            $("div[lay-verify]").removeAttr('lay-verify');
                        }
                    },
                    verifys: function (formObj) {
                        layui.form.verify({
                            user: function (value) { //value：表单的值、item：表单的DOM对象
                                if (!new RegExp("^[a-zA-Z0-9_\u4e00-\u9fa5\\s·]+$").test(value)) {
                                    return '用户名不能有特殊字符';
                                }
                                if (/(^\_)|(\__)|(\_+$)/.test(value)) {
                                    return '用户名首尾不能出现下划线\'_\'';
                                }
                                if (/^\d+\d+\d$/.test(value)) {
                                    return '用户名不能全为数字';
                                }
                            },
                            pass: [/^[\S]{6,18}$/, '密码必须6到18位，且不能出现空格'],
                            zipcode: [/^\d{6}$/, "请检查邮政编码格式"],
                            chinese: [/^[\u0391-\uFFE5]+$/, "请填写中文字符"] //包含字母
                            ,
                            money: [/(^[1-9]([0-9]+)?(\.[0-9]{1,2})?$)|(^(0){1}$)|(^[0-9]\.[0-9]([0-9])?$)/, "请输入正确的金额,且最多两位小数!"],
                            letters: [/^[a-z]+$/i, "请填写字母"],
                            digits: [/^\d+$/, '请填入数字'],
                            qq: [/^[1-9]\d{4,}$/, "请填写有效的QQ号"],
                            callback: function(value, item){
                                item.attr('options').callback(value, item);
                            }
                        });
                    },
                    /**
                     * 初始化表格数据
                     */
                    initForm: function (data) {
                        if(data){
                            Fun.api.removeEmptyData(data);
                            layui.form.val("form", data);
                        }else if (window.FormArray) {
                            window.FormArray =     Fun.api.removeEmptyData(window.FormArray);
                            layui.form.val("form", window.FormArray);
                        }
                        layui.form.render();
                    },

                    filedelete:function (othis){
                        var fileurl = othis.data('fileurl'),
                            that;
                        var confirm = Fun.toastr.confirm(__('Are you sure？'), function () {
                            that = othis.parents('.layui-upload-list').parents('.layui-upload');
                            var input = that.find('input[type="text"]');
                            var inputVal = input.val();
                            var input_temp;
                            if (othis.parents('li').index() === 0) {
                                input_temp = inputVal.replace(fileurl, '');
                                input.val(input_temp.replace(/^,|,$/g, ''));
                            } else {
                                input_temp = inputVal.replace(',' + fileurl, '');
                                input.val(input_temp.replace(/^,|,$/g, ''));
                            }
                            othis.parents('li').remove();
                            Fun.toastr.close(confirm);
                        });
                        return false;
                    },
                    autocomplete: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='autoComplete']") : $("*[lay-filter='autoComplete']");
                        if (list.length > 0) {
                            require(['autoComplete'], function (autoComplete) {
                                layui.each(list, function (i, v) {
                                    var dataOptions = Fun.api.getElementData($(this));
                                    var _t = $(this), src = dataOptions.src || dataOptions.url,
                                        id = _t.attr('id') || _t.data('id'), keys = dataOptions.keys || null;
                                    if (keys) keys = [keys];
                                    if (dataOptions.length == 0) {
                                        data = Fun.api.getData(src, {});
                                    }
                                    window['autoComplete-' + id] = new autoComplete({
                                        selector: "#" + id,
                                        placeHolder: "Search...",
                                        data: {
                                            src: data,
                                            keys: keys,
                                            cache: true,
                                        },
                                        resultsList: {
                                            element: function (list, data) {
                                                if (!data.results.length) {
                                                    // Create "No Results" message element
                                                    var message = document.createElement("div");
                                                    // Add class to the created element
                                                    message.setAttribute("class", "autocomplete_no_result");
                                                    // Add message text content
                                                    message.innerHTML = '<span>Found No Results for "' + data.query + '"</span>';
                                                    // Append message element to the results list
                                                    list.prepend(message);
                                                }
                                            },
                                            noResults: true,
                                        },
                                        resultItem: {
                                            highlight: true
                                        },
                                        events: {
                                            input: {
                                                selection: function (event) {
                                                    const selection = event.detail.selection.value;
                                                    window['autoComplete-' + id].input.value = keys ? selection[keys] : selection;
                                                }
                                            }
                                        }
                                    });
                                })
                            })
                        }
                    },
                    selects: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='selects']") : $("*[lay-filter='selects']");
                        if (list.length > 0) {
                            require(['selects'], function (selects) {
                                selects = layui.selects || parent.layui.selects;
                                layui.each(list, function (i) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var id = _t.prop('id'), name = _t.attr('name') || 'id',
                                        url = dataOptions.url || dataOptions.request,
                                        selectList = dataOptions.selectList || [],
                                        values = dataOptions.value ? dataOptions.value : '',
                                        attr = dataOptions.attr,
                                        attr =  attr && typeof attr == 'string' ? attr.split(',') : (typeof attr =='object'?attr:['id','title']);
                                    attrs  = {}
                                    if(layui.isArray(attr)){
                                        attrs.id = attr.shift();
                                        attrs.title = attr.shift();
                                        attrs.selected = attr[2]?attr[2]:'selected';
                                    }
                                    var  opt = {
                                        elem: this,
                                        keywordPlaceholder: '请输入关键词',
                                        // 搜索时，没有匹配结果时显示的文字
                                        unfilteredText: '没有匹配的选项',
                                        // 值分隔符
                                        valueSeparator: ',',
                                        /* customName: {
                                             id: 'id',
                                             title: 'title',
                                             selected: 'selected',
                                         },*/
                                        options: [],
                                        // 搜索时，如果不能匹配时，是否允许新增
                                        allowCreate: true,
                                        // 是否折叠已选择的选项
                                        collapseSelected: false,
                                        // 远程获取options的url
                                        url: undefined,
                                        // 通过url获取options的解析函数
                                        parseOptions: undefined,
                                    };
                                    if(attrs){
                                        opt.customName =  attrs;
                                    }
                                    if(selectList){
                                        datas = [];
                                        for (k in selectList){
                                            if(!selectList[k].hasOwnProperty('id')){
                                                datas[i] = {id:k,title:selectList[k]};
                                            }
                                        }
                                        selectList = datas.length>0?datas:selectList;
                                        opt.options  = selectList;
                                    }
                                    if(url){
                                        opt.url = Fun.url(url);
                                        opt.parseOptions = function (res){
                                            return res.data;
                                        }
                                    }
                                    window['selects-' + id] = selects.render(opt);
                                    window['selects-' + id].val(String(values))
                                })
                            })
                        }
                    },
                    select: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='select']") : $("*[lay-filter='select']");
                        // 生成选项HTML的通用函数
                        function getOptions(dataList, fields, selectedValue) {
                            var html = '<option value=""></option>';
                            if (!dataList) {
                                return html;
                            }
                            
                            // 处理数组格式的数据
                            if (Array.isArray(dataList)) {  
                                dataList.forEach(function(item) {
                                    if (fields && fields.length >= 2) {
                                        // 对象格式：{id: 1, title: 'xxx'}
                                        var key = item[fields[0]];
                                        var title = item[fields[1]];
                                        var selected = (selectedValue !== undefined && key.toString() === selectedValue) ? ' selected=""' : '';
                                        html += '<option value="' + key + '"' + selected + '>' + title + '</option>';
                                    } else {
                                        // 简单数组格式：['value1', 'value2']
                                        var selected = (selectedValue !== undefined && item.toString() === selectedValue) ? ' selected=""' : '';
                                        html += '<option value="' + item + '"' + selected + '>' + item + '</option>';
                                    }
                                });
                            } 
                            // 处理对象格式的数据：{key1: 'value1', key2: 'value2'}
                            else if (typeof dataList === 'object') {
                                Object.keys(dataList).forEach(function(key) {
                                    var title = dataList[key];
                                    var selected = (selectedValue !== undefined && key.toString() === selectedValue) ? ' selected=""' : '';
                                    html += '<option value="' + key + '"' + selected + '>' + title + '</option>';
                                });
                            }
                            
                            return html;
                        }
                        $.each(list, function (i, v) {
                            var _t = $(this);
                            var dataOptions = Fun.api.getElementData(_t);
                            var value = _t.val();
                            var selectList = dataOptions.selectList || [];
                            var attr = dataOptions.attr || 'id,title';
                            var url = dataOptions.url || dataOptions.request ;
                            // 清理属性字符串并分割
                            var fields = attr.replace(/\s/g, "").split(',');
                            // 如果有本地数据，直接生成选项
                            if (selectList) {
                                var html = getOptions(selectList, fields, value);
                                _t.html(html);
                                layui.form.render('select');
                            } 
                            // 如果没有本地数据但有URL，通过AJAX获取数据
                            else if (url) {
                                Fun.api.ajax({
                                    method: 'get',
                                    url: url,
                                    data: {
                                        selectFields: attr
                                    },
                                }, function (res) {
                                    var html = getOptions(res.data, fields, value);
                                    _t.html(html);
                                    layui.form.render('select');
                                }, function (error) {
                                    console.error('获取选项数据失败:', error);
                                    Fun.toastr.error('获取选项数据失败');
                                });
                            }
                        });
                    },
                    selectcx: function (formObj) {
                        var selectcx = {},
                        list = formObj !== undefined ? formObj.find("*[lay-filter='cxselect']") : $("*[lay-filter='cxselect']");
                        if (list.length > 0) {
                            require(['cxSelect'],function(cxSelect){
                                layui.each(list, function (i, v) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var selects = dataOptions.selects || ['province_id', 'city_id', 'area_id'];
                                    var attr = dataOptions.attr || dataOptions.prop || ['id', 'name'];
                                    attr = layui.isArray(attr) ? attr : attr.split(',');
                                    $.cxSelect.defaults.jsonValue = attr[0] || 'id';
                                    $.cxSelect.defaults.jsonName = attr ? attr[1] : 'name';
                                    $.cxSelect.defaults.jsonSpace = dataOptions.url ? 'data' : "";
                                    if(dataOptions.selectList){
                                        $.cxSelect.defaults.data = dataOptions.selectList;
                                    }else{
                                        window['cxSelect-' + id] = $.cxSelect(_t, {
                                            selects: selects,  // 数组，请注意顺序
                                            emptyStyle: 'none'
                                        });

                                    }

                                    
                                })
                            })
                        }
                    },
                    selectpage: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='selectPage']") : $("*[lay-filter='selectPage']");
                        if (list.length > 0) {
                            require(["selectPage"], function (selectPage) {
                                selectPage = layui.selectPage || parent.layui.selectPage;
                                layui.each(list, function (i) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    value = _t.val() || _t.data("init");
                                    var id = _t.prop('id'), name = _t.attr('name') || 'id',
                                        verify = dataOptions.verify || _t.attr('verify'),
                                        url = dataOptions.url || dataOptions.request, isTree = dataOptions.istree,
                                        isHtml = dataOptions.ishtml,
                                        selectList = dataOptions.selectList, field = dataOptions.field || 'title',
                                        pageSize = dataOptions.pagesize || 12,
                                        primaryKey = dataOptions.primarykey || 'id',
                                        selectOnly = dataOptions.selectonly || false,
                                        pagination = !(_t.data('pagination') == 'false' || _t.data('pagination') == 0),
                                        listSize = dataOptions.listsize || '15',
                                        multiple = dataOptions.multiple || false, dropButton = dataOptions.dropbutton || true,
                                        maxSelectLimit = dataOptions.maxselectlimit || 0,
                                        searchField = dataOptions.searchfield || field,
                                        searchKey = dataOptions.searchkey || primaryKey,
                                        orderBy = dataOptions.orderby || false,
                                        method = dataOptions.method || 'GET', dbTable = dataOptions.dbtable,
                                        selectToCloseList = dataOptions.selecttocloselist || true,
                                        disabled = dataOptions.disabled || false,
                                        andOr = dataOptions.andor, formatItem = dataOptions.formatitem || false,
                                        verify = _t.attr('lay-verify') || '';
                                    orderBy = layui.type(orderBy) == 'string' ? [orderBy] : orderBy;
                                    isHtml != undefined ? isHtml : true;
                                    eSelect = dataOptions.eselect;
                                    if (!value && window.FormArray && window.FormArray[name]) {
                                        _t.val(window.FormArray[name]);
                                    }
                                    var options = {
                                        showField: field, keyField: primaryKey, pageSize: pageSize,
                                        selectFields: searchField, searchKey: searchKey, isTree: isTree, isHtml: isHtml,
                                        data: selectList || Fun.url(url), dbTable: dbTable, andOr: andOr, method: method,
                                        //仅选择模式，不允许输入查询关键字
                                        selectOnly: selectOnly, verify: verify, selectToCloseList: selectToCloseList,
                                        //关闭分页栏，数据将会一次性在列表中展示，上限200个项目
                                        pagination: pagination, maxSelectLimit: maxSelectLimit, orderBy: orderBy,
                                        //关闭分页的状态下，列表显示的项目个数，其它的项目以滚动条滚动方式展现（默认10个）
                                        listSize: listSize, multiple: multiple, dropButton: dropButton,
                                        formatItem: function (res) {
                                            if (formatItem) return eval(formatItem);
                                            return res[this.showField];
                                        },
                                        eSelect: eSelect ? eval(eSelect)(res) : function (res) {
                                        },
                                        eAjaxSuccess: function (res) {
                                            row = res.data;
                                            data = {};
                                            data.list = typeof row.data !== 'undefined' ? row.data : [];
                                            data.totalRow = typeof row.count !== 'undefined' ? row.count : row.data.length;
                                            return data;
                                        }
                                    };
                                    window['selectpage-' + id] = _t.selectPage(options);
                                    if (disabled) {
                                        _t.selectPageDisabled(true);
                                    }
                                })
                            })
                        }
                    },
                    xmselect: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='xmSelect']") : ($("*[lay-filter='xmSelect']") || $("*[lay-filter='xmselect']"));
                        if (list.length > 0) {
                            require(["xmSelect"], function (xmSelect) {
                                layui.each(list, function (i) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var id = _t.prop('id'),
                                        url = dataOptions.url || dataOptions.request, lang = dataOptions.lang,
                                        value = dataOptions.value || dataOptions.attr,
                                        selectList = dataOptions.selectList || [], parentField = dataOptions.parentField || dataOptions.parentfield || 'pid',
                                        tips = dataOptions.tips || '请选择', searchTips = dataOptions.searchTips|| dataOptions.searchtips || '请选择',
                                        empty = dataOptions.empty || '呀,没有数据', height = dataOptions.height || 'auto',
                                        paging = dataOptions.paging, pageSize = dataOptions.pageSize ||dataOptions.pagesize,
                                        remoteMethod =dataOptions.remoteMethod || dataOptions.remotemethod, content = dataOptions.content || '',
                                        radio = dataOptions.radio, disabled = dataOptions.disabled,
                                        autoRow = dataOptions.autoRow || dataOptions.autorow,
                                        clickClose = dataOptions.clickClose || dataOptions.clickclose, prop = dataOptions.prop || dataOptions.attr,
                                        max = dataOptions.max, create = dataOptions.create, on = dataOptions.on,
                                        repeat = !!dataOptions.repeat,
                                        theme = dataOptions.theme || '#4d70ff',
                                        name = dataOptions.name || _t.data('name') || 'pid',
                                        style = dataOptions.style || {},
                                        cascader = dataOptions.cascader ? {show: true, indent: 200, strict: false} : false,
                                        layVerify = _t.attr('lay-verify') || dataOptions.layverify || '',
                                        layReqText = _t.attr('lay-reqtext') ||  dataOptions.layreqtext || '';
                                    layVerType = _t.attr('lay-vertype') ||  dataOptions.layvertype  || 'tips'
                                    var size = _t.data('size') || 'medium';
                                    toolbar = _t.data('toolbar') == false ? {show: false} : {
                                        show: true,
                                        list: ['ALL', 'CLEAR', 'REVERSE']
                                    }
                                    var filterable = !!(dataOptions.filterable === undefined);
                                    var remoteSearch = !!((dataOptions.remoteSearch || dataOptions.remotesearch) !== undefined);
                                    var pageRemote = !(dataOptions.pageRemote || dataOptions.pageremote) ? false : true, props, propArr, options;
                                    var tree = dataOptions.tree;
                                    if (remoteSearch) toolbar.show = true;
                                    filterable = true;
                                    if (typeof tree === 'object') {
                                        tree = tree;
                                    } else {
                                        tree = tree ? {
                                            show: true,
                                            showFolderIcon: true,
                                            showLine: true,
                                            indent: 20,
                                            expandedKeys: [],
                                            strict: false,
                                            simple: false,
                                            clickExpand: true,
                                            clickCheck: true,
                                        } : false;
                                    }
                                    if (typeof value != 'object' && value) {
                                        value = typeof value === "number" ? [value] : value.split(',')
                                    }
                                    ;props = {
                                        name: 'title',
                                        value: "id"
                                    };
                                    selelectFields = {
                                        name: 'title',
                                        value: "id"
                                    };
                                    if (prop) {
                                        propArr = prop.split(',');
                                        props.name = propArr[0];
                                        props.value = propArr[1];
                                        selelectFields = {name: props.name}
                                        selelectFields.value = propArr[1] == props.name ? 'id' : propArr[1];
                                    }
                                    ;lang = lang ? lang : 'zh';
                                    paging = paging === undefined || paging !== 'false';
                                    pageSize = pageSize ? pageSize : 10;
                                    radio = !!radio;
                                    disabled = !!disabled;
                                    max = max ? max : 0;
                                    clickClose = clickClose ? clickClose : false;
                                    xmSelect = window.xmSelect ? window.xmSelect : parent.window.xmSelect;
                                    options = {
                                        el: this, language: lang, data: selectList, initValue: value, name: name, prop: props,
                                        tips: tips, empty: empty, searchTips: searchTips, disabled: disabled,
                                        filterable: filterable, remoteSearch: remoteSearch,
                                        remoteMethod: function (val, cb, show) {
                                            if (remoteMethod && remoteMethod !== undefined) {
                                                eval(remoteMethod)(val, cb, show)
                                            } else {
                                                var formatFilter = {},
                                                    formatOp = {};
                                                formatFilter[props.name] = val;
                                                formatOp[props.name] = '%*%';
                                                Fun.ajax({
                                                    method: 'get',
                                                    url: Fun.url(url ? url : window.location.href),
                                                    data: {
                                                        filter: JSON.stringify(formatFilter),
                                                        op: JSON.stringify(formatOp),
                                                        selectFields: selelectFields
                                                    }
                                                }, function (res) {
                                                    cb(res.data)
                                                }, function (res) {
                                                    cb([])
                                                })
                                            }
                                        },
                                        paging: paging, pageSize: pageSize, autoRow: autoRow, size: size,
                                        repeat: repeat, height: height, max: max,
                                        pageRemote: pageRemote,
                                        toolbar: toolbar, theme: {
                                            color: theme,
                                        }, radio: radio, layVerify: layVerify, clickClose: clickClose,
                                        maxMethod: function (val) {
                                        }, on: function (data) {
                                            return eval(on) ? eval(on)(data) : false;
                                        }, create: function (val, arr) {
                                            return eval(create) ? eval(create)(val, arr) : {name: val, value: val}
                                        },
                                    }
                                    if (tree) options.tree = tree;
                                    if (cascader) options.cascader = cascader;
                                    if (style) options.style = style;
                                    if (layReqText) options.layReqText = layReqText;
                                    if (layVerType) options.layVerType = layVerType;
                                    if (content) options.content = content;
                                    window['xmselect-' + id] = xmSelect.render(options);
                                    if (selectList.toString() === '' && url) {
                                        searchData = {
                                            selectFields: selelectFields,
                                            tree: tree.show,
                                            parentField: parentField
                                        }
                                        Fun.ajax({
                                            method: 'GET',
                                            url: Fun.url(url ? url : window.location.href),
                                            data: searchData
                                        }, function (res) {
                                            window['xmselect-' + id].update({
                                                data: res.data,
                                                autoRow: autoRow,
                                            })
                                        }, function (res) {
                                            console.log(res);
                                        })
                                    }
                                })
                            })
                        }
                    },
                    editor: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='editor']") : $("*[lay-filter='editor']");
                        if (list.length > 0) {
                            const useDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
                            const isSmallScreen = window.matchMedia('(max-width: 1023.5px)').matches;
                            layui.each(list, function () {
                                var _t = $(this);
                                var dataOptions = Fun.api.getElementData(_t);
                                var id = _t.prop('id');
                                var name = _t.prop('name');
                                var path = dataOptions.path;
                                _t.html(window.FormArray[name]);
                                var upload_url = (dataOptions.url ? dataOptions.url : Fun.url(Upload.init.requests.upload_url)) + '?editor=tinymce&path=' + path;
                                if (dataOptions.editor == 'tinymce') {
                                    if ($("body").find('script[src="/static/plugins/tinymce/tinymce.min.js"]').length == 0) {
                                        $('body').append($("<script defer referrerpolicy='origin' src='/static/plugins/tinymce/tinymce.min.js'></script>"));
                                    }
                                    window['editor-' + id] = tinymce.init({
                                        selector: '#' + id + '[lay-editor]',
                                        license_key:'gpl',
                                        language: dataOptions.language ||  'zh_CN',
                                        plugins: dataOptions.plugins ? dataOptions.plugins : 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media  codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
                                        editimage_cors_hosts: ['picsum.photos'],
                                        menubar: 'file edit view insert format tools table help',
                                        toolbar: dataOptions.toolbar ? dataOptions.toolbar : 'undo redo  bold italic underline strikethrough  fontfamily fontsize | blocks  alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  insertfile image media preview  template link  | print  anchor codesample save  ltr rtl ',
                                        toolbar_sticky: false,
                                        toolbar_sticky_offset: isSmallScreen ? 102 : 108,
                                        autosave_ask_before_unload: true,
                                        autosave_interval: '3s',
                                        autosave_prefix: '{path}{query}-{id}-',
                                        autosave_restore_when_empty: false,
                                        autosave_retention: '20m',
                                        image_advtab: true,
                                        height: (dataOptions.height ? dataOptions.height : 650), //编辑器高度
                                        min_height: (dataOptions.maxheight ? dataOptions.maxheight : 400),
                                        content_style: "body { font-family:Helvetica,Arial,sans-serif; font-size:16px } img {max-width:100%;}",
                                        font_size_formats:'10px 11px 12px 13px 14px 15px 16px 18px 20px 22px 24px 26px 28px 30px 32px 34px 36px',
                                        font_size_input_default_unit: "px",
                                        font_formats: '微软雅黑=Microsoft YaHei,Helvetica Neue,PingFang SC,sans-serif;苹果苹方=PingFang SC,Microsoft YaHei,sans-serif;宋体=simsun,serif;仿宋体=FangSong,serif;黑体=SimHei,sans-serif;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;',
                                        link_list: [
                                            {title: 'funadmin', value: 'https://www.tiny.cloud'},
                                            {title: 'my funadmin', value: 'http://www.funadmin.com'}],
                                        image_list: [
                                            {title: 'My page 1', value: 'https://www.tiny.cloud'},
                                            {title: 'my funadmin', value: 'http://www.funadmin.com'}],
                                        image_class_list: [
                                            {title: 'None', value: ''},
                                            {title: 'Some class', value: 'class-name'}],
                                        //动态改变值
                                        init_instance_callback: function (editor) {
                                            editor.on('change', function (e) {
                                                var val = editor.contentDocument.body.innerHTML;
                                                _t.parent('div').find('textarea[name="' + name + '"]').val(val);
                                            });
                                        },
                                        importcss_append: true,
                                        images_upload_url: upload_url,
                                        images_upload_base_path: '',
                                        image_caption: true,
                                        quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
                                        noneditable_class: 'mceNonEditable',
                                        toolbar_mode: 'sliding',
                                        contextmenu: 'link image table',
                                        skin: useDarkMode ? 'oxide-dark' : 'oxide',
                                        content_css: useDarkMode ? 'dark' : 'default',
                                        convert_urls:false,
                                        //自定义文件选择器的回调内容
                                        file_picker_callback: function (callback, value, meta) {
                                            //文件分类
                                            var filetype = "*";
                                            //模拟出一个input用于添加本地文件
                                            var input = document.createElement('input');
                                            input.setAttribute('type', 'file');
                                            input.setAttribute('accept', filetype);
                                            input.click();
                                            input.onchange = function () {
                                                var file = this.files[0];
                                                var xhr, formData;
                                                xhr = new XMLHttpRequest();
                                                xhr.withCredentials = false;
                                                xhr.open('POST', upload_url);
                                                xhr.onload = function () {
                                                    var json;
                                                    if (xhr.status != 200) {
                                                        Fun.toastr.error('HTTP Error: ' + xhr.status);
                                                        return;
                                                    }
                                                    json = JSON.parse(xhr.responseText);
                                                    if (!json || typeof json.location != 'string') {
                                                        Fun.toastr.error('Invalid JSON: ' + xhr.responseText);
                                                        return;
                                                    }
                                                    callback(json.location);
                                                };
                                                formData = new FormData();
                                                formData.append('file', file, file.name);
                                                xhr.send(formData);

                                            };
                                        },
                                    });

                                }
                            })
                        }
                    },
                    tags: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='inputtags']") : $("*[lay-filter='inputtags']");
                        if (list.length > 0) {
                            require(['inputTags'], function (inputTags) {
                                layui.each(list, function () {
                                    var _t = $(this),
                                    valueData = [],value = _t.next('input').val();
                                    if (value) valueData = value.substring(0, value.length - 1).split(',');
                                    var id = _t.prop('id');
                                    window['tags-' + id] = inputTags.render({
                                        elem: this,
                                        data: valueData,//初始值
                                        permanentData: [],//不允许删除的值
                                        removeKeyNum: 8,//删除按键编号 默认，BackSpace 键
                                        createKeyNum: 32,//创建按键编号 默认，space 键
                                        beforeCreate: function (data, value) {//添加前操作，必须返回字符串才有效
                                            return value;
                                        },
                                        onChange: function (data, value, type) {
                                            _t.next('input').val(data.join(','));
                                        }
                                    })
                                })
                            })
                        }
                    },
                    icon: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='iconPicker']") : $("*[lay-filter='iconPicker']");
                        if (list.length > 0) {
                            require(['iconPicker'], function (iconPicker) {
                                layui.each(list, function () {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var id = _t.prop('id');
                                    window['icon-' + id] = layui.iconPicker.render({
                                        elem: this,
                                        type: 'fontClass',
                                        value:_t.prop('value') || dataOptions.value,
                                        search: true,
                                        page: true,
                                        limit: 12,
                                        click: function (data) {
                                            _t.val(data.icon)
                                        },
                                        success: dataOptions.done ? eval(dataOptions.done(data)) : function (d) {
                                        }
                                    })
                                })
                            })
                        }
                    },
                    color: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='colorPicker']") : $("*[lay-filter='colorPicker']");
                        if (list.length > 0) {
                            layui.each(list, function () {
                                var _t = $(this);
                                var dataOptions = Fun.api.getElementData(_t);
                                var name = dataOptions.name, format = dataOptions.format || 'hex';
                                var id = _t.prop('id') || _t.data('id');
                                var color = _t.prev('input').val();
                                window['color-' + id] = layui.colorpicker.render({
                                    elem: this,
                                    color: color,
                                    predefine: true,
                                    alpha: true,
                                    format: format,
                                    change: function (color) {
                                    },
                                    done: dataOptions.done ? eval(dataOptions.done(color)) : function (color) {
                                        _t.prev('input[name="' + name + '"]').val(color)
                                    }
                                })
                            })
                        }
                    },
                    city: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='cityPicker']") : $("*[lay-filter='cityPicker']");
                        if (list.length > 0) {
                            require(['cityPicker'], function (cityPicker) {
                                cityPicker = layui.cityPicker;
                                layui.each(list, function () {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var id = _t.prop('id'),
                                        name = _t.prop('name');
                                    var provinceId = dataOptions.provinceid,
                                        cityId = dataOptions.cityid;
                                    var province, city, district;
                                    if (window.FormArray[name]) {
                                        var cityValue = window.FormArray[name];
                                        province = cityValue.split('/')[0];
                                        city = cityValue.split('/')[1];
                                        district = cityValue.split('/')[2];
                                    }
                                    var districtId = dataOptions.districtid;
                                    window['citypicker-' + id] = new cityPicker(this, {
                                        provincename: provinceId,
                                        cityname: cityId,
                                        districtname: districtId,
                                        level: 'districtId',
                                        province: province,
                                        city: city,
                                        district: district
                                    });
                                    var str = '';
                                    if (window.FormArray.hasOwnProperty(provinceId)) {
                                        str += ChineseDistricts[886][window.FormArray[provinceId]]
                                    }
                                    if (window.FormArray.hasOwnProperty(cityId) && window.FormArray[[cityId]] && window.FormArray.hasOwnProperty(provinceId)) {
                                        str += '/' + ChineseDistricts[window.FormArray[provinceId]][window.FormArray[cityId]]
                                    }
                                    if (window.FormArray.hasOwnProperty(cityId) && window.FormArray[districtId] && window.FormArray.hasOwnProperty(districtId)) {
                                        str += '/' + ChineseDistricts[window.FormArray[cityId]][window.FormArray[districtId]]
                                    }
                                    if (!str) {
                                        str = window.FormArray.hasOwnProperty(name) ? window.FormArray['name'] : ''
                                    }
                                    window['citypicker-' + id].setValue(window.FormArray[name] ? window.FormArray[name] : str)
                                })
                            })
                        }
                    },
                    timepicker: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='timePicker']") : $("*[lay-filter='timePicker']");
                        if (list.length > 0) {
                            require(['timePicker'], function (timePicker) {
                                layui.each(list, function () {
                                    var id = $(this).prop('id');
                                    window['timepicker-' + id] = layui.timePicker.render({
                                        elem: this,
                                        trigger: 'click',
                                        options: {
                                            timeStamp: false,
                                            format: 'YYYY-MM-DD HH:ss:mm',
                                        },
                                    })
                                })
                            })
                        }
                    },
                    datepicker: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='datePicker']") : $("*[lay-filter='datePicker']");
                        if (list.length > 0) {
                            layui.each(list, function () {
                                var _t = $(this);
                                var id = _t.prop('id') || _t.attr('id');    
                                window['datepicker-' + id] = layui.laydate.render({
                                    elem: this,
                                    type: "datetime",
                                    range: true,
                                    shortcuts: [
                                        {
                                            text: "今天", value: function () {
                                                sTime = Dayjs().startOf("d");
                                                eTime = Dayjs().endOf("d");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "昨天",
                                            value: function () {
                                                sTime = Dayjs().subtract(1, "d").startOf("d");
                                                eTime = Dayjs().subtract(1, "d").endOf("d");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "明天",
                                            value: function () {
                                                sTime = Dayjs().subtract(-1, "d").startOf("d");
                                                eTime = Dayjs().subtract(-1, "d").endOf("d");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "本周",
                                            value: function () {
                                                sTime = Dayjs().startOf("w").add(1,'day');
                                                eTime = Dayjs().endOf("w").add(1,'day');
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "上周",
                                            value: function () {
                                                sTime = Dayjs().subtract(1, "w").startOf("w").add(1,'day');
                                                eTime = Dayjs().subtract(1, "w").endOf("w").add(1,'day');
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "下周",
                                            value: function () {
                                                sTime = Dayjs().subtract(-1, "w").startOf("w").add(1,'day');
                                                eTime = Dayjs().subtract(-1, "w").endOf("w").add(1,'day');
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "近一周",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 7);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "本月",
                                            value: function () {
                                                sTime = Dayjs().startOf("M");
                                                eTime = Dayjs().endOf("M");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "上月",
                                            value: function () {
                                                sTime = Dayjs().subtract(1, "M").startOf("M");
                                                eTime = Dayjs().subtract(1, "M").endOf("1 M");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "下月",
                                            value: function () {
                                                sTime = Dayjs().subtract(-1, "M").startOf("M");
                                                eTime = Dayjs().subtract(-1, "M").endOf("1 M");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "近三月",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 90);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        }, {
                                            text: "近半年",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 180);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "本季度",
                                            value: function () {
                                                sTime = Dayjs().startOf("quarter");
                                                eTime = Dayjs().endOf("quarter");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "上季度",
                                            value: function () {
                                                sTime = Dayjs().subtract(1, "Q").startOf("Q");
                                                eTime = Dayjs().subtract(1, "Q").endOf("Q");
                                                return [sTime, eTime];
                                            }()
                                        }, {
                                            text: "下季度",
                                            value: function () {
                                                sTime = Dayjs().subtract(-1, "Q").startOf("Q");
                                                eTime = Dayjs().subtract(-1, "Q").endOf("Q");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "近三季度",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 180);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "本年度",
                                            value: function () {
                                                sTime = Dayjs().startOf("y");
                                                eTime = Dayjs().endOf("y");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "上年度",
                                            value: function () {
                                                sTime = Dayjs().subtract(1, "y").startOf("y");
                                                eTime = Dayjs().subtract(1, "y").endOf("y");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "下年度",
                                            value: function () {
                                                sTime = Dayjs().subtract(-1, "y").startOf("y");
                                                eTime = Dayjs().subtract(-1, "y").endOf("y");
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "近一年",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 365 * 1);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        },
                                        {
                                            text: "近三年",
                                            value: function () {
                                                sTime = new Date();
                                                sTime.setTime(new Date().getTime() - 3600 * 1000 * 24 * 365 * 3);
                                                eTime = Dayjs();
                                                return [sTime, eTime];
                                            }()
                                        }
                                    ],
                                })
                            })
                        }
                    },
                    date: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='date']") : $("*[lay-filter='date']");
                        if (list.length > 0) {
                            layui.each(list, function () {
                                var _t = $(this);
                                var dataOptions = Fun.api.getElementData(_t);
                                var format = dataOptions.format, type = dataOptions.type,
                                    id = _t.prop('id') || _t.data('id'),
                                    value = dataOptions.value || _t.val(), range = dataOptions.range;
                                    type = type || 'datetime';
                                    theme = dataOptions.theme || '#4d70ff';
                                var options = {
                                    elem: this,
                                    type: type,
                                    trigger: dataOptions.trigger || 'click',
                                    calendar: true,
                                    theme: theme
                                };
                                if (format !== undefined && format !== '' && format != null) {
                                    options['format'] = format.replaceAll('Y','y');
                                }
                                if (range !== undefined) {
                                    if (range != null || range === '') {
                                        range = '-'
                                    }
                                    options['range'] = range
                                }
                                if (value) {
                                    options['value'] = value;
                                }
                                if (dataOptions.fullPanel != false) {
                                    options['fullPanel'] = true;// 2.8+
                                }
                                if (dataOptions.mark) {
                                    options['mark'] = mark;
                                }
                                if (dataOptions.holidays) {
                                    options['holidays'] = holidays;
                                }
                                if (dataOptions.min) {
                                    options['min'] = min;
                                }
                                if (dataOptions.max) {
                                    options['max'] = max;
                                }
                                laydate = layui.laydate ? layui.laydate : parent.layui.laydate;
                                window['date-' + id] = laydate.render(options)
                            })
                        }
                    },
                    rate: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='rate']") : $("*[lay-filter='rate']");
                        if (list.length > 0) {
                            layui.each(list, function (i) {
                                var _t = $(this), id = _t.prop('id');
                                var dataOptions = Fun.api.getElementData(_t);
                                var name = dataOptions.name;
                                var value = dataOptions.value;
                                var length = dataOptions.length || 5;
                                var theme = dataOptions.theme || '#1E9FFF';
                                var readonly = dataOptions.readonly || dataOptions.disabled ? true : false;
                                var options = {
                                    elem: this,
                                    value: value,
                                    length: length,
                                    theme: theme,
                                    readonly: readonly
                                };
                                if (_t.parent('div').find('input[name="' + name + '"]').length == 0) {
                                    _t.before('input[name="' + name + '"]');
                                }
                                if (options.setText) {
                                    options.setText = function (value) { //自定义文本的回调
                                        var arrs = options.setText;
                                        this.span.text(arrs[value] || (value + __("Star")));
                                    }
                                }
                                options.choose = function (value) {
                                    _t.parent('div').find('input[name="' + name + '"]').val(value)
                                }
                                window['rate-' + id] = layui.rate.render(options);
                            })
                        }
                    },
                    slider: function (formObj) {
                        var list = formObj !== undefined ? formObj.find("*[lay-filter='slider']") : $("*[lay-filter='slider']");
                        if (list.length > 0) {
                            layui.each(list, function (i) {
                                var _t = $(this), id = _t.prop('id');
                                var dataOptions = Fun.api.getElementData(_t);
                                var name = dataOptions.name;
                                var value = dataOptions.value;
                                var type = dataOptions.type || 'default';
                                var step = dataOptions.step || 1;
                                var range = dataOptions.range || true;
                                var max = dataOptions.max || 100;
                                var min = dataOptions.min || 0;
                                var disabled = dataOptions.disabled || dataOptions.readonly ? true : false;
                                var input = dataOptions.input == undefined || dataOptions.input ? true : false;
                                var theme = dataOptions.theme || '#1E9FFF';
                                var options = {
                                    elem: this,
                                    value: value,
                                    type: type,
                                    step: step,
                                    range: range,
                                    max: max,
                                    min: min,
                                    disabled: disabled,
                                    input: input,
                                    theme: theme
                                };
                                options.max = options.max ? options.max : 100;
                                options.min = options.min ? options.min : 0;
                                options.disabled = options.readonly || options.disabled ? true : false;
                                options.input = options.input == undefined || options.input ? true : false;
                                options.theme = options.theme == undefined ? '#1E9FFF' : options.theme;
                                if (options.setTips) {
                                    options.setTips = function (value) { //自定义文本的回调
                                        return value + options.setTips;
                                    }
                                }
                                options.change = function (value) {
                                }
                                options.done = function (value) {
                                }
                                window['slider-' + id] = layui.slider.render(options);
                                _t.find('.layui-slider-input .layui-input').attr('name',_t.attr('name'))
                                _t.find('.layui-slider-input .layui-input').attr('lay-vertype',_t.attr('lay-vertype'))
                                _t.find('.layui-slider-input .layui-input').attr('lay-verify',_t.attr('lay-verify'))
                            })
                        }
                    },
                    formarray: function (formObj) {
                        formObj.on("click", ".form-array .del", function () {
                            var tr = $(this).parents('tr');
                            var lawtable = tr.parents('.layui-table');
                            rows = lawtable.find('.tr');
                            if (rows.length > 1) {
                                $(this).parents('tr').remove();
                            } else {
                                Fun.toastr.error('至少保留一条记录!');
                            }
                        });
                        require(['Sortable'], function (Sortable) {
                            //排序
                            layui.each($('.form-sortable'), function () {
                                new Sortable($(this)[0], {
                                    group: 'sortable',
                                    animation: 150
                                })
                            })
                        })

                        formObj.on("click", ".form-array .add", function () {
                            var tr = $(this).parents('tr');
                            var html = tr.html();
                            cls = tr.attr('class');
                            html = '<tr class="' + cls + '">' + html + '</tr>';
                            tr.after(html);
                            tr.next('tr').find('input').val('');
                            Upload.api.uploads();
                        });
                    },
                    /**
                     * 选择文件
                     */
                    chooseFiles: function (formObj) {
                        var fileChooseList = formObj !== undefined ? formObj.find("*[lay-filter='upload-choose']") : $("*[lay-filter='upload-choose']");
                        if (fileChooseList.length > 0) {
                            require(['tableSelect', 'table'], function (tableSelect, Table) {
                                layui.each(fileChooseList, function (i, v) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var uploadType = dataOptions.type, uploadNum = dataOptions.num, uploadMime = dataOptions.mime,
                                        url = dataOptions.tableurl, path = dataOptions.path;
                                    uploadMime = uploadMime || '*';
                                    uploadType = uploadType ? uploadType : 'radio';
                                    uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                                    var input = _t.parents('.layui-upload').find('input[type="text"]');
                                    var uploadList = _t.parents('.layui-upload').find('.layui-upload-list');
                                    var id = _t.attr('id');
                                    tableSelect = layui.tableSelect || parent.layui.tableSelect;
                                    url = url ? url : Fun.url(Upload.init.requests.attach_url + '?' +
                                        '&elem_id=' + id + '&num=' + uploadNum + '&type=' + uploadType + '&mime=' + uploadMime + '&path=' + path + '&type=' + uploadType);
                                    tableSelect.render({
                                        elem: this,
                                        checkedKey: 'id',
                                        searchType: 2,
                                        searchList: [{
                                            searchKey: 'original_name',
                                            searchPlaceholder: __('FileName')
                                        },],
                                        table: {
                                            url: url,
                                            cols: [
                                                [{
                                                    type: uploadType
                                                }, {
                                                    field: 'id',
                                                    title: 'ID'
                                                }, {
                                                    field: 'url',
                                                    minWidth: 80,
                                                    search: false,
                                                    title: __('Path'),
                                                    imageHeight: 40,
                                                    align: "center",
                                                    templet: Table.templet.image,
                                                }, {
                                                    field: 'original_name',
                                                    width: 150,
                                                    title: __('OriginalName'),
                                                    align: "center"
                                                }, {
                                                    field: 'mime',
                                                    width: 120,
                                                    title: __('MimeType'),
                                                    align: "center"
                                                }, {
                                                    field: 'create_time',
                                                    width: 200,
                                                    title: __('CreateTime'),
                                                    align: "center",
                                                    search: 'range'
                                                },]
                                            ]
                                        },
                                        done: function (elem, data) {
                                            var fileArr = [];
                                            var html = '';
                                            layui.each(data.data, function (index, val) {
                                                if (uploadMime === 'images') {
                                                    html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + val.path + '" alt=""><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + val.path + '"></i></li>\n';
                                                } else if (uploadMime === 'video') {
                                                    html += '<li><video controls class="layui-upload-img fl" width="150" src="' + val.path + '"></video><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + val.path + '"></i></li>\n';
                                                } else if (uploadMime === 'audio') {
                                                    html += '<li><audio controls class="layui-upload-img fl"  src="' + val.path + '"></audio><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + val.path + '"></i></li>\n';
                                                } else {
                                                    html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + val.path + '"></i></li>\n';
                                                }
                                                fileArr.push(val.path)
                                            });
                                            var fileurl = fileArr.join(',');
                                            Fun.toastr.loading();
                                            Fun.toastr.success(__('Choose Success'), function () {
                                                var inptVal = input.val();
                                                if (uploadNum === 1) {
                                                    input.val(fileurl)
                                                    uploadList.html(html)
                                                } else {
                                                    if (inptVal) {
                                                        input.val(inptVal + ',' + fileurl);
                                                    } else {
                                                        input.val(fileurl)
                                                    }
                                                    uploadList.append(html)
                                                }
                                                Fun.toastr.close()
                                            });
                                        }
                                    })
                                })
    
                            });
                        }
                    },
    
                    /**
                     * 选择文件
                     */
                    selectFiles: function (formObj) {
                        var fileSelectList = formObj !== undefined ? formObj.find("*[lay-filter='upload-select']") : $("*[lay-filter='upload-select']");
                        if (fileSelectList.length > 0) {
                            layui.each(fileSelectList, function (i, v) {
                                $(this).click(function (e) {
                                    var _t = $(this);
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var uploadType = dataOptions.type, uploadNum = dataOptions.num, uploadMime = dataOptions.mime, url = dataOptions.selecturl, path = dataOptions.path;
                                    uploadMime = uploadMime || '';
                                    uploadType = uploadType ? uploadType : 'radio';
                                    uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                                    var input = _t.parents('.layui-upload').find('input[type="text"]');
                                    var token = _t.parents('form').find('input[name="__token__"]');
                                    var uploadList = _t.parents('.layui-upload').find('.layui-upload-list');
                                    var id = _t.attr('id');
                                    url = url ? url : Fun.url(Upload.init.requests.select_url + '?' +
                                        '&elem_id=' + id + '&num=' + uploadNum + '&type=' + uploadType + '&mime=' + uploadMime +
                                        '&path=' + path + '&type=' + uploadType);
                                    var parentiframe = Fun.api.checkLayerIframe();
                                    options = {
                                        title: __('Filelist'), type: 2,
                                        url: Fun.url(url), method: 'get',
                                        success: function (layero, index) {
                                            var body = layui.layer.getChildFrame('body', index);
                                            if (parentiframe) {
                                                body = parent.layui.layer.getChildFrame('body', index);
                                            }
                                            __token__ = body.find('input[name="__token__"]').val();
                                            //token失效
                                            token.val(__token__)
                                        },
                                        yes: function (index, layero) {
                                            try {
                                                $(document).ready(function () {
                                                    // 父页面获取子页面的iframe
                                                    var body = layui.layer.getChildFrame('body', index);
                                                    if (parentiframe) {
                                                        body = parent.layui.layer.getChildFrame('body', index);
                                                    }
                                                    li = body.find('.box-body .file-list-item li.active');
                                                    if (li.length === 0) {
                                                        Fun.toastr.error(__('please choose file'));
                                                        return false;
                                                    }
                                                    var fileArr = [], html = '';
                                                    layui.each(li, function (index, val) {
                                                        var type = $(this).data('type'), url = $(this).data('path');
                                                        if (type.indexOf('image') >= 0) {
                                                            html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + url + '" alt=""><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + url + '"></i></li>\n';
                                                        } else if (type.indexOf('video') >= 0) {
                                                            html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/video.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + url + '"></i></li>\n';
                                                        } else if (type.indexOf('audio') >= 0) {
                                                            html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/audio.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + url + '"></i></li>\n';
                                                        } else if (type.indexOf('zip') >= 0) {
                                                            html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/zip.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + url + '"></i></li>\n';
                                                        } else {
                                                            html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + url + '"></i></li>\n';
                                                        }
                                                        fileArr.push(url)
                                                    });
                                                    var fileurl = fileArr.join(',');
                                                    var inptVal = input.val();
                                                    if (uploadNum === 1) {
                                                        input.val(fileurl)
                                                        uploadList.html(html)
                                                    } else {
                                                        if (inptVal) {
                                                            input.val(inptVal + ',' + fileurl);
                                                        } else {
                                                            input.val(fileurl)
                                                        }
                                                        uploadList.append(html)
                                                    }
                                                    layui.layer.close(index) || parent.layui.layer.close(index)
                                                })
                                            } catch (err) {
                                                Fun.toastr.error(err)
                                            }
                                            return false;
                                        }
                                    }
                                    var index = Fun.api.open(options)
                                })
                            });
                        }
                    },
                    /**
                     * json
                     */
                    json: function (formObj) {
                        var jsonList = formObj !== undefined ? formObj.find("*[lay-filter='json']") : $("*[lay-filter='json']");
                        if (jsonList.length > 0) {
                            require(['jsoneditor'],function(JSONEditor){
                                layui.each(jsonList, function (i, v) {
                                    var _t = $(this);
                                    // 配置参数
                                    var id = $(this).attr('id');
                                    window['json-'+id] = new JSONEditor(this,  {
                                        mode: _t.data('mode') || 'tree',
                                        modes: ['code', 'form', 'text', 'tree', 'view', 'preview'], // allowed modes
                                        // onEvent:function (node, event){
                                        // },
                                        onModeChange: function (newMode, oldMode) {
                                            console.log('Mode switched from', oldMode, 'to', newMode)
                                        }  ,
                                        onChangeJSON:function (json) {

                                        },
                                        onChangeText:function (text) {
                                            // 数据发生变化，改变之后的字符串
                                            _t.prev('input').val(text)
                                        }, onError:function (error) {
                                            // 主动的修改已触发发生错误时
                                        },
                                    });
                                    // window['json-'+id].on('change',function(text) {
                                    //     // Do something
                                    //     console.log(text)
                                    //     _t.prev('input').val(text)
                                    // });

                                    // 显示的数据
                                    var initialJson = $(this).prev('input').val() ;
                                    if(initialJson){
                                        initialJson  = JSON.parse(initialJson);
                                    }else{
                                        initialJson  = {}
                                    }
                                    window['json-'+id].set(initialJson)
                                });

                            })
                        }
                    },
                    transfer: function (formObj) {
                        var transferList = formObj !== undefined ? formObj.find("*[lay-filter='transfer']") : $("*[lay-filter='transfer']");
                        if (transferList.length > 0) {
                            transfer = layui.transfer || parent.layui.transfer;
                            layui.each(transferList, function (i, v) {
                                    var _t = $(this);
                                    // 配置参数
                                    var id = _t.attr('id');
                                    var dataOptions = Fun.api.getElementData(_t);
                                    var val =  dataOptions.value;
                                    val =  typeof val == 'string' ? val.split(','):[val];
                                window['transfer-'+id] = transfer.render({
                                        elem:this,
                                        id:id,
                                        data:dataOptions.data,
                                        title:dataOptions.title,
                                        value:val,
                                        showSearch:dataOptions.search || dataOptions.showsearch ||true ,
                                        width:dataOptions.width || 200 ,
                                        height:dataOptions.height || 360 ,
                                        text: {
                                            none: dataOptions.none || '无数据', // 没有数据时的文案
                                            searchNone: dataOptions.searchNone || '无匹配数据' // 搜索无匹配数据时的文案
                                        },onchange: function(data, index){
                                            var datas = transfer.getData(id);
                                            var ids = datas.map(function(item){
                                                return item.value;
                                            })
                                            _t.parent('div').find('input[name="'+_t.data('name')+'"]').val(ids)
                                        }, parseData: function(res){ // 解析成规定的 data 格式
                                            return {
                                                "value": res['id'], // 数据值
                                                "title":res['title'] || res['name'], // 数据标题
                                                "disabled": res['disabled'] || false,  // 是否禁用
                                                "checked": res['checked'] || false // 是否选中
                                            };
                                        }
                                    })
                                });
                        }
                    },
                    /**
                     * 绑定事件
                     * @param form
                     * @param success
                     * @param error
                     * @param submit
                     */
                    bindEvent: function (form, success, error, submit) {
                        form = typeof form == 'object' ? form : $(form);
                        var events = Form.events;
                        events.uploads(form); //上传
                        events.json(form); //上传
                        events.choosefiles(form);//选择文件
                        events.selectfiles(form); //选择文件页面类型
                        events.cropper(form); //上传
                        events.icon(form);
                        events.xmselect(form);
                        events.color(form);
                        events.tags(form);
                        events.city(form);
                        events.date(form);
                        events.rate(form);
                        events.slider(form);
                        events.timepicker(form);
                        events.datepicker(form);
                        events.editor(form);
                        events.formarray(form);
                        events.select(form);
                        events.selectcx(form);
                        events.selectpage(form);
                        events.autocomplete(form);
                        events.verifys(form);
                        events.required(form);
                        events.selects(form);
                        events.transfer(form);
                        events.submit(form, success, error, submit);
                        events.bindevent(form);
                        events.events();//事件
    
                        //初始化数据
                        this.initForm();
                    },
                },
            };
            return Form;
        });
