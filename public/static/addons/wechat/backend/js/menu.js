define(['jquery', 'table', 'form'], function ($, Table, Form) {
    let Controller = {
        index: function () {
            let config = {
                add:'addons/wechat/backend/menu/add',
                edit:'addons/wechat/backend/menu/edit',
                aysn:'addons/wechat/backend/menu/aysn',
                delete:'addons/wechat/backend/menu/delete',
                getAccoutMenu:'addons/wechat/backend/menu/getAccoutMenu',
                app_id:'',
                app_secret:'',
            };
            let obj , button;
            // 初始化公众号
            $(function () {
                Fun.ajax({
                    url:config.getAccoutMenu
                },function(res){
                    data = res.data;
                    if (data.account.length>0) {
                        $(".menu-view-title").text(data.account.wxname);
                        config.app_id = data.account.app_id;
                        config.app_secret = data.account.secret;
                    }
                    button = data.button;
                    if (data.button.length > 0) {
                        obj = {
                            "menu": data.button,
                        };
                        $('.menu-view-menu').remove();
                        menuCreate(obj);
                    } else {
                        obj = {
                            "menu": data.button,
                        };
                        $('.menu-view-menu').remove();
                        menuCreate(obj);
                        //清除右边数据
                        // $('.cm-edit-before').show().siblings().hide();
                    }
                })
            })
            //一级菜单对象
            function parents(param) {
                this.name = param;
                this.sub_button = [];
            }

            //二级菜单对象
            function subs(param) {
                this.name = param;
            }

            //显示第一级菜单
            function showMenu() {
                if (button.length == 1) {
                    appendMenu(button.length);
                    showBtn();
                    $('.menu-view-menu').css({
                        width: '50%',
                    });
                }
                if (button.length == 2) {
                    appendMenu(button.length);
                    showBtn();
                    $('.menu-view-menu').css({
                        width: '33.3333%',
                    });
                }
                if (button.length == 3) {
                    appendMenu(button.length);
                    showBtn();
                    $('.menu-view-menu').css({
                        width: '33.3333%',
                    });
                }
                for (var b = 0; b < button.length; b++) {
                    $('.menu-view-menu')[b].setAttribute('alt', b);
                }
            }

            //显示子菜单
            function showBtn() {
                for (var i = 0; i < button.length; i++) {
                    var text = button[i].name;
                    var list = document.createElement('ul');
                    list.class = "wx-menu-view-menu-sub";
                    $('.menu-view-menu')[i].childNodes[0].innerHTML = text;
                    $('.menu-view-menu')[i].appendChild(list);
                    for (var j = 0; j < button[i].sub_button.length; j++) {
                        var text = button[i].sub_button[j].name;
                        var li = document.createElement("li");
                        var tt = document.createTextNode(text);
                        var div = document.createElement('div');
                        li.class = 'wx-menu-view-menu-sub-add';
                        li.id = 'sub_' + i + '_' + j;//设置二级菜单id
                        div.class = "text-ellipsis";
                        div.appendChild(tt);
                        li.appendChild(div);
                        $('.menu-view-menu-sub')[i].appendChild(li);
                    }
                    var ulBtnL = button[i].sub_button.length;
                    var iLi = document.createElement("li");
                    var ii = document.createElement('i');
                    var iDiv = document.createElement("div");
                    ii.class = "layui-icon layui-icon-add-1";
                    iDiv.class = "text-ellipsis";
                    iLi.class = 'wx-menu-view-menu-sub-add';
                    iDiv.appendChild(ii);
                    iLi.appendChild(iDiv);
                    if (ulBtnL < 5) {
                        $('.menu-view-menu-sub')[i].appendChild(iLi);
                    }

                }
            }

            //显示添加的菜单
            function appendMenu(num) {
                var menuDiv = document.createElement('div');
                var mDiv = document.createElement('div');
                var mi = document.createElement('i');
                mi.class = 'layui-icon layui-icon-add-1 iBtn';
                mDiv.class = 'text-ellipsis';
                menuDiv.class = 'wx-menu-view-menu';
                mDiv.appendChild(mi);
                menuDiv.appendChild(mDiv);
                switch (num) {
                    case 1:
                        menuBtns.append(menu);
                        menuBtns.append(menuDiv);
                        break;
                    case 2:
                        menuBtns.append(menu);
                        menuBtns.append(menu);
                        menuBtns.append(menuDiv);
                        break;
                    case 3:
                        menuBtns.append(menu);
                        menuBtns.append(menu);
                        menuBtns.append(menu);
                        break;
                }
            }

            //初始化菜单按钮
            function addMenu() {
                var menuI = '<div class="wx-menu-view-menu"><div class="text-ellipsis"><i class="layui-icon layui-icon-add-1 iBtn"></i></div></div>';
                var sortIndex = true;
                menuBtns.append(menuI);
                var customFirstBtns = $('.menu-view-menu');
                var firstBtnsLength = customFirstBtns.length;
                if (firstBtnsLength <= 1) {
                    customFirstBtns.css({
                        width: '100%',
                    })
                }
            }

            function setSubText() {
                var actived = $('.menu-view-menu-sub-add').hasClass('subbutton-actived');
                var activedTxt = $('.subbutton-actived').text();
                if (actived) {
                    setInput(activedTxt);
                    updateTit(activedTxt);

                    radios[0].checked = true;
                    $('#editMsg').show();
                    $('#editPage').hide();
                    $('.msg-context-item').show();
                    $('.msg-template').hide();
                }
            }

            //对新添加二级菜单添加id
            function setLiId() {
                var prev = $('.menu-view-menu')[colIndex].getElementsByTagName('i')[0].parentNode.parentNode.previousSibling;
                var divText = prev.childNodes[0].innerHTML;
                if (typeof (button[colIndex].sub_button) == "undefined") {
                    var sub_button = {"sub_button": []};
                    button[colIndex].append(sub_button);
                }
                button[colIndex].sub_button.push(new subs(divText));
                var id = button[colIndex].sub_button.length - 1;
                prev.setAttribute('id', 'sub_' + colIndex + '_' + id);

                $('.menu-view-footer-right').find('.subbutton-actived').removeClass('subbutton-actived');
                $('.menu-view-menu').eq(colIndex).find('i').parent().parent().prev().addClass('subbutton-actived');
            }

            //设置右边input的value
            function setInput(val) {
                $('input[name="custom_input_title"]').val(val);
            }

            //实时更新右侧顶部标题
            function updateTit(text) {
                $('#cm-tit').html(text);
            }

            function sortable(m, sortIndex) {
                if (sortIndex) {
                    Sortable.create(document.getElementById('menuStage_2_' + m), {
                        animation: 300, //动画参数
                        disabled: false,
                    });
                } else {
                    var el = document.getElementById('menuStage_2_' + m);
                    var sortable = Sortable.create(el, {
                        disabled: true,
                    });
                    sortable.destroy();

                }
            }

            //type为click时添加删除按钮元素
            function delElement() {
                var msgTemp = $('.msg-template');
                var delEl = '<span class="msg-panel-del del-tuwen">删除</span>';
                msgTemp.append(delEl);
                if (msgTemp.find('span').length == 0) {
                    msgTemp.append(delEl);
                }
            };

            //保存
            function saveAjax(url,data) {
                Fun.ajax({
                    url:api_url,
                    data:data,
                })
                // $.ajax({
                //     url: ApiUrl + '/admin/wechat.wechat/addWeixinMenu',
                //     type: "POST",
                //     data: JSON.stringify({
                //         "menu": obj.menu,//先将对象转换为字符串再传给后台
                //         "app_id": $("#appIdcode").val()
                //     }),
                //     contentType: "application/json",
                //     dataType: "json",
                //     success: function (res) {
                //         if (res.code > 0) {
                //             layer.msg("创建成功！");
                //         } else {
                //             layer.alert(res.msg);
                //         }
                //     }
                // });
            }
            //删除菜单
            function deleteMenu() {
                Fun.toastr.confirm(-('Are you sure you want to delete it'), function () {
                    Fun.ajax({
                        url: api_url.delete,
                        data:{"appid": $("#appIdcode").val()}
                    }, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            obj = {
                                "menu": {button: []}
                            };
                            $('.menu-view-menu').remove();
                            menuCreate(obj);
                            $(".msg-tab_item").removeClass('on');
                            $("#imgtext").addClass("on");
                        });
                    })
                });
                Fun.ajax({
                    url:api_url,
                    data:data,
                })
                // layer.confirm('谨慎操作', {btn: ['确定', '取消'], title: "提示"}, function () {
                //     $.ajax({
                //         url: ApiUrl + '/admin/wechat.wechat/menuDel',
                //         type: "POST",
                //         data: {"appid": $("#appIdcode").val()},
                //         contentType: "application/json",
                //         dataType: "json",
                //         success: function (res) {
                //             if (res.code > 0) {
                //                 layer.msg("删除成功！");
                //                 obj = {
                //                     "menu": {button: []}
                //                 };
                //                 $('.menu-view-menu').remove();
                //                 menuCreate(obj);
                //                 $(".msg-tab_item").removeClass('on');
                //                 $("#imgtext").addClass("on");
                //             } else {
                //                 layer.alert(data.msg);
                //             }
                //         }
                //     })
                //
                // });

            }

            // 创建菜单
            function menuCreate(obj) {
                $('.iBtn').parent().unbind("click");
                $('.text-ellipsis').unbind("click");
                $('li>.text-ellipsis>i').unbind("click");
                $('.msg-panel-tab>li').unbind("click");
                $('#selectModal .modal-body .panel').unbind("click");
                $('#selectModal .ensure').unbind("click");
                $('#delMenu').unbind("click");
                $('#saveBtns').unbind("click");
                menuBtns.unbind("click");
                mId = null;
                tempObj = {};//存储HTML对象
                if (typeof (obj.menu) != "undefined") {
                    button = obj.menu.button;//一级菜单
                } else {
                    button = [];
                }
                objp = new parents();
                objs = new subs();
                if (typeof (button) != "undefined") {
                    ix = button.length;//一级菜单数量
                } else {
                    ix = 0;
                }
                if (typeof (button) != "undefined" && button.length > 0) {
                    showMenu();
                    //$('.cm-edit-before').hide();
                } else {
                    addMenu();
                    $('.cm-edit-before').siblings().hide();
                }
                $('.menu-view-footer-right').off('click').on('click', ".iBtn", function () {
                    var dom = $(this).parent(".text-ellipsis");
                    if ($(dom).siblings("ul").length == 0) {
                        ix = $('.menu-view-menu[alt]').length;
                        var num = $('.menu-view-footer-right').find('.menu-view-menu').length;
                        var ulNum = $(dom).parents('.menu-view-menu').prev().find('.menu-view-menu-sub').length;
                        ix++;
                        if (ix < 4) {
                            $(dom).parent().before(menuEl);
                            $(dom).parent().prev().append(menuUl);

                            $('.menu-view-footer-right').find('.subbutton-actived').removeClass('subbutton-actived');
                            $(dom).parent().prev().addClass('subbutton-actived');

                            //一级菜单列数
                            var buttonIndex = $(dom).parents('.menu-view-menu').index() - 1;
                            $('.menu-view-menu').eq(buttonIndex).on('click', (function (buttonIndex) {
                                var txt = $('.menu-view-menu').eq(buttonIndex).text();
                                setMenuText(txt);
                            })(buttonIndex));

                            if (ix == 1) {
                                $('.menu-view-menu').css({
                                    width: '50%'
                                });
                                $('.menu-view-menu')[ix - 1].setAttribute('alt', ix - 1);
                            }
                            if (ix == 2) {
                                $('.menu-view-menu').css({
                                    width: '33.3333%'
                                });
                                $('.menu-view-menu')[ix - 1].setAttribute('alt', ix - 1);
                            }
                            var divText = $(dom).parent().prev().find('.text-ellipsis').text();
                            button.push(new parents(divText));
                        }
                        if (ix == 3) {
                            $(dom).parents('.menu-view-menu').remove();
                            $(dom).parent().append(menuUl);
                            var index = ix - 1
                            if ($(".menu-view-menu").eq(ix - 1).children(".text-ellipsis").children(".iBtn").length == 0) {
                                $('.menu-view-menu')[ix - 1].setAttribute('alt', ix - 1);
                            }
                        }
                        $('.cm-edit-after').show().siblings().hide();
                    }
                });
                setMenuText = function (value) {
                    setInput(value);
                    updateTit(value);

                    radios[0].checked = true;
                    $('#editMsg').show();
                    $('#editPage').hide();
                    $('.msg-context-item').show();
                    $('.msg-template').hide();
                }
                //添加子菜单
                menuBtns.on('click', 'li>.text-ellipsis>i', function () {
                    //绑定删除事件
                    $('.msg-panel-del').on('click', delClick);
                    colIndex = $(this).parents('.menu-view-menu').prevAll().length;
                    var liNum = $(this).parents('.menu-view-menu').find('li').length;

                    // if (liNum <= 1) {
                    //     // $('#reminderModal').modal('show');
                    // } else {
                    if (liNum < 6) {
                        $(this).parent().parent().before(menuLi);
                        setLiId();
                    }
                    if (liNum == 5) {
                        $(this).parents('li').remove();
                    }
                    // }
                    $('#radioGroup').show();
                    setSubText()
                });
                //确定添加子菜单事件
                $('.reminder').click(function () {
                    var ul = $('.menu-view-menu')[colIndex].getElementsByTagName('ul')[0];
                    var li = document.createElement('li');
                    var div = document.createElement('div');
                    var Text = document.createTextNode('新建子菜单');
                    li.class = "wx-menu-view-menu-sub-add";
                    div.class = "text-ellipsis";
                    div.appendChild(Text);
                    li.appendChild(div);
                    ul.insertBefore(li, ul.childNodes[0]);
                    setLiId();
                    delete button[colIndex].type;
                    delete button[colIndex].media_id;
                    delete button[colIndex].url;
                    $('#reminderModal').modal('hide');

                    setSubText();
                });
                imageText();
                //点击菜单
                menuBtns.on('click', '.text-ellipsis', function () {
                    $('.cm-edit-after').show().siblings().hide();
                    if ($(this).parent().attr('id') || $(this).parent().attr('alt')) {
                        $(this).parents('.menu-view-footer-right').find('.subbutton-actived').removeClass('subbutton-actived');
                        $(this).parent().addClass('subbutton-actived');
                    }
                    //一级菜单列数
                    var buttonIndex = $(this).parents('.menu-view-menu').prevAll().length;
                    if ($('.msg-context-item').is(':hidden')) {
                        $('.msg-template').show();
                    } else if ($('.msg-context-item').is(':visible')) {
                        $('.msg-template').hide();
                    }
                    //点击在一级菜单上
                    if ($(this).parent().attr('alt')) {
                        if ($('.menu-view-menu').hasClass('subbutton-actived')) {
                            var current = $('.subbutton-actived');
                            var alt = current.attr('alt');
                            var lis = current.find('ul>li');
                            setInput(button[buttonIndex].name);
                            updateTit(button[buttonIndex].name);
                            if (lis.length > 1) {
                                $('#editMsg').hide();
                                $('#editPage').hide();
                                $('#radioGroup').hide();
                            } else {
                                if (button[buttonIndex].type == 'media_id') {
                                    radios[0].checked = true;
                                    switch (button[buttonIndex].ctype) {
                                        case 'image':
                                            $("#img").trigger('click');
                                            break;
                                        case 'voice':
                                            $("#voice").trigger('click');
                                            break;
                                        case 'video':
                                            $("#video").trigger('click');
                                            break;
                                        default:
                                            $("#imgtext").trigger("click");
                                    }
                                    $('#editMsg').show();
                                    $('#editPage').hide();
                                    $('#radioGroup').show();

                                    //拿key换取mediaId
                                    subKey = button[buttonIndex].media_id;
                                    $('.msg-template').html($('#' + subKey).html());
                                    delElement();
                                    //绑定删除事件
                                    $('.msg-panel-del').on('click', delClick);

                                    $('.msg-template').html(tempObj[button[buttonIndex].media_id]);
                                } else if (button[buttonIndex].type == 'view') {
                                    $('input[name="url"]').val(button[buttonIndex].url);
                                    radios[1].checked = true;
                                    $('#editMsg').hide();
                                    $('#editPage').show();
                                    $('#radioGroup').show();
                                } else if (!button[buttonIndex].type) {
                                    radios[0].checked = true;
                                    $('#editMsg').show();
                                    $('#editPage').hide();
                                    $('#radioGroup').show();
                                }
                                if (button[buttonIndex].media_id) {
                                    $('.msg-context-item').hide();
                                    $('.msg-template').show();
                                } else {
                                    $('.msg-context-item').show();
                                    $('.msg-template').hide();
                                }
                            }

                        }

                    }
                    //点击在二级菜单上
                    if ($(this).parent().attr('id')) {
                        var subIndex = $(this).parent("li").prevAll().length;
                        var subText = button[buttonIndex].sub_button[subIndex].name;
                        var subUrl = button[buttonIndex].sub_button[subIndex].url;
                        var subType = button[buttonIndex].sub_button[subIndex].type;
                        var subKey = button[buttonIndex].sub_button[subIndex].media_id;

                        if ($('.menu-view-menu-sub-add').hasClass('subbutton-actived')) {
                            setInput(subText);
                            updateTit(subText);
                            $('#radioGroup').show();
                            if (subType == 'media_id') {
                                radios[0].checked = true;
                                switch (button[buttonIndex].sub_button[subIndex].ctype) {
                                    case 'image':
                                        $("#img").trigger('click');
                                        break;
                                    case 'voice':
                                        $("#voice").trigger('click');
                                        break;
                                    case 'video':
                                        $("#video").trigger('click');
                                        break;
                                    default:
                                        $("#imgtext").trigger("click");
                                }
                                $('#editMsg').show();
                                $('#editPage').hide();

                                //拿key换取图文消息
                                $('.msg-template').html($('#' + subKey).html());
                                delElement();
                                //绑定删除事件
                                $('.msg-panel-del').on('click', delClick);
                                $('.msg-template').html(tempObj[subKey]);
                            } else if (subType == 'view') {
                                radios[1].checked = true;
                                $('#editMsg').hide();
                                $('#editPage').show();
                                $('input[name="url"]').val(subUrl);
                            } else if (!subType) {
                                radios[0].checked = true;
                                $('#editMsg').show();
                                $('#editPage').hide();
                                $('input[name="url"]').val('');
                            }
                            if (subKey) {
                                $('.msg-context-item').hide();
                                $('.msg-template').show();
                            } else {
                                $('.msg-context-item').show();
                                $('.msg-template').hide();
                            }
                        }
                    }
                    //绑定删除事件
                    $('.msg-panel-del').on('click', delClick);
                });

                //保存右侧菜单名称
                $('input[name="custom_input_title"]').keyup(function () {
                    var val = $(this).val();
                    var current = $('.subbutton-actived');
                    if ($('.menu-view-menu-sub-add').hasClass('subbutton-actived')) {
                        var sub_row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                        var sub_col = $('.subbutton-actived').prevAll().length;
                        button[sub_row].sub_button[sub_col].name = val;
                        current.find('.text-ellipsis').text(val);
                        updateTit(val);
                    } else if ($('.menu-view-menu').hasClass('subbutton-actived')) {
                        var alt = current.attr('alt');
                        button[alt].name = val;
                        current.children('.text-ellipsis').text(val);
                        updateTit(val)
                    }

                });
                //保存右侧跳转页面的url
                $('input[name="url"]').keyup(function () {
                    var val = $(this).val();
                    var current = $('.subbutton-actived');
                    if ($('.menu-view-menu-sub-add').hasClass('subbutton-actived')) {
                        var sub_row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                        var sub_col = $('.subbutton-actived').prevAll().length;
                        button[sub_row].sub_button[sub_col].url = val;
                        button[sub_row].sub_button[sub_col].type = 'view';
                        if (button[sub_row].sub_button[sub_col].url == '') {
                            delete button[sub_row].sub_button[sub_col].url;
                        }
                    } else if ($('.menu-view-menu').hasClass('subbutton-actived')) {
                        var alt = current.attr('alt');
                        button[alt].url = val;
                        button[alt].type = 'view';
                        if (button[alt].url == '') {
                            delete button[alt].url;
                        }
                    }

                });


                //tab切换
                $('.msg-panel-tab>li').click(function () {
                    $('.msg-panel-tab>li').eq($(this).index()).addClass('on').siblings().removeClass('on');
                    $('.msg-panel-context').eq($(this).index()).removeClass('hide').siblings().addClass('hide')
                });

                //菜单内容跳转
                radios = document.getElementsByName("radioBtn");
                for (var n = 0; n < radios.length; n++) {
                    radios[n].index = n;
                    radios[n].onchange = function () {
                        if (radios[this.index].checked == true) {
                            if (radios[this.index].value == 'link') {
                                $('#editMsg').hide();
                                $('#editPage').show();
                            } else {
                                $('#editMsg').show();
                                $('#editPage').hide();
                            }
                        }
                    };
                }


                delClick = function () {
                    $('.msg-template').empty();
                    $('.msg-context-item').show();
                    $('.mask-bg').hide();

                    var current = $('.subbutton-actived');
                    if ($('.menu-view-menu-sub-add').hasClass('subbutton-actived')) {
                        var sub_col = $(".subbutton-actived").prevAll().length;
                        var sub_row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                        delete button[sub_row].sub_button[sub_col].media_id;
                        delete button[sub_row].sub_button[sub_col].type;
                    } else if ($('.menu-view-menu').hasClass('subbutton-actived')) {
                        var alt = $(".subbutton-actived").prevAll().length;
                        delete button[alt].media_id;
                        delete button[alt].type;
                    }
                };
                //删除菜单按钮
                $('#delMenu').click(function () {
                    var is_Actived = $('.menu-view-menu').hasClass('subbutton-actived');//一级菜单选择项
                    var is_actived = $('.menu-view-menu-sub-add').hasClass('subbutton-actived');//二级菜单选中项
                    var rowIndex = 0;
                    var colIndex = 0;
                    var liLength = $(".subbutton-actived").parents('.menu-view-menu-sub');
                    if (is_Actived) {
                        rowIndex = $(".subbutton-actived").prevAll().length;
                        $('.subbutton-actived').remove();
                        button.splice(rowIndex, 1);
                        let buttonLength = $(".menu-view-menu:last[alt]").length;
                        let isTrue = $(".menu-view-menu .text-ellipsis .iBtn");
                        if (buttonLength == 1) {
                            $(".menu-view-menu").css("width", '33.3333%');
                        } else if (buttonLength == 0) {
                            $(".menu-view-menu").css("width", '50%');
                        }
                        if ($(".menu-view-footer-right").children().length == 1) {
                            $(".menu-view-menu").css("width", '100%');
                        }
                        let divHtml = '<div class="wx-menu-view-menu"><div class="text-ellipsis"><i class="layui-icon layui-icon-add-1 iBtn"></i></div></div>';
                        if (!isTrue.length && isTrue.length == 0) {
                            $(".menu-view-footer-right").last().append(divHtml);
                        }
                    } else if (is_actived) {
                        rowIndex = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                        colIndex = $('.subbutton-actived').prevAll().length;
                        $('.subbutton-actived').remove();
                        button[rowIndex].sub_button.splice(colIndex, 1);
                        /*    if (colIndex == 0) {
                        delete button[rowIndex].sub_button;
                        }*/
                        var l = $(liLength).find('li').length;
                        var add_button = $(liLength).find("i");
                        if (l < 5) {
                            if (!add_button.length) {
                                let liHtml = '<li class="wx-menu-view-menu-sub-add"><div class="text-ellipsis"><i class="layui-icon layui-icon-add-1"></i></div></li>';
                                $(liLength).find("li:last").after(liHtml);
                            }
                        }
                    }
                    //清除右边数据
                    $('.cm-edit-before').show().siblings().hide();
                    updateTit('');
                    setInput('');
                    $('input[name="url"]').val('');
                    $('.msg-template').children().remove();
                    $('.msg-context-item').show();
                })
                //保存自定义菜单
                $('#saveBtns').click(function () {
                    let url = null;
                    let strRegex = '(https?|ftp|file)://[-A-Za-z0-9+&@#/%?=~_|!:,.;]+[-A-Za-z0-9+&@#/%=~_|]';
                    let re = new RegExp(strRegex);
                    let flag;
                    for (let i = 0; i < button.length; i++) {
                        if (button[i].sub_button.length) {
                            //判断是否有子元素
                            for (let j = 0; j < button[i].sub_button.length; j++) {
                                //二级菜单
                                if (button[i].sub_button[j].hasOwnProperty('url')) {
                                    url = button[i].sub_button[j].url;
                                    if (!re.test(url)) {
                                        layer.msg("请输入正确的url地址！");
                                        flag = false;
                                    }
                                } else if (button[i].sub_button[j].hasOwnProperty('media_id')) {
                                    flag = true;
                                } else {
                                    flag = false;
                                    layer.msg("菜单内容不能为空！");
                                }
                            }

                        } else {
                            //一级菜单 url
                            if (button[i].hasOwnProperty('url')) {
                                flag = true;
                                url = button[i].url;
                                if (!re.test(url)) {
                                    layer.msg("请输入正确的url地址！");
                                    flag = false;
                                }

                            } else if (button[i].hasOwnProperty('media_id')) {
                                // media_id;
                                flag = true;

                            } else {
                                flag = false;
                                layer.msg("菜单内容不能为空！");
                            }
                        }
                    }
                    if (flag) {
                        saveAjax();
                    }
                });
            }

            // 图文消息
            function imageText() {

                let appid = $("#appIdcode").val();
                $.ajax({
                    url: ApiUrl + '/admin/wechat.wechat/getMaterialByType',
                    type: 'POST',
                    data: {'appid': appid, type: 'news'},
                    dataType: "json",
                    cache: false,
                    async: false,
                    success: function (responseStr) {
                        $("#imgTextAdd").empty();
                        let html = '';
                        for (let i = 0; i < responseStr.data.length; i++) {
                            let divHtml1 = '';
                            let divHtml2 = '';

                            if (i % 3 == 0) {
                                html += '<div style="display: flex;">';
                            }
                            let imgUrl = responseStr.data[i].groupList[0].cover;
                            html += '<div id=' + responseStr.data[i].media_id + ' class="col-xs-4">' +
                                '<div class="panel panel-default">' +
                                '<div class=" newmodul">' +
                                '<div class="panel-heading msg-date">' +
                                '<div class=" weui-panel weui-panel_access">' +
                                '<div class="weui-panel-hd" style="height: 200px;background-image:url(' + imgUrl + ');background-size: 100% 100%;">' +
                                '<span class="newtitle">' + responseStr.data[i].groupList[0].title + '</span>' +
                                '</div>';
                            let circle = '';
                            if (responseStr.data[i].groupList.length > 1) {
                                html += '<div class="weui-panel-bd">';
                                for (let j = 1; j < responseStr.data[i].groupList.length; j++) {
                                    circle = circle +
                                        '<a href="javascript:void(0);" class="weui-media-box weui-media-box_appmsg">' +
                                        '<div class="weui-media-box-bd">' +
                                        '<h4 class="weui-media-box-title">' + responseStr.data[i].groupList[j].title + '</h4>' +
                                        '</div>' +
                                        '<div class="weui-media-box-hd">' +
                                        '<img class="weui-media-box-thumb" src="' + responseStr.data[i].groupList[j].cover + '" alt="">' +
                                        '</div>' +
                                        '</a>'
                                }
                                html += circle + '</div>';
                            }
                            html += '</div></div></div><div class="mask-bg"><div class="mask-icon"><i class="icon-ok"></i></div></div></div></div>';
                            if (i % 3 == 2) {
                                html += '</div>'
                            }
                        }
                        $("#imgTextAdd").append(html);
                        modalAddClick();
                    },
                    error: function (responseStr) {
                        layer.alert("出错啦");
                    }
                });
                let delHtml = '<span class="msg-panel-del del-tuwen">删除</span>';
                if ($(".subbutton-actived").attr('alt')) {
                    let row = $(".subbutton-actived").prevAll().length;
                    if (button[row].ctype != null) {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else if (typeof (button[row].ctype) == 'undefined') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                    } else {
                        var subKey = button[row].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                } else if ($(".subbutton-actived").attr('id')) {
                    let row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                    let clo = $(".subbutton-actived").prevAll().length;
                    if (typeof (button[row].sub_button[clo].ctype) == 'undefined') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                    } else if (button[row].sub_button[clo].ctype != null) {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].sub_button[clo].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                }
            }
            //图片
            function picture() {
                var appid = $("#appIdcode").val();
                $.ajax({
                    url: ApiUrl + '/admin/wechat.wechat/getMaterialByType',
                    type: 'POST',
                    data: {appid: appid, type: 'image'},
                    dataType: "json",
                    cache: false,
                    async: false,
                    success: function (responseStr) {
                        responseStr = responseStr.data;

                        var imgHtml = '';
                        for (let i = 0; i < responseStr.length; i++) {
                            if (i % 3 == 0) {
                                imgHtml += '<div style="display: flex;">'
                            }
                            imgHtml += '<div id=' + responseStr[i].media_id + ' class="col-xs-4">'
                                + '<div class="panel panel-default">'
                                + '<div class="panel-body">'
                                + '<div class="msg-img"><img src=' + responseStr[i].media_url + ' alt=""></div>'
                                + '</div>'
                                + '<div class="mask-bg"><div class="mask-icon"><i class="icon-ok"></i></div></div>'
                                + '</div>'
                                + '</div>';
                            if (i % 3 == 2) {
                                imgHtml += '</div>'
                            }
                        }
                        ;
                        $("#imgTextAdd").append(imgHtml);
                        modalAddClick();
                    },
                    error: function (responseStr) {
                        layer.alert("出错啦");
                    }
                });
                let delHtml = '<span class="msg-panel-del del-tuwen">删除</span>';
                if ($(".subbutton-actived").attr('alt')) {
                    let row = $(".subbutton-actived").prevAll().length;
                    if (button[row].ctype != 'image') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                } else if ($(".subbutton-actived").attr('id')) {
                    let row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                    let clo = $(".subbutton-actived").prevAll().length;
                    if (typeof (button[row].sub_button[clo].ctype) == 'undefined') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                    } else if (button[row].sub_button[clo].ctype != 'image') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].sub_button[clo].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                }
            }

            // 语音
            function voice() {
                var appid = $("#appIdcode").val();
                $.ajax({
                    url: ApiUrl + '/admin/wechat.wechat/getMaterialByType',
                    type: 'POST',
                    data: {appid: appid, 'type': 'voice'},
                    dataType: "json",
                    cache: false,
                    async: false,
                    success: function (responseStr) {
                        var responseStr = responseStr.data;
                        $("#imgTextAdd").empty();
                        for (var i = 0; i < responseStr.length; i++) {
                            let voice = '<div id=' + responseStr[i].media_id + ' class="col-xs-4">' +
                            '   <div class="panel panel-default">' +
                            '   <div class="panel-body"><div class="wx-audio-content jg" >' +
                            '    <audio muted class="wx-audio-content"  src=\"' + responseStr[i].media_url + '\" loop="true"></audio>' +
                            '    <div class="wx-audio-left">\n' +
                            '     <img class="wx-audio-state"  src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFQAAABUCAMAAAArteDzAAAAaVBMVEUAAAAarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRlIa6J1AAAAInRSTlMA9wYa38QR7ZJnMK1IIqBsO3fXDbSGQudZz5fKpV0rfbpRlHIjYQAAA35JREFUWMPFWduyqjAMDS0tgtwEFBGv/P9Hntmh3cWDTYsMs/Oio3SRy0qapuCU7PXIRdUGQxCFncgfrwzWCb/l4TCTML/xbxFlIQariEJ+AZnkwUBKkCdLIZvBQ5olsPw61Uhc4vTOa4Ca39P4IqYWXH2dyw5mWXUs2ez/8liZVx6YD2bW6wXRzmpesov0U70HxW5azTBmpD1xqJW9uUzfaS0Lp1ms0Nru6Nfv9WPSi8lahT2BKoWyvARPKZUPhLRiduq9ckHaKds6y5pa6XmARXJQutaEP4MzLJTzyJfmk193I2YKiyUdUXcf+OnCdKPO+JqNvxO2kx4YNcr+c2jvjpE7Wv27W4uRS/C1jFEu3mpdhJyX34PWISY3ByNj/SxhhZRjfZ0UMkUJt3Bxx08rJU2xbFB16YEZDiG3JSy6sHlXNPbCHIbOVpHiN1VzjBLzKOCkmxjGKld6B4oNbjkiqi3rkJeBNN8jBj7SUEaxyGgnjE1OkS0mHkUAgd5X/qWF80mWR7PaOY0410GrnHHXVHpSqlZII521RzeXqtpkTkgEEitIiwF1YeLDJgQnIldbgAx5wMBj5z4br+aWB5GdGbxUxGjUp6ESLmxhJsaMFzx+Pi5+VIpN6bTUlcvPfw/InXlvjO5MjsdE/ucg6DjxRlEJY4Wb0J1IlnR0ZoXGEHF/6l1I68d+vj3ho9xH0mO+cjumNiMxvg/tTOWYcIAkqCl+XjRbtH7CHv4aCQrIQIui3TCxNPyN1BMXfhQFFxCgJ/yzmYAaTpGgEZpPoOq60GJctfkRaX5IBApRVTNTm/TvnYHqCEoh6kMzUCuNxnUUpVzkB/2+/Pc5iTpT5PdNUx78FrMT6kymqbugmEpxNZU4JXaph7v0GbOGxJQ3SZU+ryINSWT8iAt6skg7txPD1wCJN/rrQG0nZuNzo54nHQOnNj6zRTtRj5Pe5klu0d7NBGTThvFENhNE20NQS5BtD9GgUdQqyQZtaSuZ4bIr1fUGcmHTCz1SRpJNL9GeE3xNHe35/CDhRj04DhLzI48b9eI48mxxONvyGLn+wGtsLTY5mm87RFg/7jhNxh3bD2aANWtHSFsOu7Yfy60fIG4/6lw/lN14fOwedJdWXxKD7m1H8u7LAwZMZsn88mCDa46/v5DZ6OoIhcf7dg7Y7mPalb7XcVEwDEFU+V3H/QOplcP+ctPpgwAAAABJRU5ErkJggg==" />\n' +
                            '    </div>' +
                            '    <div class="wx-audio-right">' +
                            '     <div><p class="wx-audio-title">' + responseStr[i].title + '</p>' +
                            '     <p class="wx-audio-disc">' + responseStr[i].create_time + '</p>' +
                            '     <p class="wx-audio-disc">00:' + responseStr[i].duration ? responseStr[i].duration : 0 + '</p></div>' +
                                '    </div></div>' +
                                '   </div><div class="mask-bg"><div class="mask-icon"><i class="icon-ok"></i></div></div></div>';
                            $("#imgTextAdd").append(voice);
                        }
                        modalAddClick();
                    },
                    error: function (responseStr) {
                        layer.alert("出错啦");
                    }
                });
                let delHtml = '<span class="msg-panel-del del-tuwen">删除</span>';
                if ($(".subbutton-actived").attr('alt')) {
                    let row = $(".subbutton-actived").prevAll().length;
                    if (button[row].ctype != 'voice') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                } else if ($(".subbutton-actived").attr('id')) {
                    let row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                    let clo = $(".subbutton-actived").prevAll().length;
                    if (typeof (button[row].sub_button[clo].ctype) == 'undefined') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                    } else if (button[row].sub_button[clo].ctype != 'voice') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].sub_button[clo].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                }
            }

            // 视频
            function video1() {
                var appid = $("#appIdcode").val();
                $.ajax({
                    url: ApiUrl + '/admin/wechat.wechat/getMaterialByType',
                    type: 'POST',
                    data: {appid: appid, type: 'video'},
                    dataType: "json",
                    cache: false,
                    async: false,
                    success: function (responseStr) {
                        responseStr = responseStr.data;
                        $("#imgTextAdd").empty();
                        for (var i = 0; i < responseStr.length; i++) {
                            let voice = '<div id=' + responseStr[i].media_id + ' class="col-xs-4">' +
                            '   <div class="panel panel-default">' +
                            '   <div class="panel-body"><div class="wx-audio-content jg" >' +
                            '    <audio muted class="wx-audio-content"  src=\"' + responseStr[i].media_url + '\" loop="true"></audio>' +
                            '    <div class="wx-audio-left">\n' +
                            '     <img class="wx-audio-state"  src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFQAAABUCAMAAAArteDzAAAAaVBMVEUAAAAarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRkarRlIa6J1AAAAInRSTlMA9wYa38QR7ZJnMK1IIqBsO3fXDbSGQudZz5fKpV0rfbpRlHIjYQAAA35JREFUWMPFWduyqjAMDS0tgtwEFBGv/P9Hntmh3cWDTYsMs/Oio3SRy0qapuCU7PXIRdUGQxCFncgfrwzWCb/l4TCTML/xbxFlIQariEJ+AZnkwUBKkCdLIZvBQ5olsPw61Uhc4vTOa4Ca39P4IqYWXH2dyw5mWXUs2ez/8liZVx6YD2bW6wXRzmpesov0U70HxW5azTBmpD1xqJW9uUzfaS0Lp1ms0Nru6Nfv9WPSi8lahT2BKoWyvARPKZUPhLRiduq9ckHaKds6y5pa6XmARXJQutaEP4MzLJTzyJfmk193I2YKiyUdUXcf+OnCdKPO+JqNvxO2kx4YNcr+c2jvjpE7Wv27W4uRS/C1jFEu3mpdhJyX34PWISY3ByNj/SxhhZRjfZ0UMkUJt3Bxx08rJU2xbFB16YEZDiG3JSy6sHlXNPbCHIbOVpHiN1VzjBLzKOCkmxjGKld6B4oNbjkiqi3rkJeBNN8jBj7SUEaxyGgnjE1OkS0mHkUAgd5X/qWF80mWR7PaOY0410GrnHHXVHpSqlZII521RzeXqtpkTkgEEitIiwF1YeLDJgQnIldbgAx5wMBj5z4br+aWB5GdGbxUxGjUp6ESLmxhJsaMFzx+Pi5+VIpN6bTUlcvPfw/InXlvjO5MjsdE/ucg6DjxRlEJY4Wb0J1IlnR0ZoXGEHF/6l1I68d+vj3ho9xH0mO+cjumNiMxvg/tTOWYcIAkqCl+XjRbtH7CHv4aCQrIQIui3TCxNPyN1BMXfhQFFxCgJ/yzmYAaTpGgEZpPoOq60GJctfkRaX5IBApRVTNTm/TvnYHqCEoh6kMzUCuNxnUUpVzkB/2+/Pc5iTpT5PdNUx78FrMT6kymqbugmEpxNZU4JXaph7v0GbOGxJQ3SZU+ryINSWT8iAt6skg7txPD1wCJN/rrQG0nZuNzo54nHQOnNj6zRTtRj5Pe5klu0d7NBGTThvFENhNE20NQS5BtD9GgUdQqyQZtaSuZ4bIr1fUGcmHTCz1SRpJNL9GeE3xNHe35/CDhRj04DhLzI48b9eI48mxxONvyGLn+wGtsLTY5mm87RFg/7jhNxh3bD2aANWtHSFsOu7Yfy60fIG4/6lw/lN14fOwedJdWXxKD7m1H8u7LAwZMZsn88mCDa46/v5DZ6OoIhcf7dg7Y7mPalb7XcVEwDEFU+V3H/QOplcP+ctPpgwAAAABJRU5ErkJggg==" />\n' +
                            '    </div>' +
                            '    <div class="wx-audio-right">' +
                            '     <div><p class="wx-audio-title">' + responseStr[i].file_name + '</p>' +
                            '     <p class="wx-audio-disc">' + responseStr[i].create_time + '</p>' +
                            '     <p class="wx-audio-disc">00:' + responseStr[i].duration ? responseStr[i].duration : '' + '</p></div>' +
                                '    </div></div>' +
                                '   </div><div class="mask-bg"><div class="mask-icon"><i class="icon-ok"></i></div></div></div>';
                            $("#imgTextAdd").append(voice);
                        }
                        $("#imgTextAdd").append(voice);
                        modalAddClick();
                    },
                    error: function (responseStr) {
                        layer.alert("出错啦");
                    }
                });
                let delHtml = '<span class="msg-panel-del del-tuwen">删除</span>';
                if ($(".subbutton-actived").attr('alt')) {
                    let row = $(".subbutton-actived").prevAll().length;
                    if (button[row].ctype != 'video') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                } else if ($(".subbutton-actived").attr('id')) {
                    let row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                    let clo = $(".subbutton-actived").prevAll().length;
                    if (typeof (button[row].sub_button[clo].ctype) == 'undefined') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                    } else if (button[row].sub_button[clo].ctype != 'video') {
                        $('.msg-template').empty();
                        $('.msg-context-item').css("display", "block");
                        $('.mask-bg').hide();
                    } else {
                        var subKey = button[row].sub_button[clo].media_id;
                        if (typeof (subKey) == 'undefined') {
                            $('.msg-template').empty();
                            $('.msg-context-item').css("display", "block");
                            $('.mask-bg').hide();
                        } else {
                            $('.msg-template').html($('#' + subKey).html());
                            $('.msg-template').append(delHtml);
                            //绑定删除事件
                            $('.msg-panel-del').on('click', delClick);
                            $('.msg-context-item').css("display", "none");
                        }
                    }
                }
            }

            // 添加素材
            $('.msg-panel-add').click(function () {
                var index = layer.open({
                    type: 2,
                    content: '{:url("materialAdd")}',
                    area: ['800px', '100%'],
                    anim: 2,
                    maxmin: true,
                });
                layer.full(index);
            })

            // 选择素材
            function modalAddClick() {
                $(".msg-panel_select").on('click', function () {
                    if ($("li").hasClass("msg-tab_item on")) {
                        let dom = $("#clickUl").find(".on").attr('id');
                        switch (dom) {
                            case 'imgtext':
                                $(".modal-title1").text('选择图文消息');
                                break;
                            case 'img':
                                $(".modal-title1").text('选择图片消息');
                                break;
                            case 'voice':
                                $(".modal-title1").text('选择语音消息');
                                break;
                            case 'video':
                                $(".modal-title1").text('选择视频消息');
                                break;
                        }
                    }
                });


                //id为selectModal弹框选中遮罩层
                $('#selectModal .modal-body .panel').click(function () {
                    $(this).find('.mask-bg').show();
                    $(this).parent().siblings().find('.mask-bg').hide();
                    mId = $(this).parent().attr('id');
                });
                //id为selectModal弹框确定按钮事件
                $('#selectModal .ensure').on('click', function () {
                    var msgTemp = $('.msg-template');
                    var delEl = '<span class="msg-panel-del del-tuwen">删除</span>';
                    if (mId != null) {
                        msgTemp.html($('#' + mId).html());
                        delElement();
                        msgTemp.find('.mask-bg').hide();
                        msgTemp.siblings().hide();
                        msgTemp.show();
                        tempObj[mId] = msgTemp.html();
                        //绑定删除事件
                        $('.msg-panel-del').on('click', delClick);
                        var current = $('.subbutton-actived').prevAll().length;
                        var input_name = $('input[name="custom_input_title"]');
                        if ($('.menu-view-menu-sub-add').hasClass('subbutton-actived')) {
                            var sub_col = $(".subbutton-actived").prevAll().length;
                            var sub_row = $(".subbutton-actived").parents('.menu-view-menu').prevAll().length;
                            button[sub_row].sub_button[sub_col].name = input_name.val();
                            button[sub_row].sub_button[sub_col].media_id = mId;
                            button[sub_row].sub_button[sub_col].type = 'media_id';
                            if ($("li").hasClass("msg-tab_item on")) {
                                let dom = $("#clickUl").find(".on").attr('id');
                                switch (dom) {
                                    case 'imgtext':
                                        button[sub_row].sub_button[sub_col].ctype = null;
                                        break;
                                    case 'img':
                                        button[sub_row].sub_button[sub_col].ctype = 'image';
                                        break;
                                    case 'voice':
                                        button[sub_row].sub_button[sub_col].ctype = 'voice';
                                        break;
                                    case 'video':
                                        button[sub_row].sub_button[sub_col].ctype = 'video';
                                        break;
                                }
                            }
                        } else if ($('.menu-view-menu').hasClass('subbutton-actived')) {
                            button[current].name = input_name.val();
                            button[current].media_id = mId;
                            button[current].type = 'media_id';
                            if ($("li").hasClass("msg-tab_item on")) {
                                let dom = $("#clickUl").find(".on").attr('id');
                                switch (dom) {
                                    case 'imgtext':
                                        button[current].ctype = null;
                                        break;
                                    case 'img':
                                        button[current].ctype = 'image';
                                        break;
                                    case 'voice':
                                        button[current].ctype = 'voice';
                                        break;
                                    case 'video':
                                        button[current].ctype = 'video';
                                        break;
                                }
                            }
                        }
                    }
                    $('#selectModal').modal('hide');
                });
            }


            //同步
            $('#synchroBtns').click(function () {
                Fun.ajax({
                    url:api_url.edit,
                    appId: $("#appIdcode").val()
                },function(res){
                    data = JSON.parse(data);
                    var str = "";
                    for (var i = 0; i < data.length; i++) {
                        str += "<option value='" + data[i].app_id + "'>" + data[i].wxname + "</option>";
                    }
                    if (data.length >= 0) {
                        $(".menu-view-title").text(data[0].wxname);
                    }
                    $('#appIdcode').html(str);
                    appIdChange();
                },function (res) {
                    layer.msg('网络错误');
                })

            });

            //预览
            $(function () {
                // 预览点击
                $("#showPhone").on('click', function () {
                    $("#mobileDiv").css('display', "block");
                    $(".mask").css('display', "block");
                    //公众号名
                    $(".nickname").text(' ');
                    $(".nickname").text($("#appIdcode").find("option:selected").text());
                    $("#viewList").empty();
                    $("#viewShow").empty();
                    $(".cm-edit-after").css('display', 'none');
                    $(".cm-edit-before").css('display', 'block');
                    $(".subbutton-actived").removeClass('subbutton-actived');
                    //遍历按钮
                    let html
                    for (let i = 0; i < obj.menu.button.length; i++) {
                        html = '<li class="pre_menu_item grid_item size1of' + obj.menu.button.length + ' jsViewLi " id="menu_' + i + '">' +
                            '<a href="javascript:void(0);" class="jsView pre_menu_link" title="' + obj.menu.button[i].name + '" draggable="false" ' +
                            'media_id="' + obj.menu.button[i].media_id + '" ctype="' + obj.menu.button[i].ctype + '" type="' + obj.menu.button[i].type + '">';
                        if (obj.menu.button[i].sub_button.length) {
                            html += '<i class="icon_menu_dot"></i>';
                        }
                        html += obj.menu.button[i].name + '</a>';
                        let htmlDiv = '';
                        if (obj.menu.button[i].sub_button.length) {
                            htmlDiv += '<div class="sub_pre_menu_box jsSubViewDiv" style="display:none">' +
                                '<ul class="sub_pre_menu_list">';
                            for (let j = 0; j < obj.menu.button[i].sub_button.length; j++) {
                                htmlDiv += '<li id="subMenu_menu_0_' + j + '">' +
                                    '<a href="javascript:void(0);" class="jsSubView" title="' + obj.menu.button[i].sub_button[j].name + '" draggable="false" ' +
                                    'media_id="' + obj.menu.button[i].sub_button[j].media_id + '" ctype="' + obj.menu.button[i].sub_button[j].ctype + '" type="' + obj.menu.button[i].sub_button[j].type + '">' +
                                    obj.menu.button[i].sub_button[j].name +
                                    '</a>' +
                                    '</li>';
                            }
                            htmlDiv += '</ul>' +
                                '<i class="arrow arrow_out"></i>' +
                                '<i class="arrow arrow_in"></i>' +
                                '</div>';
                        }
                        html += htmlDiv + '</li>';
                        $("#viewList").append(html);
                    }

                    $(".jsViewLi").off('click').on('click', function () {
                        switch ($(this).find('.jsSubViewDiv').css('display')) {
                            case 'none':
                                $('.jsSubViewDiv').css('display', 'none');
                                $(this).find('.jsSubViewDiv').css('display', 'block');
                                break;
                            case 'block':
                                $('.jsSubViewDiv').css('display', 'none');
                                $(this).find('.jsSubViewDiv').css('display', 'none');
                                break;
                        }
                    })
                    $("#viewList").off('click').on('click', 'li', function () {
                        switch ($(this).children('a').attr('type')) {
                            case 'media_id':
                                let media_id = $(this).children('a').attr('media_id');
                                switch ($(this).children('a').attr('ctype')) {
                                    // $('#' + mId).html()
                                    case 'null':
                                        imageText();
                                        $("#viewShow").append('<li class="show_item">' + $('#' + media_id).html() + '</li>');
                                        $('.mobile_preview_bd').scrollTop($('#viewShow')[0].scrollHeight);
                                        break;
                                    case 'image':
                                        picture();
                                        $("#viewShow").append('<li class="show_item">' + $('#' + media_id).html() + '</li>');
                                        $('.mobile_preview_bd').scrollTop($('#viewShow')[0].scrollHeight);
                                        break;
                                    case 'voice':
                                        voice();
                                        $("#viewShow").append('<li class="show_item">' + $('#' + media_id).html() + '</li>');
                                        $('.mobile_preview_bd').scrollTop($('#viewShow')[0].scrollHeight);
                                        break;
                                    case 'video':
                                        video1();
                                        $("#viewShow").append('<li class="show_item">' + $('#' + media_id).html() + '</li>');
                                        $('.mobile_preview_bd').scrollTop($('#viewShow')[0].scrollHeight);
                                        break;
                                }
                                break;
                            case 'view': //根据当前点击li判断上级ul是否一级菜单

                                if ($(this).parent('ul').hasClass('sub_pre_menu_list')) {
                                    //代表二级菜单
                                    let row = $(this).parents('.pre_menu_item').prevAll().length;
                                    let col = $(this).prevAll().length;
                                    $(this).children('a').attr('href', button[row].sub_button[col].url);
                                    $(this).children('a').attr('target', '_blank');
                                } else if ($(this).parent('ul').hasClass('pre_menu_list')) {
                                    //代表一级菜单
                                    let row = $(this).prevAll().length;
                                    $(this).children('a').attr('href', button[row].url);
                                    $(this).children('a').attr('target', '_blank');
                                }
                                break;
                        }
                    });
                })
                // 退出预览
                $(".mobile_preview_closed").on('click', function () {
                    $("#mobileDiv").css('display', "none");
                    $(".mask").css('display', "none");
                })


            })
            Controller.api.bindevent();
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});