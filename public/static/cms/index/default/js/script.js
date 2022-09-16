function CheckInternetExplorer() {
    var bVersion = navigator.appVersion;
    var version = bVersion.split(";");
    if (version.length > 1) {
        var trimVersion = parseInt(version[1].replace(/[ ]/g, "").replace(/MSIE/g, ""));
        var $body = document.getElementsByTagName("body")[0];
        var msg = "<div style=\"text-align:center;background-color:#ff6a00;color:#fff;line-height:40px;;font-size:14px;\">您好，你当前使用的IE浏览器版本过低，为了获取更好的浏览体验，建议你使用谷歌(chrome)\火狐(Firefox)等标准浏览器，或升级IE10+以上版本！</div>";
        if (trimVersion <= 9) {
            msg += $body.innerHTML;
            $body.innerHTML = msg;
        }
    }
}

//保证所有响应式图片列表同一尺寸，避免上传图片尺寸不一致排版混乱
$(function () {
    var $imageSameSize = $(".imgae-same-size");
    $imageSameSize.each(function () {
        var $this = $(this);
        var $images = $this.find("img");
        if ($images.length == 0) {
            return true;
        }
        var $firstImg = $images.eq(0);
        //$(window).on("onload",function(){}) //弃用，效率太低,要等所有页面图片加载完毕才执行
        //$images.eq(0).attr("onload", function (){$images.ImageSameSize()}); //弃用火狐浏览器下获取不到响应式图片的实际高度
        var img = new Image();
        img.src = $firstImg.attr("src");
        img.onload = function () {
            $images.ImageSameSize();
        };
    });
});


//自动适应尺寸，如果容器列表项内有图片需要自动适应宽度和高度，所有容器中的图片文件尺寸默认用同级第一个容器尺寸。
;(function ($, window, undefined) {
    $.fn.ImageSameSize = function (index) {
        var $objs = this;
        if (index == undefined) {
            index = 0;
        }
        var $templateImage = $objs.eq(index);
        var SetSize = function () {
            var height = $templateImage.css("height").replace(/\s+|px/gi, "");
            var width = $templateImage.css("width").replace(/\s+|px/gi, "");
            $objs.each(function (idx) {
                $(this).css({ "width": width + "px", "height": height + "px" });
            });
        };
        SetSize();
        var bindResize = $templateImage.attr("data-bindResize");
        if (bindResize == undefined) {
            $(window).resize(function () {
                $templateImage.css({ "width": "auto","height": "auto" });
                SetSize();
            });
            $templateImage.attr("data-bindresize", 1);
        }
    };
})(jQuery, window);

//注册响应式菜单
; (function ($, window, undefined) {
    $.fn.InitNav = function (currentColumnId,topColumnId) {

        var $navLiItem = this;
        if (currentColumnId == undefined) {
            currentColumnId = "0";
        }
        if (topColumnId == undefined) {
            topColumnId = "0";
        }
        //添加子级菜单指示箭头
        $navLiItem.each(function () {
            var $this = $(this);
            if ($this.children("ul").length > 0) {
                $this.children("ul").children("li").addClass("animated navSlide");
                $this.append('<span class="arrow"></span>');
            }
            var attrDataId = $this.attr("data-id");
            if (attrDataId == topColumnId.toString()) {
                $this.addClass("current active");
            }
            else if (attrDataId == currentColumnId)
            {
                $this.addClass("current active");
                var $thisParentLi = $this.parentsUntil("div");
                $thisParentLi.addClass("current active");//给所有的父级li添加current active样式
            }
        });
        //指示箭头注册点击事件
        $navLiItem.find(".arrow").on("click", function () {
            var $this = $(this);
            $this.parent("li").toggleClass("active")
        });
    };
})(jQuery, window);


//js无缝滚动代码
function marquee(i, direction) {
    var obj = document.getElementById("marquee" + i);
    var obj1 = document.getElementById("marquee" + i + "_1");
    var obj2 = document.getElementById("marquee" + i + "_2");
    if (direction == "up") {
        if (obj2.offsetTop - obj.scrollTop <= 0) {
            obj.scrollTop -= (obj1.offsetHeight + 20);
        } else {
            var tmp = obj.scrollTop;
            obj.scrollTop++;
            if (obj.scrollTop == tmp) {
                obj.scrollTop = 1;
            }
        }
    } else {
        if (obj2.offsetWidth - obj.scrollLeft <= 0) {
            obj.scrollLeft -= obj1.offsetWidth;
        } else {
            obj.scrollLeft++;
        }
    }
}
function marqueeStart(i, direction) {
    var obj = document.getElementById("marquee" + i);
    var obj1 = document.getElementById("marquee" + i + "_1");
    var obj2 = document.getElementById("marquee" + i + "_2");

    obj2.innerHTML = obj1.innerHTML;
    var marqueeVar = window.setInterval("marquee(" + i + ", '" + direction + "')", 20);
    obj.onmouseover = function () {
        window.clearInterval(marqueeVar);
    }
    obj.onmouseout = function () {
        marqueeVar = window.setInterval("marquee(" + i + ", '" + direction + "')", 20);
    }
}