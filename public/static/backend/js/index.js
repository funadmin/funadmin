define(['backend'], function (Backend) {
    var  Backend = layui.Backend;
    return controller=  {
        index: function () {
            Backend.render(options =  {
                refreshUrl: '',
                themeid: '',
                maxTabs: '',
                loadingTime: '',
            })
            //刷新菜单事件
            $(document).on('refresh', '#layui-side-left-menu', function () {
                var _that = $(this);
                Fun.ajax({
                    url: Fun.url('ajax/refreshmenu'),
                }, function (res) {
                    _that.html(res.msg);
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
