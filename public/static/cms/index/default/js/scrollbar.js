/**
 * Created by Administrator on 2017/9/6.
 */


//Vertical and horizontal
/**
 * 用div的拖拽以及滚动事件代替了滚动条，通过改变div的样式来改变滚动条的样式
 */

;(function($){
    $.fn.extend({
        "scrollBar":function(options){
            var settings = {
                barWidth:5,
                position:"x,y",
                wheelDis:15

            };

            options = $.extend(settings,options);

            var horizontalDiv = '<div class="zl-scrollBarBox" style="width:100%;left:0;height:'+settings.barWidth+'px;bottom:0;">'+
                '<div class="zl-scrollBar zl-horizontalBar" style="height:'+settings.barWidth+'px;border-radius:'+settings.barWidth/2+'px;"></div>'+
                '</div>';
            var verticalDiv = '<div class="zl-scrollBarBox" style="height:100%;top:0;width:'+settings.barWidth+'px;right:0;">'+
                '<div class="zl-scrollBar zl-verticalBar" style="width:'+settings.barWidth+'px;border-radius:'+settings.barWidth/2+'px;"></div>'+
                '</div>';

            var T = this;

            /**
             * 将每个div的padding值保存到自定义属性中
             */

            T.each(function(){

                $(this).attr("paddingR",$(this).css("padding-right")).attr("paddingB",$(this).css("padding-bottom"));

            });
            /**
             *
             *创建滚动条的函数
             */
            function creatScrollBar(obj){

                var This = $(obj).get(0);//转化为JS对象，增加运行效率

                var paddingR = parseFloat($(obj).attr("paddingR"));
                var paddingB = parseFloat($(obj).attr("paddingB"));

                $(obj).css({
                    "padding-right":paddingR+"px",
                    "padding-bottom":paddingB+"px",
                    "overflow":"hidden"
                });

                //获取内容的总高度和总宽度


                if(!($(obj).children().hasClass("zl-scrollContentDiv"))){
                    $(obj).wrapInner('<div class="zl-scrollContentDiv"></div>');
                };

                if($(obj).css("position")=="static"){
                    $(obj).css({"position":"relative"});
                };

                var scrollContentDiv = $(obj).find(".zl-scrollContentDiv");

                var scrollH = scrollContentDiv[0].scrollHeight;
                var scrollW = scrollContentDiv[0].scrollWidth;

                var innerH = scrollContentDiv.height();
                var innerW = scrollContentDiv.width();
                var outerH = $(obj).innerHeight();
                var outerW = $(obj).innerWidth();

                function addVerticalBar(a){

                    This.style.paddingRight = paddingR + settings.barWidth + 'px';

                    innerH = $(a).height();

                    var barHeight = outerH*(innerH/scrollH);

                    $(a).find(".zl-scrollBarBox").remove().end().append(verticalDiv).find(".zl-verticalBar").height(barHeight);

                };
                function addHorizontalBar(a){

                    This.style.paddingBottom = paddingB + settings.barWidth + 'px';
                    innerW = $(a).width();

                    var barWidth = outerW*(innerW/scrollW);

                    $(a).find(".zl-scrollBarBox").remove().end().append(horizontalDiv).find(".zl-horizontalBar").width(barWidth);

                };

                switch (settings.position){

                    case "x,y":

                        if(scrollH>innerH && scrollW>innerW){
                            This.style.paddingRight = paddingR + settings.barWidth + 'px';
                            innerH = $(obj).height();
                            outerH = $(obj).innerHeight();

                            var barHeight = (outerH-settings.barWidth)*((innerH-settings.barWidth)/scrollH);
                            if(!($(obj).find(".zl-verticalBar").length)){
                                $(obj).append(verticalDiv);
                            };

                            $(obj).find(".zl-verticalBar").height(barHeight).parent().height(outerH-settings.barWidth);


                            This.style.paddingBottom = paddingB + settings.barWidth + 'px';
                            innerW = $(obj).width();

                            var barWidth = (outerW-settings.barWidth)*((innerW-settings.barWidth)/scrollW);

                            if(!($(obj).find(".zl-horizontalBar").length)){
                                $(obj).append(horizontalDiv);
                            };
                            $(obj).find(".zl-horizontalBar").width(barWidth).parent().width(outerW)
                                .css({
                                    "padding-right":settings.barWidth+"px",
                                    "box-sizing":"border-box"
                                });

                        }else if(scrollH>innerH){

                            addVerticalBar(obj);


                        }else if(scrollW>innerW){

                            addHorizontalBar(obj);

                        }else{
                            $(obj).find(".zl-scrollBarBox").remove();
                        }
                        break;

                    case "x":

                        if(scrollW>innerW){

                            addHorizontalBar(obj);

                        }else{
                            $(obj).find(".zl-scrollBarBox").remove();
                        }
                        break;

                    case "y":

                        if(scrollH>innerH){

                            addVerticalBar(obj);

                        }else{
                            $(obj).find(".zl-scrollBarBox").remove();
                        }

                        break;
                };


            }

            /**
             * 循环给每一个Div添加
             */
            function recycleThis(){

                T.each(function(){

                    creatScrollBar(this);

                });
            };

            recycleThis();//执行循环

            /**
             *创建监听div内容变化的定时器
             */

            function creatTimer(obj,oldWidth,oldHeight,oldInnerWidth,oldInnerHeight,timer){

                timer = setInterval(function(){

                    var newWidth = null;
                    var newHeight = null;
                    var newInnerWidth = null;
                    var newInnerHeight = null;
                    var topDiv = null;
                    var leftDiv = null;
                    var topBar = null;
                    var leftBar = null;
                    var scrollContentDiv = $(obj).find(".zl-scrollContentDiv");

                    if(scrollContentDiv.length){
                        newWidth = scrollContentDiv[0].scrollWidth;
                        newHeight = scrollContentDiv[0].scrollHeight;

                        newInnerWidth = scrollContentDiv.width();
                        newInnerHeight = scrollContentDiv.height();
                    }else{
                        newWidth = $(obj)[0].scrollWidth - parseFloat($(obj).css("padding-left"));
                        newHeight = $(obj)[0].scrollHeight - parseFloat($(obj).css("padding-top"));
                        newInnerWidth = $(obj).width();
                        newInnerHeight = $(obj).height();

                    };

                    if(newWidth!=oldWidth || newHeight!=oldHeight || newInnerWidth!=oldInnerWidth || newInnerHeight!=oldInnerHeight){

                        //记录更新滚动条长短前的位置
                        if(scrollContentDiv.length){
                            topDiv = parseFloat(scrollContentDiv.css("top"));
                            leftDiv = parseFloat(scrollContentDiv.css("left"));
                        };
                        if($(obj).find(".zl-verticalBar").length){
                            topBar = parseFloat($(obj).find(".zl-verticalBar").css("top"));
                        };
                        if($(obj).find(".zl-horizontalBar").length){
                            leftBar = parseFloat($(obj).find(".zl-horizontalBar").css("left"));
                        };
                        //更新滚动条长短或有无
                        creatScrollBar(obj);

                        if($(obj).find(".zl-scrollBarBox").length){
                            if(topDiv){
                                var maxTopBox = scrollContentDiv[0].scrollHeight - $(obj).height();
                                var maxLeftBox = scrollContentDiv[0].scrollWidth - $(obj).width();
                                if(-leftDiv>maxLeftBox){
                                    leftDiv = -maxLeftBox;
                                }
                                if(-topDiv>maxTopBox){
                                    topDiv = -maxTopBox;
                                }
                                scrollContentDiv.css({
                                    "left":leftDiv+'px',
                                    "top":topDiv+'px'
                                });
                            };
                            //将原来的位置赋值给现在的滚动条
                            if(topBar && $(obj).find(".zl-verticalBar").length){
                                var verticalBar = $(obj).find(".zl-verticalBar");
                                var maxTop = verticalBar.parent().height() -verticalBar.height();

                                if(topBar>maxTop){
                                    topBar = maxTop;
                                }
                                verticalBar.css("top",topBar+'px');
                            };
                            if(leftBar && $(obj).find(".zl-horizontalBar").length){
                                var horizontalBar = $(obj).find(".zl-verticalBar");
                                var maxLeft = horizontalBar.parent().width() -horizontalBar.width();

                                if(leftBar>maxLeft){
                                    leftBar = maxLeft;
                                }

                                $(obj).find(".zl-horizontalBar").css("left",leftBar+'px');
                            };
                        }

                        oldWidth = newWidth;
                        oldHeight = newHeight;

                        oldInnerHeight = newInnerHeight;
                        oldInnerWidth = newInnerWidth;
                    }

                },100);


            }

            /**
             * 通过循环给每一个div添加上监听内容变化的定时器
             */
            function addTimer(){
                $.each(T,function(k,v){

                    var obj = v;
                    var timer = "timer"+k;
                    var oldWidth = null;
                    var oldHeight = null;
                    var oldInnerWidth = null;
                    var oldInnerHeight = null;

                    if($(v).find(".zl-scrollContentDiv").length){
                        oldWidth = $(v).find(".zl-scrollContentDiv")[0].scrollWidth ;
                        oldHeight = $(v).find(".zl-scrollContentDiv")[0].scrollHeight;
                        oldInnerWidth = $(v).find(".zl-scrollContentDiv").width();
                        oldInnerHeight = $(v).find(".zl-scrollContentDiv").height();

                    }else{
                        oldWidth = $(obj)[0].scrollWidth - parseFloat($(obj).css("padding-left"));
                        oldHeight = $(obj)[0].scrollHeight - parseFloat($(obj).css("padding-top"));

                        oldInnerWidth = $(obj).width();
                        oldInnerHeight = $(obj).height();
                    }
                    creatTimer(obj,oldWidth,oldHeight,oldInnerWidth,oldInnerHeight,timer);
                });
            }
            addTimer();
            function clearTimer(){
                $.each(T,function(index,item){
                    var timer = "timer"+index;
                    clearInterval(timer);
                });
            };
            /**
             * 滚动条拖拽效果
             */
            this.on("mousedown",".zl-scrollBar",function(ev){

                clearTimer();//清除定时器

                var direction = null;

                if($(this).hasClass("zl-verticalBar")){

                    direction = "0";

                }else if($(this).hasClass("zl-horizontalBar")){

                    direction = "1";

                }

                var This = $(this).get(0);

                var height = $(this).parent().height() - $(this).height();
                var width = $(this).parent().width() - $(this).width();

                var contentDiv = $(this).parent().parent().find(".zl-scrollContentDiv").get(0);

                var scrollH = contentDiv.scrollHeight;
                var innerH = $(this).parent().parent().height();

                var scrollW = contentDiv.scrollWidth;
                var innerW = $(this).parent().parent().width();


                var ev = ev || event;

                var disY = ev.clientY - This.offsetTop;
                var disX = ev.clientX - This.offsetLeft;

                var topCount = null;
                var leftCount = null;

                switch (direction){

                    case "0":

                        document.onmousemove = function(ev){

                            var ev = ev || event;

                            if(ev.clientY - disY <= 0){

                                topCount = 0;

                            }else

                            if((ev.clientY - disY) >= height){

                                topCount = height;

                            }else{

                                topCount = ev.clientY - disY;

                            }

                            This.style.top = topCount  + "px";

                            contentDiv.style.top = -(topCount*(scrollH - innerH)/height) + 'px';

                        };

                        break;

                    case "1":

                        document.onmousemove = function(ev){

                            var ev = ev || event;

                            if(ev.clientX - disX <= 0){

                                leftCount = 0;

                            }else

                            if((ev.clientX - disX) >= width){

                                leftCount = width;

                            }else{

                                leftCount = ev.clientX - disX;

                            }

                            This.style.left = leftCount  + "px";

                            contentDiv.style.left = -(leftCount*(scrollW - innerW)/width) + 'px';

                        };

                        break;

                }

                document.onmouseup = function(){

                    document.onmousemove = null;
                    document.onmouseup = null;

                    addTimer();//添加定时器

                };

                return false;
            });

            /**
             *鼠标滚轮效果
             */

            function fn(ev,a){
                if(a.find(".zl-verticalBar").length){

                    var b = true;
                    var height = a.find(".zl-scrollBarBox").height() - a.find(".zl-scrollBar").height();
                    var contentDiv = a.find(".zl-scrollContentDiv").get(0);
                    var scrollH = contentDiv.scrollHeight;
                    var innerH = a.height();

                    if(ev.wheelDelta){
                        b = ev.wheelDelta>0?true:false;
                    }else{
                        b = ev.detail<0?true:false;
                    }

                    var topDis = parseFloat(a.find(".zl-scrollBar").css("top"));

                    if(b){

                        topDis -= settings.wheelDis;

                        if(topDis <0){

                            topDis = 0;

                        }

                    }else{

                        topDis += settings.wheelDis;

                        if(topDis > height){

                            topDis = height;

                        }
                    }

                    a.find(".zl-scrollBar").get(0).style.top = topDis  + "px";
                    a.find(".zl-scrollContentDiv").get(0).style.top = -(topDis*(scrollH - innerH)/height) + 'px';

                }
            };


            T.each(function(){

                var oDiv = $(this).get(0);

                if(oDiv.addEventListener){

                    oDiv.addEventListener("DOMMouseScroll",function(ev){

                        var ev = ev || event;
                        var $This = $(this);

                        fn(ev,$This);

                        ev.preventDefault();

                    },false);

                }

                oDiv.onmousewheel = function(ev){

                    var ev = ev || event;
                    var $This = $(this);

                    fn(ev,$This);

                    return false;
                };

            });

            return this;
        }

    });
})(jQuery);