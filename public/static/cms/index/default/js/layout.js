
$(function () {


    windowwidth();
    $(window).resize(function () {
        windowwidth();
    });
    var backtop = $("a.zdsbacktop");
    var wint = $(window).scrollTop();
    $(window).on('scroll', function () {
        wint = $(window).scrollTop();
        if (wint <= 400) {
            backtop.fadeOut(300);
        };
        if (wint > 400) {
            backtop.fadeIn(300);
        };
    });

    backtop.on('click', function () {
        $("html,body").stop(false, true).animate({ "scrollTop": 0 }, 500);
        return false;
    });

    $(".pro-datial-tab table").wrap("<div class='product-table'></div>");


    $(".inxnews-list li").eq(0).addClass("fadeInLeft");
    $(".inxnews-list li").eq(1).addClass("fadeInLeft");
    $(".inxnews-list li").eq(2).addClass("fadeInRight");
    $(".inxnews-list li").eq(3).addClass("fadeInRight");


    $(".indxArrivals li").eq(0).addClass("fadeInRight");
    $(".indxArrivals li").eq(1).addClass("fadeInUp");
    $(".indxArrivals li").eq(2).addClass("fadeInLeft");

    $(".inxSolut-wrapper li").eq(0).addClass("fadeInLeft");
    $(".inxSolut-wrapper li").eq(1).addClass("fadeInLeft");
    $(".inxSolut-wrapper li").eq(2).addClass("fadeInRight");
    $(".inxSolut-wrapper li").eq(3).addClass("fadeInRight");


    $(document).on('click', function (ev) {
        if ($(window).width() > 1023) {

        }

        if ($(window).width() < 1024) {
            if (!$(ev.target).closest('.full-inside-subnav').length) {
                $('.inside-subnav .subnav').stop().slideUp();
            }
            if (!$(ev.target).closest('.third-cate').length) {
                $('.third-cate .cate-list').stop().slideUp();
            }
        }
        if (!$(ev.target).closest('.headcompany').length) {
            $('.company-choose').stop().hide();
        }


    });
    if($(".yLine").length > 0){
        $('.yLine').countTo({
            lastSymbol:"", //显示在最后的字符
            from: 0,  // 开始时的数字
            speed: 1500,  // 总时间
            refreshInterval:100,  // 刷新一次的时间
            beforeSize:0, //小数点前最小显示位数，不足的话用0代替
            decimals: 0  // 小数点后的位数，小数做四舍五入

        });
    }

    if($(".pro-screening").length>0){
        $(".pro-screening").Citys({
            Provice: ".a",
            City: ".b",
            defaul: ".defaultValue"
        });
    }

    // 副导航栏
    $('.third-chl').on('click', function () {
        $(this).next().stop().slideToggle();
    });

    $(".channel-title").click(function () {
        if ($(this).hasClass("cur")) {
            $(".full-inside-subnav .subnav").stop().slideUp(300, function () {
                $(this).removeAttr("style");
            });
            $(this).removeClass("cur");
        }
        else {
            $(".full-inside-subnav .subnav").stop().slideDown(300);
            $(this).addClass("cur");

        }
    });




    var clumbwid = 0;
    var clumbaLength = $(".common-clumb a").length;
    $(".common-clumb a").each(function () {
        var thisEle = $(this);
        clumbwid += thisEle.outerWidth();
    });

    if (clumbwid > $(".common-clumb").width()) {
        $(".common-clumb-box").append('<a href="javascript:;" class="sub-btn sub-prev"></a><a href="javascript:;" class="sub-btn sub-next"></a>')
        if ($(window).width() > 1023) {
            $(".common-clumb-box").css({ "padding": "0 40px" });
        }

        var swiper = new Swiper('.common-clumb', {
            pagination: '',
            paginationClickable: true,
            roundLengths: true,
            slidesPerView: "auto",
            spaceBetween: 0,
            nextButton: '.sub-next',
            prevButton: '.sub-prev',
            preventLinksPropagation: false
        });
        swiper.slideTo($(".common-clumb a.cur").index(), 0, false);
    }

    if(typeof Swiper != 'undefined') {

        var relatSwiper = new Swiper(".solwrapper-min", {
            slidesPerView: 4,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            nextButton:'.relat-solute-wrapper .relat-next',
            prevButton:'.relat-solute-wrapper .relat-prev',
            breakpoints: {
                414: {
                    spaceBetween: 0,
                    slidesPerView: 2
                }
            }
        });


        var caseSwiper = new Swiper(".caeslwrapper-min", {
            slidesPerView: 3,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            nextButton:'.relat-case-wrapper .relat-next',
            prevButton:'.relat-case-wrapper .relat-prev',
            breakpoints: {
                414: {
                    slidesPerView: 1
                }
            }
        });


        var proSwiper = new Swiper(".soproduct-min", {
            slidesPerView: 4,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            nextButton:'.relat-product-wrapper .relat-next',
            prevButton:'.relat-product-wrapper .relat-prev',
            breakpoints: {
                414: {
                    spaceBetween: 10,
                    slidesPerView: 2
                }
            }
        });

        // 职业发展
        var CareerSwiper = new Swiper(".wrapper-min", {
            slidesPerView: 3,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            nextButton:'.Career-wrapper .relat-next',
            prevButton:'.Career-wrapper .relat-prev',
            breakpoints: {
                414: {
                    slidesPerView: 1
                }
            }
        });

        // 发展历程
        var historyswiper = new Swiper('.historyulitem', {
            slidesPerView: 4,
            nextButton: '.hisswiper-right',
            prevButton: '.hisswiper-left',
            paginationClickable: true,
            spaceBetween: 0,
            slidesPerGroup: 1,
            breakpoints: {
                991: {
                    slidesPerView: 3,
                },
                414: {
                    spaceBetween: 0,
                    slidesPerView: 1
                }
            }
        });

        var inxnewswiper = new Swiper('.inxnews-list', {
            slidesPerView: 4,
            spaceBetween: 30,
            nextButton: '.indxNews-wraper .index-next',
            prevButton: '.indxNews-wraper .index-prev',
            paginationClickable: true,
            breakpoints: {
                768: {
                    slidesPerView: 3,
                    spaceBetween: 20
                },
                414: {
                    spaceBetween: 10,
                    slidesPerView: 1
                }
            }
        });

        var arrSwiper = new Swiper(".arrivalsp-min", {
            slidesPerView: 3,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            nextButton:'.arrivals-wrapper .index-next',
            prevButton:'.arrivals-wrapper .index-prev',
            breakpoints: {
                768: {
                    spaceBetween: 20
                },
                414: {
                    slidesPerView: 1
                }
            }
        });

        var inxsolutSwiper = new Swiper(".inxSolut-min", {
            slidesPerView: 4,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 30,
            pagination: '.inxSolut-wrapper .ipages',
            nextButton:'.inxSolut-wrapper .index-next',
            prevButton:'.inxSolut-wrapper .index-prev',
            breakpoints: {
                768: {
                    slidesPerView: 3,
                    spaceBetween: 20,
                },
                414: {
                    slidesPerView: 1
                }
            }
        });

        var inxpartSwiper = new Swiper(".partner-wrapper", {
            slidesPerView: 5,
            simulateTouch: false,
            speed: 1200,
            spaceBetween: 25,
            nextButton:'.inxpart-list .index-next',
            prevButton:'.inxpart-list .index-prev',
            breakpoints: {
                768: {
                    slidesPerView: 3,
                    spaceBetween: 20,
                },
                414: {
                    spaceBetween: 10,
                    slidesPerView: 2
                }
            }
        });

        var prodatSwiper = new Swiper('.minimg-wrapper', {
            effect: 'slide',
            loop: true,
            autoplay: 4000,
            speed: 1200,
            pagination: '.minimg-wrapper .ipages',
            paginationClickable: true,
            nextButton: '.prodatile-next',
            prevButton: '.prodatile-prev',
            preventClicks: false,
            autoplayDisableOnInteraction: false,
            // simulateTouch: false,
            grabCursor: false,
            parallax: true
        });
        // banner
        $(".indexbanner ul li").addClass("active");
        if ($(".indexbanner li").length > 1) {
            console.log(111)
            var indexSwiper = new Swiper('.indexbanner', {
                effect: 'slide',
                loop: true,
                autoplay: 3000,
                speed: 1000,
                pagination: '.indexbanner .ipages',
                paginationClickable: true,
                preventClicks: false,
                autoplayDisableOnInteraction: false,
                // simulateTouch: false,
                grabCursor: false,
                parallax: true,
                onTransitionEnd: function onTransitionEnd(swiper) {
                    $(".indexbanner ul li").eq(swiper.activeIndex).addClass("active").siblings().removeClass("active");
                }
            });
        }
        $('.indexbanner').mouseover(function () {
            indexSwiper.stopAutoplay();
        });
        $('.indexbanner').mouseout(function () {
            indexSwiper.startAutoplay();
        });


    }

    $(".tab-options a").click(function () {
        var t = $(".tab-options a").index($(this));
        $(".tab-options a").removeClass("cur").eq(t).addClass("cur");
        $("html,body").animate({scrollTop: $(".composimin").eq(t).offset().top - 80}, {duration: 500,easing: "swing"});

    });

    // if($('.tab-options').length>0) {
    //     $(document).ready(function (e) {
    //         t = $('.tab-options').offset().top - 80;
    //         // fh = $('.tab-options').height();
    //         $(window).scroll(function (e) {
    //             s = $(document).scrollTop();
    //             console.log(s,t);
    //             if (s > t) {
    //                 $('.tab-options').css('position', 'fixed');
    //                 if (s > t) {
    //                     $('.tab-options').css({'top': '100px', 'right': "0", "left": "0"});
    //                 }
    //             } else {
    //                 $('.tab-options').css('position', '');
    //             }
    //         })
    //     });
    // }

    //常见问题
    $(".Problem-list li").eq(0).addClass("cur");
    $(".prob-cont").eq(0).show();
    $(document).on("click", ".Problem-list li h3", function () {

        if ($(this).next().is(":hidden")) {
            $(".prob-cont").slideUp(300);
            $(".Problem-list li").removeClass("cur");
            $(this).parents("li").addClass("cur");
            $(this).next().slideDown(300);
        } else {
            $(this).parents("li").removeClass("cur");
            $(this).next().slideUp(300);
        }

    });

    //// 资质荣誉弹窗
    //var _popW = $(window).width() * 0.94 < 800 ? $(window).width() * 0.94 : 800;
    //var _popH = $(window).height() * 0.65 < 600 ? $(window).height() * 0.65 : 600;
    //var _poprate = _popW / _popH;

    //function getSlide(originImgs, popupSwStr, numWrap) {
    //    var $thumb = $(originImgs);
    //    var $thumbSwiper = $(popupSwStr).html('');
    //    var slide_length = $thumb.length;
    //    for (var i = 0; i < slide_length; i++) {
    //        var $img = $thumb.eq(i);
    //        var imgW = $img.width();
    //        var imgH = $img.height();
    //        var rate = imgW / imgH;
    //        var $item = $('<li class="swiper-slide"><img src="' + $thumb[i].src + '"></li>');
    //        if (rate < _poprate) {
    //            $item.find('img').addClass('maxh');
    //        }
    //        ; $thumbSwiper.append($item);
    //    }
    //    ; $(numWrap).html(slide_length);
    //};
    //getSlide(".Honor-list ul img,.Environ-list ul img,.Erespon-list ul img,.Employee-min ul img", ".thumb-popup .swiper-wrapper", ".thumb-count");
    //if (typeof Swiper != 'undefined') {
    //    var thumbCopySwiper = new Swiper(".thumb-copy-wrapper", {
    //        speed: 1000,
    //        prevButton: '.swiper-left',
    //        nextButton: '.swiper-right',
    //        loop: false,
    //        pagination: "",
    //        observer: true,
    //        observeParents: true,
    //        onSlideChangeStart: function (swiper) {
    //            var thisText = $(".Honor-list li,.Environ-list li,.Erespon-list li,.Employee-min li").eq(thumbCopySwiper.activeIndex).find(" p").text();

    //            $(".thumbtext-list").html(thisText);
    //            $(".thumb-index").html(thumbCopySwiper.activeIndex + 1);
    //        }
    //    });

    //};
    //$('.comloading').on('click', function () {
    //    getSlide(".Honor-list ul img,.Environ-list ul img,.Erespon-list ul img,.Employee-min ul img", ".thumb-popup .swiper-wrapper", ".thumb-count");
    //});
    //$(document).on("click", ".Honor-list li a,.Environ-list li a,.Erespon-list li a", function (ev) {
    //    ev.preventDefault();
    //    var thisIndex = $(this).parents("li").index();
    //    var thisText = $(this).find(".elc-text").text();
    //    console.log(thisIndex);
    //    thumbCopySwiper.slideTo($(this).parents("li").index(), 0, false);

    //    $(".thumbtext-list").html(thisText);
    //    $(".thumb-index").html(thisIndex + 1);
    //    $(".thumb-popup").fadeIn(300);
    //    $(".probg").fadeIn(300);
    //    var $height = $(window).height();
    //    $(".thumb-popup ul li").css("height", $height);
    //});

    $(".thumb-popup .close").click(function () {
        $(".thumb-popup").fadeOut(300);
        $(".probg").fadeOut(300);
    });



    function windowwidth() {
        function Itemheight(item,li) {

            var heightArr = [];
            // default
            // $(item).find(li).css('height', 'auto');
            // maxheight
            $(item).find(li).each(function(){
                heightArr.push($(this).outerHeight());
            });
            var maxHeight = Math.max.apply(Math, heightArr);
            $(item).find(li).css('height', maxHeight);

        };



        $(".header-nav li .sub-item ").each(function () {
            if ($(this).find(".three-nav a").length > 0) {
                $(this).find(".three-btn").show();
            } else {
                $(this).find(".three-btn").hide();
            }
        });

        if($(window).width()>767){
            Itemheight(".inquirydel-list",".inqui-cont");
            Itemheight(".Comculture-list",".content");
        }

        if ($(window).width() > 1023) {
            $(window).scroll(function () {
                var sticky = $('header');
                var scrolls = $(window).scrollTop();

                if (scrolls >10) {
                    sticky.addClass('shadow');
                }else{
                    sticky.removeClass('shadow');
                }

            });


            $(".indexbanner li").each(function () {
                var thisindex = $(this);
                var datecolor = thisindex.find(".bannertextcont").attr("data-color");
                var dateclass = thisindex.find(".bannertextcont").attr("data-dq");
                var listattr = thisindex.find(".bannertextcont").attr("style");
                var thisimg = thisindex.find("a .img").attr("pc-img");
                thisindex.find("a .img").attr("src", thisimg);
                thisindex.find(".bannertextcont").attr("style", datecolor);
                thisindex.find(".bannertextcont").addClass(dateclass);
            });



            if($(".scrollBox").length>0){
                $(".scrollBox").scrollBar();
            };



            //导航
            $(".headerweb,.search-con").unbind("click");
            $(".childMenu .sub-nav").show();

            $(".pcnavmenubtn").click( function () {
                var winW = $(window).width();
                var tempW = 320;
                $('.exit-off-canvas').width(0);
                $('.exit-off-canvas').width(winW - tempW);
                $(".pcnav-btn").toggleClass("g_close");
                $(".headsideNav").addClass("cur");
            });
            $('.exit-off-canvas,.slide-colse').click(function() {
                var winW = $(window).width();
                var tempW = 320;
                $('.exit-off-canvas').width(0);
                $(".headsideNav").removeClass("cur");
                $(".pcnav-btn").removeClass("g_close");
            });


            //搜索
            $(".search-con").unbind("mouseenter").bind("mouseenter", function () {
                $(".searchbox").stop(true, true).fadeIn(300);
                $('.sub-nav,.sub-bg').stop().slideUp();
                $(".pcnav-btn").removeClass("g_close");
            });

            $(".search-con").unbind("mouseleave").bind("mouseleave", function () {
                $(".searchbox").stop(true, true).fadeOut(300);
            });


            $(".headcompany").unbind("mouseenter").bind("mouseenter", function () {
                $(".company-choose").stop(true, true).fadeIn(300);
                $('.sub-nav,.sub-bg').stop().slideUp();
                $(".pcnav-btn").removeClass("g_close");
            });

            $(".headcompany").unbind("mouseleave").bind("mouseleave", function () {
                $(".company-choose").stop(true, true).fadeOut(300);
            });


            $(document).on("click",".headsideNav li",function () {

            });
            $(".headsideNav .slide-nav").html($(".header-nav ul").html());
            $(document).on("click", ".slide-nav li h3", function () {
                if ($(this).next().is(':hidden')) {
                    $(this).addClass('cur').next().stop().slideDown().parent().siblings().find('h3').removeClass('cur').next().stop().slideUp();
                } else {
                    $(this).removeClass('cur').next().stop().slideUp();
                }
            });

            // common-clumb
            if($(".common-clumb-min").find(".common-clumb-box")){
                $(".common-clumb-min .clumb-title").addClass("fl");
                $(".common-clumb-min .common-clumb-box").addClass("fr")
            };


            $(".inside-subnav .subnav").show();

            $(".inside-subnav .subnav ul").addClass("swiper-wrapper");
            $(".subnav ul li").addClass("swiper-slide");

            var navListwid = 0;
            var navListLength = $(".inside-subnav .subnav ul li").length;
            $(".inside-subnav .subnav ul li").each(function () {
                var thisEle = $(this);
                navListwid += thisEle.outerWidth();
            });

            if (navListwid > $(".inside-subnav .subnav").width()) {
                $(".inside-subnav .sub-btn").show();
                $(".inside-subnav .subnav").addClass("padding");
                var swiper = new Swiper('.inside-subnav .subnav', {
                    pagination: '',
                    nextButton: '.sub-next',
                    prevButton: '.sub-prev',
                    paginationClickable: true,
                    roundLengths: true,
                    slidesPerView: "auto",
                    spaceBetween: 0,
                    preventLinksPropagation: false

                });
                swiper.slideTo($(".inside-subnav .subnav ul li.cur").index(), 0, false);
            } else {
                $(".inside-subnav .subnav ul").removeClass("swiper-wrapper");
                $(".inside-subnav .subnav ul li").removeClass("swiper-slide");
                $(".inside-subnav .sub-btn").hide();
            };

            $('.inside-subnav .subnav ,.third-cate .cate-list').show();


        }
        else {
            // subnav

            $(".subnav ul").removeClass("swiper-wrapper");
            $(".subnav ul li").removeClass("swiper-slide");
            $(".subnav").hide();

            $(".contact-inside-subnav li").click(function () {
                var text = $(this).text();
                $(".channel-title").text(text);
                $(".subnav ").stop().slideUp();
            });

            $(".indexbanner li").each(function () {
                var thisindex = $(this);
                var listattr = thisindex.find(".bannertextcont").attr("style");
                var thisimg = thisindex.find("a .img").attr("mobile-img");
                thisindex.find("a .img").attr("src", thisimg);
                thisindex.find(".bannertextcont").attr("style", "");
            });


            //移动端导航
            $(".header-nav").hide();
            $(".header-nav,.search-con").unbind("mouseenter");
            $(".header-nav,.search-con").unbind("mouseleave");
            //移动端展开下拉
            $(".mobnav-btn").unbind("click").bind("click", function () {
                $(".searchbox,.company-choose").hide();
                if ($(".header-nav").is(":hidden")) {
                    $(this).addClass("g_close");
                    $(".header-nav").stop().slideDown();
                    $(".headlanguage").stop().slideDown();
                }
                else {
                    $(".header-nav").stop().slideUp();
                    $(this).removeClass("g_close");
                    $(".headlanguage").stop().slideUp();
                }
            });

            // 头部语言切换
            $('.headcompany').on('click', function (ev) {
                ev.stopPropagation();
                if ($(".company-choose").is(":hidden")) {
                    $('.company-choose').stop().slideDown();
                    $(".searchbox,.header-nav").hide();
                    $(".mobnav-btn").removeClass("g_close")
                }
                else {
                    $('.company-choose').stop().slideUp();
                }
            });


            //移动端二级导航
            $(".header-nav li").each(function () {
                if($(this).find(".sub-nav a").length>0){
                    $(this).find(".phsearchicon").show();
                }
                else{
                    $(this).find(".phsearchicon").hide();
                }
            });
            $(".phsearchicon").unbind("click").bind("click", function () {
                if ($(this).parents("li").find(".sub-item").is(":hidden")) {
                    $(this).parents("li").find(".sub-nav").stop().slideDown().parents("li").siblings().find(".sub-nav").stop().slideUp();
                    $(this).parents("li").addClass("cur").siblings().removeClass("cur");
                    $(this).parents("li").siblings().find('.three-nav').slideUp();
                    $(this).parents("li").siblings().find(".sub-item").removeClass("cur");
                }
                else {
                    $(this).parents("li").find(".sub-nav").stop().slideUp();
                    $(this).parents("li").removeClass("cur");
                    $(this).parents("li").find('.three-nav').slideUp();
                    $(this).parents("li").find(".sub-item").removeClass("cur");
                }
            });



            // three-nav
            $(".three-btn").unbind("click").bind("click", function () {
                if ($(this).parents(".sub-item").find(".three-nav").is(":hidden") && $(this).parents(".sub-item").find(".three-nav a").length > 0) {
                    $(this).parents(".sub-item").find(".three-nav").stop().slideDown().parents(".sub-item").siblings().find(".three-nav").stop().slideUp();
                    $(this).parents(".sub-item").addClass("cur").siblings().removeClass("cur");
                }
                else {
                    $(this).parents(".sub-item").find(".three-nav").stop().slideUp();
                    $(this).parents(".sub-item").removeClass("cur");
                }
            });



            //搜索
            $(".search-inco").unbind("click").bind("click", function () {
                $(".header-nav").hide();
                $(".mobnav-btn").removeClass("g_close");
                if ($(".searchbox").is(":hidden")) {
                    $(".searchbox").stop().slideDown();
                }
                else {
                    $(".searchbox").stop().slideUp();
                }
            })


        };

        if($(window).width()>1279){
            $(".header-nav").show();
            //$('.header-nav').on('mouseenter', 'ul li', function() {

            //    var curChildUl = $(this).find('.sub-nav');
            //    var curHasChild = curChildUl ? curChildUl.length : 0;
            //    var curLeft = $(this).offset().left;
            //    var curHtml = '';
            //    if (curHasChild > 0) {
            //        curHtml = curChildUl.prop("outerHTML");
            //        $('.childMenu').css('padding-left', curLeft + 15 - $('.childMenu').offset().left).html(curHtml);
            //        $('.childMenu').addClass('active');
            //    } else {
            //        $('.childMenu').html('').removeClass('active');
            //    };
            //});
            //$('.header-nav').on('mouseleave', 'ul li', function() {
            //    $('.childMenu').removeClass('active');
            //});
            //$('.childMenu').hover(function() {
            //    $('.childMenu').addClass('active');
            //}, function(e) {
            //    $('.childMenu').removeClass('active');
            //});
            $(".header-nav li").hover(function () {
                if ($(this).find(".sub-nav a").length > 0) {
                    $(this).find(".sub-nav").stop(true, true).fadeIn(0);
                }
            }, function () {
                $(this).find(".sub-nav").stop(true, true).fadeOut(0);
            });
            $(".header-nav li .sub-item").hover(function () {
                if ($(this).find(".three-nav a").length > 0) {
                    $(this).find(".three-nav").stop(true, true).fadeIn(0);
                }
            }, function () {
                $(this).find(".three-nav").stop(true, true).fadeOut(0);
            });


        }


        //      2018.11.19
        if ($(window).width() >= 1024) {
            $(".sub-box").css("display", "block");
            $(".footer ul li h3").unbind("click");
        }
        else {

            $(".sub-box").css("display", "none");
            $(".footer ul li h3").unbind("click").bind("click", function () {
                if ($(this).next().is(":hidden")) {
                    $(".sub-box").slideUp(300);
                    $(".footer ul li h3").removeClass("cur");
                    $(this).addClass("cur");
                    $(this).next().slideDown(300);
                }
                else {
                    $(this).removeClass("cur");
                    $(this).next().slideUp(300);
                }
            });

        }

    }


});
