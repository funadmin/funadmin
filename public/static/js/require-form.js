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
                var list = formObj !== undefined ? formObj.find("*[lay-filter='autoComplete']") : $("*[lay-filter='autoComplete']");
                if (list.length > 0) {
                    require(['autoComplete'], function (autoComplete) {
                        layui.each(list, function (i, v) {
                            var _t = $(this), data = _t.data('data'), src = _t.data('src') || _t.data('url'),
                                id = _t.attr('id') || _t.data('id'), keys = _t.data('keys') || null;
                            if (keys) keys = [keys];
                            if (data.length == 0) {
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
            selectplus: function (formObj) {
                var selectplus = {},
                    list = formObj !== undefined ? formObj.find("*[lay-filter='selectPlus']") : $("*[lay-filter='selectPlus']");
                if (list.length > 0) {
                    require(['selectPlus'], function (selectPlus) {
                        selectPlus = layui.selectPlus || parent.layui.selectPlus;
                        layui.each(list, function (i) {
                            var _t = $(this);
                            var id = _t.prop('id'), name = _t.attr('name') || 'id',
                                verify = _t.data('verify') || _t.attr('verify'),
                                url = _t.data('url') || _t.data('request'),
                                data = _t.data('data') || [],
                                type = _t.attr('multiple') || _t.data('multiple') ? 'checkbox' : 'radio',
                                method = _t.data('method') ? $(this).data('method') : 'get',
                                values = _t.data('value') ? $(this).data('value') : '',
                                attr = _t.data('attr'),
                                attr = typeof attr === 'string' ? attr.split(',') : ['id', 'title'],
                                where = _t.data('where'), delimiter = _t.data('delimiter') || ',',
                                fielddelimiter = _t.data('fielddelimiter') || '、';
                            if (typeof values === 'string') {
                                values = values.split(',')
                            } else if (typeof values === 'number') {
                                values = [values];
                            }
                            options = {
                                el: this, data: data, url: url, type: type, name: name,
                                field: attr, values: values, method: method, where: where,
                                delimiter: delimiter, fielddelimiter: fielddelimiter, verify: verify,
                            };
                            window['selectplus-' + id] = selectPlus.render(options);
                        })
                    })
                }
            },
            selectn: function (formObj) {
                var selectn = {},
                    list = formObj !== undefined ? formObj.find("*[lay-filter='selectN']") : $("*[lay-filter='selectN']");
                if (list.length > 0) {
                    require(['selectN'], function (selectN) {
                        selectN = layui.selectN || parent.layui.selectN;
                        layui.each(list, function (i) {
                            var _t = $(this);
                            var id = _t.prop('id'), name = _t.attr('name') || 'id',
                                verify = _t.data('verify') || _t.attr('verify'),
                                url = _t.data('url') || _t.data('request'),
                                data = _t.data('data') || '',
                                method = _t.data('method') ||  'get',
                                last = _t.data('last') ||  '',
                                values = _t.data('value') || '',
                                search = _t.data('search') || _t.attr('lay-search');
                            if (search !== undefined) {
                                search = true;
                            }
                            attr = _t.data('attr'),
                                attr = typeof attr === 'string' ? attr.split(',') : ['id', 'title'],
                                num = _t.data('num') ? _t.data('num') : 3,
                                pid = _t.data('pid') || 'pid',
                                delimiter = _t.data('delimiter') || ',',
                                options = {
                                    elem: this, data: data, url: url, name: name, pid: pid, formFilter: id,
                                    field: attr, selected: values, method: method, search: search, num: num,
                                    delimiter: delimiter, last: last, verify: verify,
                                };
                            window['selectn-' + id] = selectN(options).render();
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
                            value = _t.val() || _t.data("init");
                            var id = _t.prop('id'), name = _t.attr('name') || 'id',
                                verify = _t.data('verify') || _t.attr('verify'),
                                url = _t.data('url') || _t.data('request'), isTree = _t.data('istree'),
                                isHtml = _t.data('ishtml'),
                                data = _t.data('data'), field = _t.data('field') || 'title',
                                pageSize = _t.data('pagesize') || 12,
                                primaryKey = _t.data('primarkey') || 'id',
                                selectOnly = _t.data('selectonly') || false,
                                pagination = !(_t.data('pagination') == 'false' || _t.data('pagination') == 0),
                                listSize = _t.data('listsize') || '15',
                                multiple = _t.data('multiple') || false, dropButton = _t.data('dropbutton') || true,
                                maxSelectLimit = _t.data('maxselectlimit ') || 0,
                                searchField = _t.data('searchfield') || field,
                                searchKey = _t.data('searchkey') || primaryKey,
                                orderBy = _t.data('orderby') || false,
                                method = _t.data('method') || 'GET', dbTable = _t.data('dbtable'),
                                selectToCloseList = _t.data('selecttocloselist') || true,
                                disabled = _t.data('disabled') || false,
                                andOr = _t.data('andor'), formatItem = _t.data('formatitem') || false,
                                verify = _t.attr('lay-verify') || '';
                            orderBy = layui.type(orderBy) == 'string' ? [orderBy] : orderBy;
                            isHtml != undefined ? isHtml : true;
                            eSelect = _t.data('eselect');
                            if (!value && window.FormArray && window.FormArray[name]) {
                                _t.val(window.FormArray[name]);
                            }
                            var options = {
                                showField: field, keyField: primaryKey, pageSize: pageSize,
                                selectFields: searchField, searchKey: searchKey, isTree: isTree, isHtml: isHtml,
                                data: data || Fun.url(url), dbTable: dbTable, andOr: andOr, method: method,
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
                            var id = _t.prop('id'),
                                url = _t.data('url') || _t.data('request'), lang = _t.data('lang'),
                                value = _t.data('value') || _t.attr('value'),
                                data = _t.data('data') || [], parentfield = _t.data('parentfield') || 'pid',
                                tips = _t.data('tips') || '请选择', searchTips = _t.data('searchtips') || '请选择',
                                empty = _t.data('empty') || '呀,没有数据', height = _t.data('height') || 'auto',
                                paging = _t.data('paging'), pageSize = _t.data('pagesize'),
                                remoteMethod = _t.data('remotemethod'), content = _t.data('content') || '',
                                radio = _t.data('radio'), disabled = _t.data('disabled'),
                                autoRow = _t.data('autorow') !== false,
                                clickClose = _t.data('clickclose'), prop = _t.data('prop') || _t.data('attr'),
                                max = _t.data('max'), create = _t.data('create'), on = $(this).data('on'),
                                repeat = !!_t.data('repeat'),
                                theme = _t.data('theme') || '#4d70ff',
                                name = _t.attr('name') || _t.data('name') || 'pid',
                                style = _t.data('style') || {},
                                cascader = _t.data('cascader') ? {show: true, indent: 200, strict: false} : false,
                                layVerify = _t.attr('lay-verify') || _t.data('layverify') || '',
                                layReqText = _t.attr('lay-reqtext') ||  _t.data('layreqtext') || '';
                            layVerType = _t.attr('lay-vertype') ||  _t.data('layvertype')  || 'tips'
                            var size = _t.data('size') || 'medium';
                            toolbar = _t.data('toolbar') == false ? {show: false} : {
                                show: true,
                                list: ['ALL', 'CLEAR', 'REVERSE']
                            }
                            var filterable = !!($(this).data('filterable') === undefined || _t.data('filterable'));
                            var remoteSearch = !!($(this).data('remotesearch') !== undefined && _t.data('remotesearch'));
                            var pageRemote = !$(this).data('pageremote') ? false : true, props, propArr, options;
                            var tree = _t.data('tree');
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
                                el: this, language: lang, data: data, initValue: value, name: name, prop: props,
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
                            if (data.toString() === '' && url) {
                                searchData = {
                                    selectFields: selelectFields,
                                    tree: tree.show,
                                    parentField: parentfield
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
                        var id = _t.prop('id');
                        var data = _t.data();
                        var name = _t.prop('name');
                        var path = _t.data('path');
                        _t.html(window.FormArray[name]);
                        var upload_url = (data.url ? data.url : Fun.url(Upload.init.requests.upload_url)) + '?editor=tinymce&path=' + path;
                        if ($(this).data('editor') == 'tinymce') {
                            if ($("body").find('script[src="/static/plugins/tinymce/tinymce.min.js"]').length == 0) {
                                $('body').append($("<script defer referrerpolicy='origin' src='/static/plugins/tinymce/tinymce.min.js'></script>"));
                            }
                            window['editor-' + id] = tinymce.init({
                                selector: '#' + id + '[lay-editor]',
                                language: data.language ||  'zh-Hans',
                                plugins: data.plugins ? data.plugins : 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
                                editimage_cors_hosts: ['picsum.photos'],
                                menubar: 'file edit view insert format tools table help',
                                toolbar: data.toolbar ? data.toobar : 'undo redo  bold italic underline strikethrough  fontfamily fontsize | blocks  alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  insertfile image media preview  template link  | print  anchor codesample save  ltr rtl ',
                                toolbar_sticky: false,
                                toolbar_sticky_offset: isSmallScreen ? 102 : 108,
                                autosave_ask_before_unload: true,
                                autosave_interval: '3s',
                                autosave_prefix: '{path}{query}-{id}-',
                                autosave_restore_when_empty: false,
                                autosave_retention: '20m',
                                image_advtab: true,
                                height: (data.height ? data.height : 650), //编辑器高度
                                min_height: (data.maxheight ? data.maxheight : 400),
                                content_style: "body { font-family:Helvetica,Arial,sans-serif; font-size:16px } img {max-width:100%;}",
                                fontsize_formats: '12px 14px 16px 18px 24px 36px 48px 56px 72px,128px',
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
                                        $('textarea[name="' + name + '"]').val(val);
                                    });
                                },
                                importcss_append: true,
                                images_upload_url: upload_url,
                                images_upload_base_path: '',
                                templates: [
                                    {
                                        title: 'New Table',
                                        description: 'creates a new table',
                                        content: '<div class="mceTmpl"><table width="98%%"  border="0" cellspacing="0" cellpadding="0"><tr><th scope="col"> </th><th scope="col"> </th></tr><tr><td> </td><td> </td></tr></table></div>'
                                    },
                                    {
                                        title: 'Starting my story',
                                        description: 'A cure for writers block',
                                        content: 'Once upon a time...'
                                    },
                                    {
                                        title: 'New list with dates',
                                        description: 'New List with dates',
                                        content: '<div class="mceTmpl"><span class="cdate">cdate</span><br><span class="mdate">mdate</span><h2>My List</h2><ul><li></li><li></li></ul></div>'
                                    }
                                ],
                                template_cdate_format: '[Date Created (CDATE): %m/%d/%Y : %H:%M:%S]',
                                template_mdate_format: '[Date Modified (MDATE): %m/%d/%Y : %H:%M:%S]',
                                image_caption: true,
                                quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
                                noneditable_class: 'mceNonEditable',
                                toolbar_mode: 'sliding',
                                contextmenu: 'link image table',
                                skin: useDarkMode ? 'oxide-dark' : 'oxide',
                                content_css: useDarkMode ? 'dark' : 'default',
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
                                                failure('HTTP Error: ' + xhr.status);
                                                return;
                                            }
                                            json = JSON.parse(xhr.responseText);
                                            if (!json || typeof json.location != 'string') {
                                                failure('Invalid JSON: ' + xhr.responseText);
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
                var list = formObj !== undefined ? formObj.find("*[lay-filter='tags']") : $("*[lay-filter='tags']");
                if (list.length > 0) {
                    require(['inputTags'], function (inputTags) {
                        layui.each(list, function () {
                            var _t = $(this),
                                content = [];
                            var tag = _t.parents('.tags').find('input[type="hidden"]').val();
                            if (tag) content = tag.substring(0, tag.length - 1).split(',');
                            var id = _t.prop('id');
                            var data = _t.data();
                            var inputTags = layui.inputTags ? layui.inputTags : parent.layui.inputTags;
                            window['tags-' + id] = inputTags.render({
                                elem: this,
                                content: content,
                                done: (data.done ? eval(data.done(value)) : function (value) {
                                })
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
                            var _t = $(this);var url = _t.data('url');
                            var data = _t.data();var id = _t.prop('id');
                            window['icon-' + id] = layui.iconPicker.render({
                                elem: this,
                                type: 'fontClass',
                                value:_t.prop('value') || _t.data('value'),
                                search: true,
                                page: true,
                                limit: 12,
                                click: function (data) {
                                    _t.val(data.icon)
                                },
                                success: data.done ? eval(data.done(d)) : function (d) {
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
                        var _t = $(this), name = _t.data('name'), format = _t.data('format') || 'hex',
                            data = _t.data();
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
                            done: data.done ? eval(data.done(color)) : function (color) {
                                _t.prev('input[name="' + name + '"]').val(color)
                            }
                        })
                    })
                }
            },
            region: function (formObj) {
                var list = formObj !== undefined ? formObj.find("*[lay-filter='region']") : $("*[lay-filter='region']");
                if (list.length > 0) {
                    require(['regionCheckBox'], function (regionCheckBox) {
                        layui.each(list, function () {
                            var _t = $(this);
                            var id = _t.prop('id'),
                                name = _t.attr('name');
                            value = _t.data('value') || [];
                            if (value && typeof value === 'string') {
                                value = value.split(',');
                            }
                            window['region-' + id] = layui.regionCheckBox.render({
                                elem: this,
                                name: name,
                                value: value,
                                width: '550px',
                                border: true,
                                ready: function () {
                                    _t.prev('input[type="hidden"]').val(getAllChecked())
                                },
                                change: function (result) {
                                    _t.prev('input[name="' + name + '"]').val(getAllChecked())
                                }
                            });

                            function getAllChecked() {
                                var all = '';
                                _t.find("input:checkbox[name='" + id + name + "']:checked").each(function () {
                                    all += $(this).val() + ','
                                });
                                return all.substring(0, all.length - 1)
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
                            var id = _t.prop('id'),
                                name = _t.prop('name');
                            var provinceId = _t.data('provinceid'),
                                cityId = _t.data('cityid');
                            var province, city, district;
                            if (window.FormArray[name]) {
                                var cityValue = window.FormArray[name];
                                province = cityValue.split('/')[0];
                                city = cityValue.split('/')[1];
                                district = cityValue.split('/')[2];
                            }
                            var districtId = _t.data('districtid');
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
                        var id = $(this).prop('id') || $(this).attr('id');
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
                                        sTime = Dayjs().startOf("w");
                                        eTime = Dayjs().endOf("w");
                                        return [sTime, eTime];
                                    }()
                                },
                                {
                                    text: "上周",
                                    value: function () {
                                        sTime = Dayjs().subtract(1, "w").startOf("w");
                                        eTime = Dayjs().subtract(1, "w").endOf("w");
                                        return [sTime, eTime];
                                    }()
                                },
                                {
                                    text: "下周",
                                    value: function () {
                                        sTime = Dayjs().subtract(-1, "w").startOf("w");
                                        eTime = Dayjs().subtract(-1, "w").endOf("w");
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
                        var data = $(this).data(), format = $(this).data('format'), type = $(this).data('type'),
                            id = $(this).prop('id') || $(this).data('id'),
                            value = $(this).data('value') || $(this).val(), range = $(this).data('range');
                        var options = {
                            elem: this,
                            type: data.type || 'datetime',
                            trigger: 'click',
                            calendar: true,
                            theme: data.theme || '#4d70ff'
                        };
                        if (format !== undefined && format !== '' && format != null) {
                            options['format'] = format
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
                        if (data.fullPanel != false) {
                            options['fullPanel'] = true;// 2.8+
                        }
                        if (data.mark) {
                            options['mark'] = mark;
                        }
                        if (data.holidays) {
                            options['holidays'] = holidays;
                        }
                        if (data.min) {
                            options['min'] = min;
                        }
                        if (data.max) {
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
                        var _t = $(this), id = _t.prop('id'), name = _t.data('name')
                        options = _t.data('options') || {};
                        options.elem = this;
                        options.value = _t.data('value');
                        options.length = options.length ? options.length : 5;
                        options.theme = options.theme == undefined ? '#1E9FFF' : options.length;
                        options.readonly = options.readonly || options.disabled ? true : false;
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
                        var _t = $(this), id = _t.prop('id'), name = _t.data('name');
                        options = _t.data('options') || {};
                        options.elem = this;
                        options.value = _t.data('value');
                        options.type = _t.data('type') || 'default';
                        options.step = _t.data('step') || 1;
                        options.range = _t.data('range') || false;
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
                    console.log(rows)
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
                });
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

            //验证
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
                    qq: [/^[1-9]\d{4,}$/, "请填写有效的QQ号"]
                });
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
            submit: function (formObj, success, error, submit) {
                var formList = $("[lay-submit]");
                // 表单提交自动处理
                if (formList.length > 0) {
                    layui.each(formList, function (i) {
                        var filter = $(this).attr('lay-filter'),
                            type = $(this).data('type'),
                            refresh = $(this).data('refresh'),
                            url = $(this).data('request') || $(this).data('url') || $('form[lay-filter="' + filter + '"]').attr('action');
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
                var fileurl = othis.data('fileurl'),
                    that;
                var confirm = Fun.toastr.confirm(__('Are you sure？'), function () {
                    that = othis.parents('.layui-upload-list').parents('.layui-upload');
                    var input = that.find('input[type="text"]');
                    var inputVal = input.val();
                    var input_temp;
                    if (othis.parents('li').index() === 0) {
                        input_temp = inputVal.replace(fileurl, '');
                        input.val(input_temp);
                    } else {
                        input_temp = inputVal.replace(',' + fileurl, '');
                        input.val(input_temp);
                    }
                    othis.parents('li').remove();
                    Fun.toastr.close(confirm);
                });
                return false;
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
            /**
             * 初始化表格数据
             */
            initForm: function (data) {
                if(data){
                    layui.form.val("form", data);
                }else if (window.FormArray) {
                    layui.form.val("form", window.FormArray);
                }
                layui.form.render();
                require(['multiSelect'], function (multiSelect) {
                    multiSelect = layui.multiSelect ? layui.multiSelect : parent.layui.multiSelect;
                    multiSelect.render();
                })
            },
            /**
             * 选择文件
             */
            chooseFiles: function (formObj) {
                var fileChooseList = formObj !== undefined ? formObj.find("*[lay-filter='upload-choose']") : $("*[lay-filter='upload-choose']");
                if (fileChooseList.length > 0) {
                    require(['tableSelect', 'table'], function (tableSelect, Table) {
                        layui.each(fileChooseList, function (i, v) {
                            var data = $(this).data();
                            if (typeof data.value == 'object') data = data.value;
                            var uploadType = data.type, uploadNum = data.num, uploadMime = data.mime,
                                url = data.tableurl, path = data.path;
                            uploadMime = uploadMime || '*';
                            uploadType = uploadType ? uploadType : 'radio';
                            uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                            var input = $(this).parents('.layui-upload').find('input[type="text"]');
                            var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                            var id = $(this).attr('id');
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
                            var data = $(this).data();
                            if (typeof data.value == 'object') data = data.value;
                            uploadType = data.type, uploadNum = data.num, uploadMime = data.mime, url = data.selecturl, path = data.path;
                            uploadMime = uploadMime || '';
                            uploadType = uploadType ? uploadType : 'radio';
                            uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                            var input = $(this).parents('.layui-upload').find('input[type="text"]');
                            var token = $(this).parents('form').find('input[name="__token__"]');
                            var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                            var id = $(this).attr('id');
                            console.log(uploadType)
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
                events.region(form);
                events.formarray(form);
                events.selectplus(form);
                events.selectn(form);
                events.selectpage(form);
                events.autocomplete(form);
                events.verifys(form);
                events.required(form);
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
