define(['backend'], function (Backend) {
    var  Backend = layui.Backend;
    return controller=  {
        index: function () {
            Backend.render(options =  {
                refreshUrl: '',
                themeid: '',
                maxTabs: '',
                loadingTime: '',
                theme:''
            })
            //刷新菜单事件
            $(document).on('refresh', '#layui-side-left-menu', function () {
                var _that = $(this);
                Fun.ajax({
                    url: Fun.url('ajax/refreshmenu'),
                }, function (res) {
                    if(typeof res.data =='object'){
                        if(Config.site.site_theme<4){
                            _that.html(res.data['menu']);
                            $('#layui-header-nav-pc').html(res.data['nav']);
                            $('#layui-header-nav-mobile').html(res.data['navm']);
                        }else if(Config.site.site_theme==4){
                            _that.html(res.data['menu']);
                            $('#layui-side-nav').html(res.data['nav']);
                        }

                    }else{
                        if(Config.site.site_theme==1){
                            _that.html(res.data);
                        }else{
                            $('#layui-side-left-menu5').html(res.data);
                        }
                    }
                    layui.element.render('nav');//重新渲染nav导航
                }, function () {
                    return false;
                });
            });
        },
        console:function () {
            Backend.api.bindEvent();
        }
    }
});
