// +----------------------------------------------------------------------
// | FunAdmin极速开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

define(['jquery', 'selectPage','xmSelect', 'iconPicker', 'cityPicker', 'inputTags', 'timePicker', 'regionCheckBox','multiSelect', 'upload','selectN','selectPlus'
], function($,selectPage, xmSelect, iconPicker, cityPicker, inputTags, timePicker, regionCheckBox, multiSelect, Upload, selectN,selectPlus) {
    var Fu = {
        init: {},
        events: {
            selectplus:function() {
                var selectplus ={},list = $("*[lay-filter='selectPlus']");
                if (list.length > 0) {
                    selectPlus = layui.selectPlus || parent.layui.selectPlus;
                    layui.each(list, function(i) {
                        var _that = $(this);
                        var id =_that.prop('id'), name =_that.attr('name') || 'id',verify = _that.data('verify') || _that.attr('verify'),
                            url =_that.data('url')||_that.data('request'),
                            data =_that.data('data')||　[], type =_that.attr('multiple') ||_that.data('multiple') ?'checkbox':'radio',
                            method =_that.data('method')?$(this).data('method'):'get',
                            values =_that.data('value')?$(this).data('value'):'',
                            attr =_that.data('attr'), attr = typeof attr ==='string' ?attr.split(','):['id','title'],
                            where =_that.data('where'), delimiter =_that.data('delimiter') || ',',
                            fielddelimiter =_that.data('fielddelimiter') || '、';
                        if(typeof values ==='string') {
                            values = values.split(',')
                        }else if(typeof values ==='number'){
                            values = [values];
                        }
                            options = {
                                el: '#' + id, data:data, url: url, type: type,  name: name,
                                field: attr, values: values, method: method, where: where,
                                delimiter: delimiter, fielddelimiter: fielddelimiter,verify:verify,
                            };
                        selectplus[i] = selectPlus.render(options);
                    })
                }
            },
            selectn:function() {
                var selectn ={},list = $("*[lay-filter='selectN']");
                if (list.length > 0) {
                    selectN = layui.selectN || parent.layui.selectN;
                    layui.each(list, function(i) {
                        var _that = $(this);
                        var id = _that.prop('id'), name = _that.attr('name') || 'id',verify = _that.data('verify') || _that.attr('verify'),
                            url = _that.data('url') || _that.data('request'),
                            data = _that.data('data')||　'',
                            method = _that.data('method')?_that.data('method'):'get',
                            last = _that.data('last')?_that.data('last'):'',
                            values = _that.data('value')?_that.data('value'):'',
                            search = _that.data('search')?_that.data('search'):'',
                            attr = _that.data('attr'), attr= typeof attr ==='string' ?attr.split(','):['id','title'],
                            num = _that.data('num')?_that.data('num'):3,
                            pid = _that.data('pid') ||　'pid',
                            delimiter = _that.data('delimiter') || ',',
                            options = {
                                elem: '#' + id, data: data, url: url, name: name,pid:pid,formFilter:id,
                                field: attr, selected: values, method: method,search:search,num:num,
                                delimiter: delimiter,last:last,verify:verify,
                            };
                        selectn[i] =  selectN(options).render();
                    })
                }
            },
            selectpage:function() {
                var list = $("*[lay-filter='selectPage']");
                if (list.length > 0) {
                    selectPage = layui.selectPage || parent.layui.selectPage;
                    layui.each(list, function(i) {
                        var _that = $(this);
                        var id = _that.prop('id'), name = _that.attr('name') || 'id',verify = _that.data('verify') || _that.attr('verify'),
                            url = _that.data('url') || _that.data('request'),
                            data = _that.data('data'), field = _that.data('field') ||　'title',
                            primaryKey = _that.data('primarkey') ||　'id', selectOnly = _that.data('selectonly') ||　false,
                            pagination = !(_that.data('pagination') == 'false' || _that.data('pagination') == 0), listSize = _that.data('listsize') ||　'15',
                            multiple = _that.data('multiple') ||　false, dropButton  = _that.data('dropbutton') ||　true,
                            maxSelectLimit  = _that.data('maxselectlimit ') ||　0, searchField   = _that.data('searchfield') || field,
                            searchKey =_that.data('searchkey') ||　primaryKey, orderBy    = _that.data('orderby') ||　false,
                            method    = _that.data('method') ||　'GET', dbTable    = _that.data('dbtable'),
                            selectToCloseList  =_that.data('selecttocloselist') ||　 false,disabled = _that.data('disabled') || false,
                            andOr =_that.data('andor'),formatItem = _that.data('formatitem') || false,required = _that.data('required') || ''
                            orderBy = layui.type(orderBy)=='string'?[orderBy]:orderBy;
                            options = {
                                showField : field, keyField :primaryKey,
                                selectFileds:searchField,searchKey:searchKey,
                                data : data || Fun.url(url), dbTable : dbTable, andOr : andOr, method:method,
                                //仅选择模式，不允许输入查询关键字
                                selectOnly : selectOnly,required : required, selectToCloseList:selectToCloseList,
                                //关闭分页栏，数据将会一次性在列表中展示，上限200个项目
                                pagination : pagination, maxSelectLimit : maxSelectLimit, orderBy : orderBy,
                                //关闭分页的状态下，列表显示的项目个数，其它的项目以滚动条滚动方式展现（默认10个）
                                listSize : listSize, multiple : multiple, dropButton : dropButton,
                                formatItem : function(res){
                                    if(formatItem)  return eval(formatItem);
                                    return res[this.showField];
                                },
                                eSelect : function(res){},
                                selectToCloseList:function(res){},
                                eAjaxSuccess: function(res) {
                                    row = res.data;data={};
                                    data.list = typeof row.data !== 'undefined' ? row.data : [];
                                    data.totalRow = typeof row.count !== 'undefined' ? row.count : row.data.length;
                                    return data;
                                }
                            };_that.selectPage(options);
                            if(disabled){_that.selectPageDisabled(true);}
                    })
                }
            },
            xmSelect: function() {
                var xmselectobj ={},list = $("*[lay-filter='xmSelect']");
                if (list.length > 0) {
                    layui.each(list, function(i) {
                        var id = $(this).prop('id'),
                            url = $(this).data('url')|| $(this).data('request'), lang = $(this).data('lang'), value = $(this).data('value'),
                            data = $(this).data('data')||　[], parentfield =  $(this).data('parentfield') || 'pid',
                            tips = $(this).data('tips') ||  '请选择', searchTips = $(this).data('searchtips') || '请选择',
                            empty = $(this).data('empty') || '呀,没有数据', height = $(this).data('height') || 'auto',
                            paging = $(this).data('paging'), pageSize = $(this).data('pagesize'),
                            remoteMethod = $(this).data('remotemethod'), content = $(this).data('content') || '',
                            radio = $(this).data('radio'), disabled = $(this).data('disabled'),autoRow =  $(this).data('autorow') !== false,
                            clickClose = $(this).data('clickclose'), prop = $(this).data('prop') || $(this).data('attr'),
                            max = $(this).data('max'), create = $(this).data('create'), repeat = !! $(this).data('repeat'),
                            theme = $(this).data('theme') || '#1890ff', name = $(this).attr('name') || $(this).data('name') || 'pid',
                            style = $(this).data('style') || {}, cascader = $(this).data('cascader') ? {show: true, indent: 200, strict: false} : false,
                            layVerify = $(this).attr('lay-verify') || '', layReqText = $(this).data('reqtext') || '';
                        var size = $(this).data('size') || 'medium' ;toolbar = $(this).data('toolbar')==false ?{show: false}: {show: true, list: ['ALL', 'CLEAR', 'REVERSE']}
                        var filterable = !! ($(this).data('filterable') === undefined || $(this).data('filterable'));
                        var remoteSearch = !!($(this).data('remotesearch') !== undefined && $(this).data('remotesearch'));
                        var pageRemote = !$(this).data('pageremote')?false:true, props, propArr, options;
                        var tree = $(this).data('tree');
                        if(remoteSearch) toolbar.show=true;filterable=true;
                        if(typeof tree ==='object'){
                            tree = tree;
                        }else{
                            tree = tree ?{show: true,showFolderIcon: true, showLine: true, indent: 20, expandedKeys: [], strict: false, simple: false, clickExpand: true, clickCheck: true, }:false;
                        }
                        if (typeof value != 'object' && value) {
                            value = typeof value === "number" ? [value] : value.split(',')
                        };props = {
                            name: 'title',
                            value: "id"
                        };selelectFields = {
                            name: 'title',
                            value: "id"
                        };if (prop) {
                            propArr = prop.split(',');
                            props.name = propArr[0];
                            props.value = propArr[1];
                            selelectFields = {name:props.name}
                            selelectFields.value = propArr[1]== props.name  ?'id': propArr[1];
                        };lang = lang ? lang : 'zh';paging = paging === undefined || paging !== 'false';
                        pageSize = pageSize ? pageSize : 10;radio = !! radio;disabled = !! disabled;max = max ? max : 0;
                        clickClose = clickClose ? clickClose : false;create = !create ?
                            function(val) {
                                return {
                                    name: val,
                                    value: val
                                }
                            } : eval(create)?eval(create):false;
                        xmSelect = window.xmSelect ? window.xmSelect : parent.window.xmSelect;
                        options = {
                            el: '#' + id, language: lang, data: data, initValue: value, name: name,prop: props,
                            tips: tips, empty: empty, searchTips: searchTips, disabled: disabled,
                            filterable: filterable,remoteSearch: remoteSearch,
                            remoteMethod: function(val, cb, show) {
                                if (remoteMethod && remoteMethod !== undefined) {
                                    eval(remoteMethod)
                                } else {
                                    var formatFilter = {},
                                        formatOp = {};
                                    formatFilter[props.name] = val;
                                    formatOp[props.name] = '%*%';
                                    Fun.ajax({
                                        method:'get',
                                        url: Fun.url(url?url: window.location.href),
                                        data: {
                                            filter: JSON.stringify(formatFilter),
                                            op: JSON.stringify(formatOp),
                                            selectFields:selelectFields
                                        }
                                    }, function(res) {
                                        cb(res.data)
                                    }, function(res) {
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
                            maxMethod: function(val) {
                            }, on: function(data) {
                            }, create: create,
                        }
                        if (tree) options.tree = tree;
                        if (cascader) options.cascader = cascader;
                        if (style) options.style = style;
                        if (layReqText) options.layReqText = layReqText;
                        if (content) options.content = content;
                        xmselectobj[i] = xmSelect.render(options);
                        if(data.toString()==='' && url){
                            searchData = {selectFields:selelectFields,tree:tree.show,parentField:parentfield}
                            Fun.ajax({
                                method:'GET',
                                url: Fun.url(url?url: window.location.href),
                                data:searchData
                            },function (res) {
                                xmselectobj[i].update({
                                    data: res.data,
                                    autoRow: autoRow,
                                })
                            },function(res){
                                console.log(res);
                            })
                        }
                    })
                }
            },
            editor: function() {
                var list = $("*[lay-filter='editor']");
                if (list.length > 0) {
                    const useDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const isSmallScreen = window.matchMedia('(max-width: 1023.5px)').matches;
                    layui.each(list, function () {
                        var id = $(this).prop('id');
                        var name = $(this).prop('name');
                        var path = $(this).data('path');
                        $(this).html(Config.formData[name]);
                        var upload_url = Fun.url(Upload.init.requests.upload_url) + '?editor=tinymce&path=' + path
                        if ($(this).data('editor') == 2) {
                            if ($("body").find('script[src="/static/plugins/tinymce/tinymce.min.js"]').length == 0) {
                                $('body').append($("<script referrerpolicy='origin' src='/static/plugins/tinymce/tinymce.min.js'></script>"));
                            }
                            window['editor' + id] = tinymce.init({
                                selector: '#' + id,
                                language: 'zh-Hans',
                                plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
                                menubar: 'file edit view insert format tools table help',
                                toolbar: 'undo redo  bold italic underline strikethrough  fontfamily fontsize | blocks  alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  insertfile image media preview  template link  | print  anchor codesample save  ltr rtl ',
                                toolbar_sticky: false,
                                toolbar_sticky_offset: isSmallScreen ? 102 : 108,
                                autosave_ask_before_unload: true,
                                autosave_interval: '3s',
                                autosave_prefix: '{path}{query}-{id}-',
                                autosave_restore_when_empty: false,
                                autosave_retention: '20m',
                                image_advtab: true,
                                height: 650, //编辑器高度
                                min_height: 400,
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
                                init_instance_callback: (editor) => {
                                    editor.on('change', (e) => {
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
                                var filetype = '.pdf, .txt, .zip, .rar, .7z, .doc, .docx, .xls, .xlsx, .ppt, .pptx, .mp3, .mp4';
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
            tags: function() {
                var list = $("*[lay-filter='tags']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var _that = $(this),
                            content = [];
                        var tag = _that.parents('.tags').find('input[type="hidden"]').val();
                        if (tag) content = tag.substring(0, tag.length - 1).split(',');
                        var id = _that.prop('id');
                        var inputTags = layui.inputTags ? layui.inputTags : parent.layui.inputTags;
                        inputTags.render({
                            elem: '#' + id,
                            content: content,
                            done: function(value) {}
                        })
                    })
                }
            },
            icon: function() {
                var list = $("*[lay-filter='iconPickers']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var _that = $(this);
                        var id = _that.prop('id');
                        layui.iconPicker.render({
                            elem: '#' + id,
                            type: 'fontClass',
                            search: true,
                            page: true,
                            limit: 12,
                            click: function(data) {
                                _that.prev("input[type='hidden']").val(data.icon)
                            },
                            success: function(d) {}
                        })
                    })
                }
            },
            color: function() {
                var list = $("*[lay-filter='colorPicker']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var _that = $(this),name= _that.data('name'),format = _that.data('format') || 'hex';
                        var id = _that.prop('id');
                        var color = _that.prev('input').val();
                        layui.colorpicker.render({
                            elem: '#' + id,
                            color: color,
                            predefine: true,
                            alpha: true,
                            format:format,
                            change: function(color) {},
                            done: function(color) {
                                _that.prev('input[name="' + name + '"]').val(color)
                            }
                        })
                    })
                }
            },
            regionCheck: function() {
                var list = $("*[lay-filter='regionCheck']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var _that = $(this);
                        var id = _that.attr('id'),
                            name = _that.attr('name');
                            value = _that.data('value') || [];
                            if(value && typeof value === 'string'){
                                value = value.split(',');
                            }
                        layui.regionCheckBox.render({
                            elem: '#' + id,
                            name: name,
                            value: value,
                            width: '550px',
                            border: true,
                            ready: function() {
                                _that.prev('input[type="hidden"]').val(getAllChecked())
                            },
                            change: function(result) {
                                _that.prev('input[name="'+name+'"]').val(getAllChecked())
                            }
                        });
                        function getAllChecked() {
                            var all = '';
                            _that.find("input:checkbox[name='" + id + name + "']:checked").each(function() {
                                all += $(this).val() + ','
                            });
                            return all.substring(0, all.length - 1)
                        }
                    })
                }
            },
            city: function() {
                var list = $("*[lay-filter='cityPicker']");
                if (list.length > 0) {
                    cityPicker = layui.cityPicker;
                    layui.each(list, function() {
                        var id = $(this).prop('id'),
                            name = $(this).prop('name');
                        var provinceId = $(this).data('provinceid'),
                            cityId = $(this).data('cityid');
                        var province, city, district;
                        if (Config.formData[name]) {
                            var cityValue = Config.formData[name];
                            province = cityValue.split('/')[0]; city = cityValue.split('/')[1];district = cityValue.split('/')[2];
                        }
                        var districtId = $(this).data('districtid');
                        currentPicker = new cityPicker("#" + id, {
                            provincename: provinceId,
                            cityname: cityId,
                            districtname: districtId,
                            level: 'districtId',
                            province: province,
                            city: city,
                            district: district
                        });
                        var str = '';
                        if (Config.formData.hasOwnProperty(provinceId)) {
                            str += ChineseDistricts[886][Config.formData[provinceId]]
                        }
                        if (Config.formData.hasOwnProperty(cityId) && Config.formData[[cityId]] && Config.formData.hasOwnProperty(provinceId)) {
                            str += '/' + ChineseDistricts[Config.formData[provinceId]][Config.formData[cityId]]
                        }
                        if (Config.formData.hasOwnProperty(cityId) && Config.formData[districtId] && Config.formData.hasOwnProperty(districtId)) {
                            str += '/' + ChineseDistricts[Config.formData[cityId]][Config.formData[districtId]]
                        }
                        if (!str) {
                            str = Config.formData.hasOwnProperty(name) ? Config.formData['name'] : ''
                        }
                        currentPicker.setValue(Config.formData[name] ? Config.formData[name] : str)
                    })
                }
            },
            timepicker: function() {
                var list = $("*[lay-filter='timePicker']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var id = $(this).prop('id');
                        layui.timePicker.render({
                            elem: '#' + id,
                            trigger: 'click',
                            options: {
                                timeStamp: false,
                                format: 'YYYY-MM-DD HH:ss:mm',
                            },
                        })
                    })
                }
            },
            date: function() {
                var list = $("*[lay-filter='date']");
                if (list.length > 0) {
                    layui.each(list, function() {
                        var format = $(this).data('format'),
                            type = $(this).data('type'),
                            range = $(this).data('range');
                        if (type === undefined || type === '' || type == null) {
                            type = 'datetime'
                        }
                        var options = {
                            elem: this,
                            type: type,
                            trigger: 'click',
                            calendar: true,
                            theme: '#393D49'
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
                        laydate = layui.laydate ? layui.laydate : parent.layui.laydate;
                        laydate.render(options)
                    })
                }
            },
            rate: function() {
                var ratelist=[];
                var list = $("*[lay-filter='rate']");
                if (list.length > 0) {
                    layui.each(list, function(i) {
                        var _that = $(this),id = _that.prop('id'),name=_that.data('name')
                        options = _that.data('options') || {};
                        options.elem = '#' + id;
                        options.value = _that.data('value');
                        options.length = options.length?options.length:5;
                        options.theme = options.theme==undefined?'#1E9FFF':options.length;
                        options.readonly = options.readonly || options.disabled ? true:false;
                        if(_that.parent('div').find('input[name="'+name+'"]').length==0){
                            _that.before('input[name="'+name+'"]');
                        }
                        if(options.setText){
                            options.setText = function(value){ //自定义文本的回调
                                var arrs = options.setText;
                                this.span.text(arrs[value] || ( value + __("Star")));
                            }
                        }
                        options.choose = function(value){
                            _that.parent('div').find('input[name="'+name+'"]').val(value)
                        }
                        ratelist[i] = layui.rate.render(options);
                    })
                }
            },
            slider: function() {
                var sliderlist=[];
                var list = $("*[lay-filter='slider']");
                if (list.length > 0) {
                    layui.each(list, function(i) {
                        var _that = $(this),id = _that.prop('id'),name=_that.data('name');
                        options = _that.data('options') || {};
                        options.elem = '#' + id;
                        options.value = _that.data('value');
                        options.max = options.max?options.max:100;
                        options.min = options.min?options.min:0;
                        options.disabled = options.readonly || options.disabled ? true:false;
                        options.input = options.input==undefined || options.input ?true:false;
                        options.theme = options.theme==undefined?'#1E9FFF':options.theme;
                        if(_that.parent('div').find('input[name="'+name+'"]').length==0){
                            _that.before('input[name="'+name+'"]');
                        }
                        if(options.setTips){
                            options.setTips = function(value){ //自定义文本的回调
                                return value +  options.setTips;
                            }
                        }
                        options.change = function(value){
                            _that.parent('div').find('input[name="'+name+'"]').val(value)
                        }
                        sliderlist[i] = layui.slider.render(options)
                    })
                }
            },
            addInput: function() {
                $(document).on('click', ".addInput", function() {
                    var name = $(this).data('name'), verify = $(this).data('verify'),
                        num = $(this).parents('.layui-form-item').siblings('.layui-form-item').length + 1;
                    var str = '<div class="layui-form-item">' + '<label class="layui-form-label"></label>' + '<div class="layui-input-inline">' + '<input type="text" name="' + name + '[key][' + num + ']" placeholder="key" class="layui-input input-double-width">' + '</div>' + '<div class="layui-input-inline">\n' + '<input type="text" id="" name="' + name + '[value][' + num + ']" lay-verify="'+verify+'" placeholder="value" autocomplete="off" class="layui-input input-double-width">\n' + '</div>' + '<div class="layui-input-inline">' + '<button data-name="' + name + '" type="button" class="layui-btn layui-btn-danger layui-btn-sm removeInupt"><i class="layui-icon">&#xe67e;</i></button>' + '</div>' + '</div>';
                    $(this).parents('.layui-form-item').after(str)
                })
            },
            removeInupt: function() {
                $(document).on('click', ".removeInupt", function() {
                    var parentEle = $(this).parent().parent();
                    parentEle.remove()
                })
            },
            bindevent: function() {}
        },
        api: {
            bindEvent: function() {
                var events = Fu.events;
                events.icon();
                events.xmSelect();
                events.color();
                events.tags();
                events.city();
                events.date();
                events.rate();
                events.slider();
                events.timepicker();
                events.editor();
                events.regionCheck();
                events.addInput();
                events.selectplus();
                events.selectn();
                events.selectpage();
                events.removeInupt();
                events.bindevent()
            }
        }
    };
    return Fu
})