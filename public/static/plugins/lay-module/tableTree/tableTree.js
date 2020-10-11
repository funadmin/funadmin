layui.define(["laydate","laytpl","table","layer","tableEdit"],function(exports) {
    "use strict";
    var moduleName = 'tableTree'
        ,_layui = layui
        ,laytpl = _layui.laytpl
        ,$ = _layui.$
        ,table = _layui.table
        ,layer = _layui.layer
        ,tableEdit = _layui.tableEdit
        ,editEntity = tableEdit.callbackFn('tableEdit(getEntity)');
    //组件用到的css样式
    var thisCss = [];
    thisCss.push('.layui-tableEdit-checked i{background-color:#60b979!important;}');
    thisCss.push('.layui-tableEdit-edit{position:absolute;right:2px;font-size:20px;bottom:8px;z-index:199109084;}');
    thisCss.push('.layui-tableEdit-edge{position:absolute;left:8px;bottom:0;font-size:20px;z-index:19910908445;}');
    thisCss.push('.layui-tableEdit-span {position:absolute;left:35px;bottom:0;}');
    var thisStyle = document.createElement('style');
    thisStyle.innerHTML = thisCss.join('\n'),document.getElementsByTagName('head')[0].appendChild(thisStyle);

    var configs = {
        callbacks:{}
        ,tableTreeCache:{}
        ,isEmpty:function(data){
            return typeof data === 'undefined' || data === null || data.length <= 0;
        }
        ,parseConfig:function (cols,field) {
            var type,data,enabled,dateType,csField;
            cols.forEach(function (ite) {
                ite.forEach(function (item) {
                    if(field !== item.field || !item.config)return;
                    var config = item.config;
                    type = config.type;
                    data = config.data;
                    enabled = config.enabled;
                    dateType = config.dateType;
                    csField = config.cascadeSelectField;
                });
            });
            return {type:type,data:data,enabled:enabled,dateType:dateType,cascadeSelectField:csField};
        }
    };


    var TableTree = function () {this.config = {},this.aopObj = {}};
    TableTree.prototype = {
        render:function (options) {
            var that = this;$.extend(that.config,options);
            var treeConfig = that.config['treeConfig'];
            if(!that.initDoneStat){
                var _done = that.config.done;
                var done = function(result){
                    if(!result.data) result.data = table.cache[that.config.id] = [];
                    configs.tableTreeCache[$(this.elem).attr('id')] = result.data;
                    var params = {field:treeConfig.showField,element:this.elem};
                    that.initTree(params);
                    if(_done){
                        _done.call(this,result);
                    }
                };
                that.config.done = done;
                that.initDoneStat = true;
                that.url = that.config.url;
            }
            that.tableEntity = table.render(that.config);
            return that.tableEntity.config.cols;
        }
        ,initTree:function (options) {
            var data = configs.tableTreeCache[$(options.element).attr('id')];
            this.createChildren(data,options.field,null,0);
            this.closeAllRows();
            this.events();
        }
        ,createChildren:function(arr,field,tr,lvl){
            var that = this;
            var tableBody = $(that.config.elem).next().find('div.layui-table-body');
            _layui.each(arr,function (key,item) {
                if(lvl === 0){
                    tr = tableBody.find('tr[data-index="'+key+'"]');
                    $(tr).attr('tree-id',item[that.config.treeConfig.treeid]);
                    var td = $(tr).find('td[data-field="'+field+'"]');
                    var _div = td.find('div.layui-table-cell');
                    var span = _div.find('span');
                    if(!span[0]){
                        var text = _div.text();_div.text('');
                        _div.prepend('<span class="layui-tableEdit-span">'+text+'</span>');
                    }else {
                       !span.hasClass('layui-tableEdit-span') && span.addClass('layui-tableEdit-span');
                    }
                    if(!td.find('i.layui-tableEdit-edge')[0]){
                        that.addIcon(tr,td,lvl);
                    }
                    var checkboxTd = tr.find('td.layui-table-col-special');
                    checkboxTd.html('');
                    var newCheckbox = '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><i style="left: 15px;" class="layui-icon layui-icon-ok"></i></div>';
                    checkboxTd.append(newCheckbox);
                }else {
                    that.addTreeRow(tr,item);
                }
                if(item.treeList){
                    var nextTr = lvl === 0 ? tr : $(tr).next();
                    that.createChildren(item.treeList,field,nextTr,lvl+1)
                }
            });
        }
        ,addTreeRow:function (tr,data) {
            var that = this,lvl = tr.data('lvl');
            lvl = configs.isEmpty(lvl) ? 0 : parseInt(lvl);
            var treeid = that.config.treeConfig.treeid;
            if(configs.isEmpty(data[treeid])){
                data[treeid] = Number(Math.random().toString().substr(3, 3) + Date.now()).toString(36);
            }
            var newTr = $('<tr data-lvl="'+(lvl+1)+'" tree-id="'+data[treeid]+'"></tr>');
            tr.after(newTr);
            that.addElementToTr(data,tr,newTr,lvl+1);
        }
        ,findChildren:function (arr,uniqueTreeid) {
            var treeid = this.config.treeConfig.treeid;
            for(var i=0;i<arr.length;i++){
                var item = arr[i];
                if(uniqueTreeid+"" === item[treeid]+""){
                    return item;
                }else {
                    if (item.treeList && item.treeList.length>0) {
                       var data = this.findChildren(item.treeList, uniqueTreeid);
                       if(data) return  data;
                    }
                }
            }
        }
        ,addIcon:function(tr,td,lvl) {
            var iconClass = this.config.treeConfig.iconClass;
            iconClass = configs.isEmpty(iconClass) ? 'layui-icon-triangle-r' : iconClass;
            var that = this
                ,icon = $('<i class="layui-icon layui-tableEdit-edge '+iconClass+'"></i>')
                ,div = $('<div class="layui-tableTree-div"></div>')
                ,_div = $('<div class="layui-tableTree-edit"></div>')
                ,cell = td.find('div.layui-table-cell')
                ,span = cell.children('span');
            if(lvl>0){
                var spanLeft = lvl*24 + 35,iconLeft = lvl*24+8;
                span.css('left',spanLeft+'px');
                icon.css('left',iconLeft+'px');
            }
            div.append(icon),cell.append(div),$(td).append(_div);
            var add = $('<i class="layui-icon layui-icon-add-1 layui-tableEdit-edit"></i>');_div.append(add);
            var update = $('<i class="layui-icon layui-icon-edit layui-tableEdit-edit"></i>');_div.append(update)
            var remove = $('<i class="layui-icon layui-icon-delete layui-tableEdit-edit"></i>');_div.append(remove);
            _div.hide();
            var heightStyle = that.config.size;
            heightStyle = heightStyle ? heightStyle : '';
            if(heightStyle === 'sm'){  //兼容table的size类型
                span.css('bottom','0px');
                icon.css('bottom','0px');
                update.css('bottom','4px');
                add.css('bottom','4px');
                remove.css('bottom','4px');
            }
            if(heightStyle === 'lg'){
                span.css('bottom','-1px');
                icon.css('bottom','-1px');
                update.css('bottom','15px');
                add.css('bottom','15px');
                remove.css('bottom','15px');
            }
            add.css('right','1px'),update.css('right','24px'),add.css('right','47px');
        }
        ,events:function () {
            var that = this;
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            var tableTreeid = $(that.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];

            //编辑图标事件注册
            tableBody.on('click','i.layui-tableEdit-edit',function (e) {
                _layui.stope(e);
                var isAdd = $(this).hasClass('layui-icon-add-1')
                    ,isUpdate = $(this).hasClass('layui-icon-edit')
                    ,isDelete = $(this).hasClass('layui-icon-delete');
                var tr = $(this).parents('tr');
                var td = $(this).parents('td'),field = td.data('field');
                var treeid = tr.attr('tree-id');
                var data = that.findChildren(treeData,treeid);
                var lvl = tr.data('lvl');
                lvl = lvl ? lvl : 0;
                if(isAdd){//新增
                    //回调tool(lay-filter)对应的方法 异步/同步获取数据后回调add方法进行新增行
                    var thisObj = {value:null,data:data,field:field,add:function (newTree) {
                            var _treeid_ = that.config.treeConfig.treeid;
                            var treeList = data.treeList;
                            if(!treeList) treeList = data.treeList = [];
                            if(configs.isEmpty(newTree)) return;
                            var count= 0,num = 0,len = newTree.length;
                            while (count<len){
                                count++;
                                if(that.checkedRepeatData(newTree[num])){
                                    layer.msg(_treeid_+"的值["+newTree[num][_treeid_]+"]重复");
                                    newTree.splice(num,1);continue;
                                }
                                treeList.push(newTree[num]);num++;
                            }
                            that.asyncAddTree(newTree,data,tr,lvl);
                        },event:'add'};
                    that.aopObj.callback.call(td[0],thisObj);
                }

                //修改
                if(isUpdate){
                    var oldValue = that.parseTempletData(data,field);
                    oldValue = oldValue ? oldValue : '';
                    editEntity.input({element:td[0],oldValue:oldValue,callback:function (res) {
                        var thisObj = {value:res,data:data,field:field,update:function (fields) {
                            if(!fields)return;
                            for(var key in fields){
                                data[key] = fields[key];
                                var showValue = that.parseTempletData(data,key);
                                showValue = showValue ? showValue : '';
                                td.find('div.layui-table-cell span').text(showValue)
                            }
                        },event:'edit'};
                        that.aopObj.callback.call(td[0],thisObj);
                    }});
                }

                //删除
                if(isDelete){
                    layer.confirm('<div style="color: red;text-align: center;">确定删除吗？</div>', function(index){
                        var thisObj = {value:null,data:data,field:field,event:'del',del:function () {
                                var lvl = tr.data('lvl');lvl = lvl ? lvl : 0;
                                //删除页面隐藏的树
                                that.removeChildren(tr,lvl);
                                //清楚缓存中的数据
                                that.clearCacheData(data);
                                tr.remove();
                        }};
                        that.aopObj.callback.call(td[0],thisObj);
                        layer.close(index);
                    });
                }
            });

            //注册点击事件。
            tableBody.on("click",'td.layui-table-col-special',function (e) {
                _layui.stope(e)
                var div = $(this).children('div.layui-unselect');
                div.hasClass('layui-tableEdit-checked') ? div.removeClass('layui-tableEdit-checked') : div.addClass('layui-tableEdit-checked');
            });

            //全选复选框事件
            var tableHead = tableBody.prev()
                ,allElem = tableHead.find('th.layui-table-col-special');
            allElem[0].innerHTML = '';
            var newCheckbox = '<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><i style="left: 15px;" class="layui-icon layui-icon-ok"></i></div>';
            allElem.append(newCheckbox);
            tableHead.on('click','th.layui-table-col-special',function (e) {
                _layui.stope(e)
                var div = $(this).children('div.layui-unselect');
                if(div.hasClass('layui-tableEdit-checked')){
                    div.removeClass('layui-tableEdit-checked');
                    tableBody.find('td.layui-table-col-special div.layui-unselect').removeClass('layui-tableEdit-checked');
                }else {
                    div.addClass('layui-tableEdit-checked');
                    tableBody.find('td.layui-table-col-special div.layui-unselect').addClass('layui-tableEdit-checked');
                }
            });

            //showField字段单元格点击事件，展开子叶节点
            tableBody.on('click','td[data-field="'+that.config.treeConfig.showField+'"]',function (e) {
                _layui.stope(e);
                var thisTreeElem = $(this).parent();
                var lvl = thisTreeElem.data('lvl');lvl = lvl ? lvl : 0;
                var icon = $(this).find('i.layui-tableEdit-edge');
                var isShow = icon.hasClass('layui-tableEdit-clicked');
                //关闭或者展开子元素
                that.showOrHideChildren(thisTreeElem,lvl,!isShow);
                //选择三角图标
                that.rotateFunc(icon);
            });

            //操作栏鼠标经过事件
            tableBody.on("mouseover ",'td[data-field="'+that.config.treeConfig.showField+'"]',function (e) {
                _layui.stope(e)
                tableBody.find('div.layui-tableTree-edit').hide();
                var thisX = this.getBoundingClientRect().left
                    ,thisY = this.getBoundingClientRect().top
                    ,thisWidth = this.offsetWidth
                    ,thisHeight = this.offsetHeight,
                    thisTreeEdit = $(this).find('div.layui-tableTree-edit');
                e = e || window.event;
                if(e.pageX || e.pageY) {
                    var xy = {x: e.pageX, y: e.pageY};
                    if(xy.y < (thisY+thisHeight) && xy.y > thisY && xy.x > (thisX+thisWidth/2) && xy.x < (thisX+thisWidth)){
                        thisTreeEdit.show();
                    }
                }
            });
            tableBody.on("mouseout",'td[data-field="'+that.config.treeConfig.showField+'"]',function (e) {
                _layui.stope(e)
                tableBody.find('div.layui-tableTree-edit').hide();
            });
        }
        ,asyncAddTree:function (obj,data,tr,lvl) {
            var that = this;
            var _treeid_ = that.config.treeConfig.treeid;
            var treepid = that.config.treeConfig.treepid;
            !obj && (obj = []);
            obj.forEach(function (e) {
                that.addTreeRow(tr,e);
                e[treepid] = data[_treeid_];
                if(e.treeList && e.treeList.length>0){
                    that.asyncAddTree(e.treeList,e,tr.next(),lvl+1);
                }
            });
            that.showOrHideChildren(tr,lvl,true);
            var nextIcon = tr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
            that.rotateFunc(nextIcon,true);
        }
        ,parseTempletData:function (d,field) {
            var rs = null;
            this.config.cols.forEach(function (item1) {
                item1.forEach(function (item2) {
                    if(item2.field === field){
                        var templet = item2.templet;
                        if(templet){
                            if(typeof templet === 'string'){
                                rs = laytpl($(templet).html()).render(d);
                            }else {
                                rs = templet(d,field);
                            }
                        }else {
                            rs = d[field];
                        }
                    }
                });
            });
            return rs;
        }
        ,clearCacheData:function (data) {
            var treeid = this.config.treeConfig.treeid;
            var tableTreeid = $(this.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            this.clearChildCacheData(treeData,data[treeid]);
        }
        ,clearChildCacheData:function (list,uniqueTreeid) {
            var treeid = this.config.treeConfig.treeid;
            for(var i=0;i<list.length;i++){
                var item = list[i];
                if((uniqueTreeid+"") === (item[treeid]+"")) {
                    list.splice(i,1);break;
                }else{
                    if(item.treeList && item.treeList.length > 0){
                        this.clearChildCacheData(item.treeList,uniqueTreeid)
                    }
                }
            }
        }
        ,rotateFunc:function (icon,isOpen,isClose) {
            if(icon.hasClass('layui-tableEdit-clicked')){
                if(!isOpen){
                    icon.css('transform','');
                    icon.removeClass('layui-tableEdit-clicked');
                }
            }else {
                icon.css('transform','rotate(90deg)');
                icon.addClass('layui-tableEdit-clicked');
            }
            if(isClose){
                icon.css('transform','');
                icon.removeClass('layui-tableEdit-clicked');
            }
        }
        ,showOrHideChildren:function (elemTree,lvl,isShow) {
            var nextTreeElem = elemTree.next(),nextlvl = nextTreeElem.data('lvl');
            nextlvl = nextlvl ? nextlvl : 0;
            if(nextTreeElem[0] && nextlvl > lvl){
                if((nextlvl-lvl) <= 1 && isShow){
                    nextTreeElem.show();
                }else {
                    nextTreeElem.hide()
                }
                var nextIcon = nextTreeElem.find('td[data-field="'+this.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                //叶子节点不用打开小图标
                this.rotateFunc(nextIcon,null,true);
                this.showOrHideChildren(nextTreeElem,lvl,isShow);
            }
        }
        ,removeChildren:function (elemTree,lvl) {
            var nextTreeElem = elemTree.next(),nextlvl = nextTreeElem.data('lvl');
            nextlvl = nextlvl ? nextlvl : 0;
            if(nextTreeElem[0] && nextlvl > lvl){
                nextTreeElem.remove();
                this.removeChildren(elemTree,lvl);
            }
        }
        ,checkedRepeatData:function (data) {
            var treeid = this.config.treeConfig.treeid;
            var tableTreeid = $(this.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            var ckeckData = this.findChildren(treeData,data[treeid]);
            return ckeckData ? true : false;
        }
        ,getCheckedData:function () {
            var that = this;
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            var isAll = tableBody.prev().find('th.layui-table-col-special div.layui-unselect').hasClass('layui-tableEdit-checked');
            var tableTreeid = $(that.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            var checkedElem = tableBody.find('td.layui-table-col-special div.layui-tableEdit-checked');
            if(isAll) return treeData;
            var dataArr = [];
            checkedElem.each(function () {
                var tr = $(this).parents('tr').eq(0);
                var treeid = tr.attr('tree-id');
                var data = that.findChildren(treeData,treeid);
                dataArr.push(data);
            });
            return dataArr;
        }
        ,reload:function (options) {
            this.config.url = this.url;
            delete this.config.data;
            this.render(options);
        }
        ,openCheckedRows:function () {
            var that = this;
            var treepid =this.config.treeConfig.treepid;
            var tableTreeid = $(that.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            var isAll = tableBody.prev().find('th.layui-table-col-special div.layui-unselect').hasClass('layui-tableEdit-checked');
            if(isAll){
                that.openAllRows();
            }else {
                tableBody.find('td.layui-table-col-special div.layui-tableEdit-checked').each(function (e) {
                    var tr = $(this).parents('tr');
                    var icon = tr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                    var lvl = tr.data('lvl');
                    lvl = lvl ? lvl : 0;
                    if(lvl > 0){
                        var thisTreeid = tr.attr('tree-id');
                        var data = that.findChildren(treeData,thisTreeid);
                        var superElem = $("tr[tree-id='"+data[treepid]+"']");
                        var superIcon = superElem.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                        //如果上级节点时关闭的，那么选中行不会进行展开。
                        if(superElem && superIcon.hasClass("layui-tableEdit-clicked")){
                            that.openTreeNode(tr,lvl);
                            that.rotateFunc(icon,true);
                        }
                    }else {
                        that.openTreeNode(tr,lvl);
                        that.rotateFunc(icon,true);
                    }
                });
            }
        }
        ,openTreeNode:function (elemTree,lvl) {
            var nextTreeElem = elemTree.next(),nextlvl = nextTreeElem.data('lvl');
            nextlvl = nextlvl ? nextlvl : 0;
            if(nextTreeElem[0] && nextlvl > lvl){
                nextTreeElem.show();
                var nextIcon = nextTreeElem.find('td[data-field="'+this.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                //叶子节点不用打开小图标
                this.rotateFunc(nextIcon,true);
                this.openTreeNode(nextTreeElem,lvl);
            }
        }
        ,closeCheckedRows:function () {
            var that = this;
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            var isAll = tableBody.prev().find('th.layui-table-col-special div.layui-unselect').hasClass('layui-tableEdit-checked');
            if(isAll){
                that.closeAllRows();
            }else {
                tableBody.find('td.layui-table-col-special div.layui-tableEdit-checked').each(function (e) {
                    var tr = $(this).parents('tr');
                    var icon = tr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                    var lvl = tr.data('lvl');
                    lvl = lvl ? lvl : 0;
                    that.showOrHideChildren(tr,lvl,false);
                    that.rotateFunc(icon,null,true);
                });
            }
        }
        ,openAllRows:function () {
            var that = this;
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            tableBody.find('tr').each(function (e) {
                var tr = $(this);
                var icon = tr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                var lvl = tr.data('lvl');
                lvl = lvl ? lvl : 0;
                that.showOrHideChildren(tr,lvl,true);
                that.rotateFunc(icon,true);
            });
        }
        ,closeAllRows:function () {
            var that = this;
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            tableBody.find('tr').each(function (e) {
                var tr = $(this);
                var icon = tr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge');
                var lvl = tr.data('lvl');
                lvl = lvl ? lvl : 0;
                that.showOrHideChildren(tr,lvl,false);
                that.rotateFunc(icon,null,true);
            });
        }
        ,sort:function (options,treeData) { //排序，此代码抄袭至layui sort源码中来
            var that = this;
            treeData.sort(function(o1, o2){
                var isNum = /^-?\d+$/
                    ,v1 = o1[options.field]
                    ,v2 = o2[options.field];

                if(isNum.test(v1)) v1 = parseFloat(v1);
                if(isNum.test(v2)) v2 = parseFloat(v2);

                if(v1 && !v2){
                    return 1;
                } else if(!v1 && v2){
                    return -1;
                }

                if(v1 > v2){
                    return 1;
                } else if (v1 < v2) {
                    return -1;
                } else {
                    return 0;
                }
            });
            if(options.desc){
                treeData.reverse();
            }
            treeData.forEach(function (e) {
               if(e.treeList && e.treeList.length>0){
                   that.sort(options,e.treeList);
               }
            });
            that.deleteLayTableIndex(treeData);
        }
        ,getTableTreeData:function () {
            var tableTreeid = $(this.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            return treeData;
        }
        ,
        deleteLayTableIndex:function (data) {
            var that = this;
            data.forEach(function (e) {
                delete  e['LAY_TABLE_INDEX'];
                if(e.treeList){
                    that.deleteLayTableIndex(e.treeList);
                }
            })
        },
        on:function (event,callback) {
            var othis = this;othis.aopObj.event = event,othis.aopObj.callback = callback;
            table.on(othis.aopObj.event,function (obj) {
                var zthis = this,field = $(zthis).data('field'),config = configs.parseConfig(othis.config.cols,field);
                obj.field = field;
                var callbackFn = function (res) {
                    obj.value = Array.isArray(res) ? (res.length>0 ? res : [{name:'',value:''}]) : res;
                    othis.aopObj.callback.call(zthis,obj);
                };
                if(Object.keys(obj.data).length <= 0){
                    var tableTreeid = $(othis.config.elem).attr('id')
                        ,treeData = configs.tableTreeCache[tableTreeid]
                        ,_treeid = $(zthis.parentNode).attr("tree-id");
                    obj.data = othis.findChildren(treeData,_treeid);
                    obj.update = function (fields) {
                        if(!fields)return;
                        for(var key in fields){
                            obj.data[key] = fields[key];
                            var showValue = othis.parseTempletData(obj.data,key);
                            showValue = showValue ? showValue : '';
                            $(zthis).find('div.layui-table-cell').text(showValue)
                        }
                    };
                }
                config.type === 'select' &&
                editEntity.register({data:config.data,element:zthis,enabled:config.enabled,selectedData:obj.data[field],callback:callbackFn});
                config.type === 'date' && editEntity.date({dateType:config.dateType,element:zthis,callback:callbackFn});
                config.type === 'input'&& editEntity.input({element:zthis,oldValue:obj.data[field],callback:callbackFn});
                !config.type && othis.aopObj.callback.call(zthis,obj);

            });
        }
        ,addTopRow:function (data) {
            var that = this;
            var treeid = that.config.treeConfig.treeid;
            var treepid = that.config.treeConfig.treepid;
            var tableTreeid = $(that.config.elem).attr('id')
                ,treeData = configs.tableTreeCache[tableTreeid];
            if(treeData.length<=0){
                if(!data){
                    data = {};
                }
                that.config.cols.forEach(function (item1) {
                    item1.forEach(function (item) {
                        if(item.field){
                            if(!(item.field in data)){
                                data[item.field] = null;
                            }
                        }
                    });
                });
                delete data[treepid]; //最上级行不能有treepid
                delete that.config.url;
                if(!data[treeid]){
                    data[treeid] = Number(Math.random().toString().substr(3, 3) + Date.now()).toString(36)
                }
                treeData.push(data);
                that.config.data = treeData;
                that.render(that.config);
                return;
            }

            if(!data || (data && typeof  data !== 'object')){
                data = {};
                for(var key in  treeData[0]){
                    data[key] = null;
                }
            }
            delete data[treepid]; //最上级行不能有treepid
            var tr = $(that.config.elem).next().find('div.layui-table-body tr').eq(0);
            if(configs.isEmpty(data[treeid]) || that.checkedRepeatData(data)){
                data[treeid] = Number(Math.random().toString().substr(3, 3) + Date.now()).toString(36)
            }
            var newTr = $('<tr data-index="'+treeData.length+'" tree-id="'+data[treeid]+'"></tr>');
            tr.before(newTr);
            data['LAY_TABLE_INDEX'] = treeData.length;
            treeData.push(data);
            that.addElementToTr(data,tr,newTr,0);
            that.rotateFunc( newTr.find('td[data-field="'+that.config.treeConfig.showField+'"] i.layui-tableEdit-edge'),true);
        }
        ,addElementToTr:function (data,tr,newTr,lvl) {
            var that = this;
            tr.children('td').each(function () {
                var field = $(this).data('field'),td = null;
                if(field+"" === '0'){
                    var div = $('<div class="layui-unselect layui-form-checkbox" lay-skin="primary"><i style="left: 15px;" class="layui-icon layui-icon-ok"></i></div>');
                    td = $('<td data-field="0" data-key="1-0-0" class="layui-table-col-special"></td>');
                    td.append(div);
                }else {
                    var attrsStr = []
                        ,_divclass = $(this).children('div.layui-table-cell').attr("class")
                        ,attrs = this.attributes;
                    for(var i=0;i<attrs.length;i++){
                        attrsStr.push(attrs[i].nodeName+'="'+attrs[i].nodeValue+'"');
                    }
                    var text = that.parseTempletData(data,field); //按模板进行解析
                    text = text ? text : '';
                    if(field === that.config.treeConfig.showField ){
                        td = $('<td '+attrsStr.join(" ")+'><div class="'+_divclass+'"><span class="layui-tableEdit-span">'+text+'</span></div></td>');
                        that.addIcon(newTr,td,lvl);
                    }else {
                        newTr.append('<td '+attrsStr.join(" ")+'><div class="'+_divclass+'">'+text+'</div></td>');
                    }
                }
                newTr.append(td);
            });
        }
        ,delCheckedRows:function () {
            var that = this;
            var tableTreeid = $(that.config.elem).attr('id');
            var treeData = configs.tableTreeCache[tableTreeid];
            var tableBody = $(this.config.elem).next().find('div.layui-table-body');
            var isAll = tableBody.prev().find('th.layui-table-col-special div.layui-unselect').hasClass('layui-tableEdit-checked');
            var checkedElem = tableBody.find('td.layui-table-col-special div.layui-tableEdit-checked');
            if(isAll){
                treeData.splice(0,treeData.length);
                tableBody.find('tbody').html('');
                return;
            }
            checkedElem.each(function () {
                var tr = $(this).parents('tr').eq(0);
                var treeid = tr.attr('tree-id');
                var data = that.findChildren(treeData,treeid);
                var lvl = tr.data('lvl');lvl = lvl ? lvl : 0;
                //删除页面隐藏的树
                that.removeChildren(tr,lvl);
                //清楚缓存中的数据
                that.clearCacheData(data);
                tr.remove();
            });
        }
    };

    var active = {
        on:function (event,callback) {
            var filter = event.match(/\((.*)\)$/),eventName = (filter ? (event.replace(filter[0],'')+'_'+ filter[1]) : event);
            configs.callbacks[moduleName+'_'+eventName]=callback;
        },
        callbackFn:function (event,params) {
            var filter = event.match(/\((.*)\)$/),eventName = (filter ? (event.replace(filter[0],'')+'_'+ filter[1]) : event);
            var key = moduleName+'_'+eventName,func = configs.callbacks[key];
            if(!func) return;
            return func.call(this,params);
        },
        render:function (options) {
            var tableTree = new TableTree();
            tableTree.render(options);
            return {
                getTreeOptions:function () {
                    return tableTree.config;
                },
                getCheckedData:function () {
                    return tableTree.getCheckedData();
                },
                reload:function (options) {
                    tableTree.reload(options);
                },
                openCheckedRows:function () {
                    tableTree.openCheckedRows();
                }
                ,closeCheckedRows:function () {
                    tableTree.closeCheckedRows();
                }
                ,closeAllRows:function () {
                    tableTree.closeAllRows();
                }
                ,openAllRows:function () {
                    tableTree.openAllRows();
                }
                ,sort:function (options) {
                    var tableTreeid = $(tableTree.config.elem).attr('id');
                    var treeData = configs.tableTreeCache[tableTreeid];
                    tableTree.sort(options,treeData);
                    delete tableTree.config.url;
                    tableTree.config.data = treeData;
                    tableTree.render(tableTree.config);
                }
                ,getTableTreeData:function () {
                    return tableTree.getTableTreeData();
                }
                ,on:function (event,callback) {
                    tableTree.on(event,callback);
                }
                ,addTopRow:function (data) {
                    tableTree.addTopRow(data);
                }
                ,delCheckedRows:function () {
                    tableTree.delCheckedRows();
                }
            };
        }
    };
    exports(moduleName, active);
});