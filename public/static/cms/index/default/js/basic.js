/* PageAdmin Cms Js基础库 */
String.prototype.trim = function () //去除首尾空格
{
    return this.replace(/(^\s*)|(\s*$)/g, "");
}
String.prototype.lTrim = function () //去除左空格
{
    return this.replace(/(^\s*)/g, "");
}
String.prototype.rTrim = function () //去除右空格
{
    return this.replace(/(\s*$)/g, "");
}
String.prototype.strLength = function () //获取字符长度,中文站两个字符
{
    return this.replace(/[^\x00-\xff]/g, "**").length;
}
String.prototype.left = function (len) //左边截取字数,中文按两个字符计算
{
    var str = this;
    if (isNaN(len) || len == null) {
        len = str.length;
    }
    var r = /[^\x00-\xff]/g;
    if (str.replace(r, "**").length <= len) { return str; }
    var m = Math.floor(len / 2);
    for (var i = m; i < str.length; i++) {
        if (str.substr(0, i).replace(r, "**").length >= len) {
            return str.substr(0, i);
        }
    }
    return str;
}

String.prototype.right = function (len) //右边截取字段数,中文按两个字符计算
{
    var str = this;
    if (isNaN(len) || len == null) {
        len = str.length;
    }
    var r = /[^\x00-\xff]/g;
    if (str.replace(r, "**").length <= len) { return str; }
    var strLength = str.length;
    for (var i = 0; i < strLength; i++) {
        if (str.substr(strLength - i).replace(r,"**").length >= len) {
            return str.substr(strLength - i);
        }
    }
    return str;
}

//只对字符值进行比较，相同等返回true
function equals(x, y) {
    if (x == y) {
        return true;
    }
    else if ((typeof x == "object" && x != null) && (typeof y == "object" && y != null)) {
        x = JSON.stringify(x);
        y = JSON.stringify(y);
        if (x.length != y.length) {
            return false;
        }
        return x == y;
    }
    else {
        if (typeof x == "boolean" || typeof x == "number" || typeof x == "bigint") {
            x = x.toString();
        }
        if (typeof y == "boolean" || typeof y == "number" || typeof y == "bigint") {
            y = y.toString();
        }
        return x == y;
    }

}

//深拷贝
function deepCopy(source) {
    if (typeof source == "object" && source != null) {
        return JSON.parse(JSON.stringify(source));
    }
}

//判断字符串是否为空

function isNullOrEmpty(str) {
    if (typeof str === "string") {
        return /^ *$/.test(str);
    }
    for (var key in str)
        return false;
    return typeof str !== "number" && typeof str !== "boolean";
}

function isLStr(str) //是否由数字、字母和下划线组成 字母开头
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    var reg = /^[a-zA-Z][a-zA-Z0-9_]*$/;
    if (reg.test(str))
        return true;
    else
        return false;
}

function isStr(str) //是否由数字、字母和下划线组成
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    return (str.replace(/\w/g, "").length == 0);
}

function isNumeric(str, symbol) //验证数值类型
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    if (symbol == undefined) {
        symbol = "";
    }
    switch (symbol) {
        case "+":        //正数
            return /(^\+?|^\d?)\d*\.?\d+$/.test(str);
        case "-":        //负数
            return /^-\d*\.?\d+$/.test(str);
        case "i":        //整数
            return /(^-?|^\+?|\d)\d+$/.test(str);
        case "+i":        //正整数
            return /(^\d+$)|(^\+?\d+$)/.test(str);
        case "-i":        //负整数
            return /^[-]\d+$/.test(str);
        case "f":        //浮点数
            return /(^-?|^\+?|^\d?)\d*\.\d+$/.test(str);
        case "+f":        //正浮点数
            return /(^\+?|^\d?)\d*\.\d+$/.test(str);
        case "-f":        //负浮点数
            return /^[-]\d*\.\d$/.test(str);
        default: //缺省,包括正负数，小数
            if (isNaN(str)) { return false; }
            else { return true; }
    }
}

function isInt(str) //正负整数
{
    if (str == undefined) { return false; }
    return /(^\d+$)|(^\+?\d+$)/.test(str) || /^[-]\d+$/.test(str);
}

function isUserName(str) //是否是用户名由数字、字母和下划线汉字组成,不能为纯数字，不能包含@,便于后台的
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    if (str.indexOf("@") > 0) //包含邮箱规则返回false
    {
        return false;
    }
    if (isNumeric(str)) { //全是数字则返回false
        return false;
    }
    var reg = /^[\u4e00-\u9fff\w]{2,15}$/;
    return reg.test(str);
}


function isChinese(str) //是否为汉字
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    return /^[^\x00-\xff]*$/.test(str);
}

function includeChinese(str) //是否包含汉字
{
    if (str == undefined) { return false; }
    return (str.length != str.replace(/[^\x00-\xff]/g, "**").length);
}

function isDate(str) //是否是日期
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    var reg1 = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;
    var reg2 = /^(\d{4})-(\d{1,2})-(\d{1,2})$/;
    if (!reg1.test(str) && !reg2.test(str)) {
        return false;
    }
    return true;
}

function isDateTime(str) //是否是日期+时间格式
{
    if (str == undefined) { return false; }
    if (str.toString().trim() == "") { return false; }
    var reg1 = /^(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/;
    var reg2 = /^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/;
    if (!reg1.test(str) && !reg2.test(str)) {
        return false;
    }
    return true;
}

function isMobile(mobile) //是否是手机号
{
    if (mobile == undefined) { return false; }
    var myreg = /^1[345678]\d{9}$/;
    if (!myreg.test(mobile)) { return false; }
    return true;
}

function isEmail(str) //是否是邮箱号
{
    if (str == undefined) { return false; }
    var pattern = /^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z0-9]{2,6}$/;
    var flag = pattern.test(str.toString().trim());
    if (!flag) {
        return false;
    }
    else {
        return true;
    }
}

function isIP(str) //是否是ip地址
{
    if (str == undefined) { return false; }
    var reSpaceCheck = /^(\d+)\.(\d+)\.(\d+)\.(\d+)$/;
    if (reSpaceCheck.test(str)) {
        str.match(reSpaceCheck);
        if (RegExp.$1 <= 255 && RegExp.$1 >= 0
            && RegExp.$2 <= 255 && RegExp.$2 >= 0
            && RegExp.$3 <= 255 && RegExp.$3 >= 0
            && RegExp.$4 <= 255 && RegExp.$4 >= 0) {
            return true;
        }
        else {
            return false;
        }
    }
    else {
        return false;
    }
}

function isImage(path,extFilter) //是否是图片地址
{
    if (isNullOrEmpty(path)) { return false; }
    if (extFilter==undefined)
    {
       extFilter = [".jpeg", ".gif", ".jpg", ".png", ".bmp"];
    }
    if (path.indexOf(".") > -1) {
        var p = path.lastIndexOf(".");
        var strPostfix = path.substring(p, path.length);
        strPostfix = strPostfix.toLowerCase();
        if (extFilter.indexOf(strPostfix) > -1) {
            return true;
        }
    }
    return false;
}

//判断是否是手机浏览器
function isMobileBrowser() {
    var userAgentInfo = navigator.userAgent;
    var Agents = ["Android", "iPhone",
        "SymbianOS", "Windows Phone",
        "iPad", "iPod"];
    var flag = true;
    for (var v = 0; v < Agents.length; v++) {
        if (userAgentInfo.indexOf(Agents[v]) > 0) {
            flag = true;
            break;
        }
    }
    return false;
}

function unique(str) //字符串用半角逗号隔开，过滤重复和空字符
{
    if (str == undefined) { return ""; }
    var arr = str.split(",");
    var result = [], hash = {};
    for (var i = 0, elem; (elem = arr[i]) != null; i++) {
        if (!hash[elem] && elem != "") {
            result.push(elem);
            hash[elem] = true;
        }
    }
    return result.join(",");
}

function urlEncode(str) //对字符串进行encodeURIComponent编码，方便传入后台
{
    if (str == undefined) { return ""; }
    return encodeURIComponent(str);
}

/*用浏览器内部转换器实现html转码*/
function htmlEncode(html) {
    //1.首先动态创建一个容器标签元素，如DIV
    var temp = document.createElement("div");
    //2.然后将要转换的字符串设置为这个元素的innerText(ie支持)或者textContent(火狐，google支持)
    (temp.textContent != undefined) ? (temp.textContent = html) : (temp.innerText = html);
    //3.最后返回这个元素的innerHTML，即得到经过HTML编码转换的字符串了
    var output = temp.innerHTML;
    temp = null;
    return output;
}
/*用浏览器内部转换器实现html解码*/
function htmlDecode(text) {
    //1.首先动态创建一个容器标签元素，如DIV
    var temp = document.createElement("div");
    //2.然后将要转换的字符串设置为这个元素的innerHTML(ie，火狐，google都支持)
    temp.innerHTML = text;
    //3.最后返回这个元素的innerText(ie支持)或者textContent(火狐，google支持)，即得到经过HTML解码的字符串了。
    var output = temp.innerText || temp.textContent;
    temp = null;
    return output;
}


function replaceAll(str, str1, str2) //替换所有对应的字符串，默认的replace只替换第一个
{
    if (str == undefined) { return ""; }
    while (str.indexOf(str1) >= 0) {
        str = str.replace(str1, str2);
    }
    return str;
}

function numericFormat(str) //格式化字符串，只保留数字
{
    if (str == undefined) { return ""; }
    return str.replace(/\D/g, "");
}

function StrFormat(str) //格式化字符串，只保留数字、字母部分
{
    if (str == undefined) { return ""; }
    return str.replace(/[\W]/g, '');
}

function ChineseFormat(str) //格式化字符串，只保留汉字
{
    if (str == undefined) { return ""; }
    return (str.replace(/[^\u4E00-\u9FA5]/g, ''));
}

function inputNumeric() //只能输入数字,onkeyup调用
{
    this.value = NumericFormat(this.value);
}

function inputChinese() //只能输入中文，,onkeyup调用
{
    this.value = ChineseFormat(this.value);
}
function inputStr()  //只能输入 字母，数字和 下划线,onkeyup调用
{
    this.value = StrFormat(this.value);
}


//html5本地储存，localStorage保存的值都是string类型,expires过期秒数，设置了值则会通过添加cookie来控制过期时间。
function setLocalStorage(name, value, expires) {
    if (expires == undefined) {
        expires = 0;//默认不过期，
    }
    if (typeof (value) == "object") {
        value = JSON.stringify(value);
    }
    if (isSupportLocalStorage()) {
        localStorage.setItem(name, value);
        if (expires > 0) {
            setCookie("_localStorageKey_" + name, "1", expires);
        }
    }
    else {
        setCookie(name, value, expires);
    }
}
//html5本地储存,hasSetexpires表示是否设置了过期时间
function getLocalStorage(name, hasSetExpires) {
    if (!hasSetExpires) {
        hasSetExpires = false;
    }
    if (isSupportLocalStorage()) {

        if (hasSetExpires) {
            var localStorageKey = getCookie("_localStorageKey_" + name);
            if (localStorageKey == "") {
                removeLocalStorage(name);
                return "";
            }
        }
        var strValue = localStorage.getItem(name);
        if (strValue == null) {
            strValue = "";
        }
        return strValue;
    }
    else {
        return getCookie(name);
    }
}

//删除本地储存
function removeLocalStorage(name) {
    if (isSupportLocalStorage()) {
        localStorage.removeItem(name);
        delCookie("_localStorageKey_" + name);
    }
    else {
        delCookie(name);
    }
}

function isSupportLocalStorage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    }
    catch (e) {
        return false;
    }
}

function setCookie(name, value, expires, path)//cookies设置,
{
    var argv = setCookie.arguments;
    var argc = setCookie.arguments.length;
    expires = (argc > 2) ? argv[2] : null;//第三个参数为过期时间
    path = (argc > 3) ? argv[3] : null;//第4个参数为路径,不设置则默认为当前路径
    if (expires != null) {
        if (typeof (expires) != "number") {
            expires = null;
        }
        var LargeExpDate = new Date();
        //LargeExpDate.setTime(LargeExpDate.getTime() + (expires*1000*60*60*24));//expires为过期天数
        LargeExpDate.setTime(LargeExpDate.getTime() + (expires * 1000)); //expires为过期秒数值
    }
    if (path != null) {
        if (path.trim() == "") {
            path = null;
        }
        path = ";path=" + path;
    }
    document.cookie = name + "=" + decodeURIComponent(value) + ((expires == null) ? "" : (";expires=" + LargeExpDate.toGMTString() + "")) + path;
}

function getCookie(name)//cookies读取
{
    var search = name + "="
    if (document.cookie.length > 0) {
        offset = document.cookie.indexOf(search)
        if (offset != -1) {
            offset += search.length
            end = document.cookie.indexOf(";", offset)
            if (end == -1) end = document.cookie.length
            return unescape(document.cookie.substring(offset, end))
        }
        else return ""
    }
    else {
        return "";
    }
}

function delCookie(name)//删除cookie
{
    var exp = new Date();
    exp.setTime(exp.getTime() - 1);
    var cval = getCookie(name);
    if (cval != null)
        document.cookie = name + "=" + cval + ";expires=" + exp.toGMTString();
}
function removeCookie(name)//删除cookie
{
    delCookie(name);
}

//监听事件
function addEvent(obj, event, fn) {
    if (obj.attachEvent) { //ie
        obj.attachEvent("on" + type, function () {
            fn.call(obj);
        })
    } else {
        obj.addEventListener(event, fn, false);
    }
}
//移除事件监听
function removeEvent(obj, event, fn) {
    if (obj.removeEventListener)
        obj.removeEventListener(event, fn, false);
    else if (obj.detachEvent)
        obj.detachEvent("on" + event, fn);
    else obj["on" + type] = null;
}

//阻止浏览器的默认行为 
function stopDefault() {
    var e = window.event || arguments.callee.caller.arguments[0];
    //阻止默认浏览器动作(W3C) 
    if (e && e.preventDefault) {
        e.preventDefault();
    }
    //IE中阻止函数器默认动作的方式 
    else {
        window.event.returnValue = false;
    } return false;
}

//停止冒泡
function stopBubble(e) {
    //var e = window.event || arguments.callee.caller.arguments[0];
    //如果提供了事件对象，则这是一个非IE浏览器 
    if (e && e.stopPropagation) {
        //因此它支持W3C的stopPropagation()方法 
        e.stopPropagation();
    }

    else {
        //否则，我们需要使用IE的方式来取消事件冒泡 
        window.event.cancelBubble = true;
    }
}

document.ready = (function () {
    var funcs = [];             //当获得事件时，要运行的函数
    var ready = false;          //当触发事件处理程序时,切换为true

    //当文档就绪时,调用事件处理程序
    function handler(e) {
        if (ready) return;       //确保事件处理程序只完整运行一次

        //如果发生onreadystatechange事件，但其状态不是complete的话,那么文档尚未准备好
        if (e.type === 'onreadystatechange' && document.readyState !== 'complete') {
            return;
        }

        //运行所有注册函数
        //注意每次都要计算funcs.length
        //以防这些函数的调用可能会导致注册更多的函数
        for (var i = 0; i < funcs.length; i++) {
            funcs[i].call(document);
        }
        //事件处理函数完整执行,切换ready状态, 并移除所有函数
        ready = true;
        funcs = null;
    }
    //为接收到的任何事件注册处理程序
    if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', handler, false);
        document.addEventListener('readystatechange', handler, false);            //IE9+
        window.addEventListener('load', handler, false);
    } else if (document.attachEvent) {
        document.attachEvent('onreadystatechange', handler);
        window.attachEvent('onload', handler);
    }
    //返回whenReady()函数
    return function whenReady(fn) {
        if (ready) { fn.call(document); }
        else { funcs.push(fn); }
    }
})();

//动态加载js
function loadScript(scriptPath, callBack) {
    var $head = document.getElementsByTagName("head")[0];
    var newScript = document.createElement('script');
    newScript.type = 'text/javascript';
    newScript.src = scriptPath;
    $head.appendChild(newScript);
    if (!/*@cc_on!@*/false) {
        newScript.onload = function () {
            callBack.call();
        }
    }
    else {
        newScript.onreadystatechange = function () {
            if (script.readystate == "loaded" || script.readState == 'complate') {
                callBack.call();
            }
        }
    }
}

//获取页面窗体尺寸
function clientSize() {
    if (window.innerHeight !== undefined) {
        return {
            "width": window.innerWidth,
            "height": window.innerHeight
        }
    } else if (document.compatMode === "CSS1Compat") {
        return {
            "width": document.documentElement.clientWidth,
            "height": document.documentElement.clientHeight
        }
    } else {
        return {
            "width": document.body.clientWidth,
            "height": document.body.clientHeight
        }
    }
}

function extend() {
    var length = arguments.length;
    var target = arguments[0] || {};
    if (typeof target != "object" && typeof target != "function") {
        target = {};
    }
    if (length == 1) {
        return target;
    }
    for (var i = 1; i < length; i++) {
        var source = arguments[i];
        for (var key in source) {
            // 使用for in会遍历数组所有的可枚举属性，包括原型。
            if (Object.prototype.hasOwnProperty.call(source, key)) {
                target[key] = source[key];
            }
        }
    }
    return target;
}

//生成guid
function GUID() {
    this.date = new Date();
    /* 判断是否初始化过，如果初始化过以下代码，则以下代码将不再执行，实际中只执行一次 */
    if (typeof this.newGUID != 'function') {
        /* 生成GUID码 */
        GUID.prototype.newGUID = function () {
            this.date = new Date();
            var guidStr = '';
            sexadecimalDate = this.hexadecimal(this.getGUIDDate(), 16);
            sexadecimalTime = this.hexadecimal(this.getGUIDTime(), 16);
            for (var i = 0; i < 9; i++) {
                guidStr += Math.floor(Math.random() * 16).toString(16);
            }
            guidStr += sexadecimalDate;
            guidStr += sexadecimalTime;
            while (guidStr.length < 32) {
                guidStr += Math.floor(Math.random() * 16).toString(16);
            }
            return this.formatGUID(guidStr);
        }

        /*
         * 功能：获取当前日期的GUID格式，即8位数的日期：19700101
         * 返回值：返回GUID日期格式的字条串
         */
        GUID.prototype.getGUIDDate = function () {
            return this.date.getFullYear() + this.addZero(this.date.getMonth() + 1) + this.addZero(this.date.getDay());
        }

        /*
         * 功能：获取当前时间的GUID格式，即8位数的时间，包括毫秒，毫秒为2位数：12300933
         * 返回值：返回GUID日期格式的字条串
         */
        GUID.prototype.getGUIDTime = function () {
            return this.addZero(this.date.getHours()) + this.addZero(this.date.getMinutes()) + this.addZero(this.date.getSeconds()) + this.addZero(parseInt(this.date.getMilliseconds() / 10));
        }

        /*
        * 功能: 为一位数的正整数前面添加0，如果是可以转成非NaN数字的字符串也可以实现
         * 参数: 参数表示准备再前面添加0的数字或可以转换成数字的字符串
         * 返回值: 如果符合条件，返回添加0后的字条串类型，否则返回自身的字符串
         */
        GUID.prototype.addZero = function (num) {
            if (Number(num).toString() != 'NaN' && num >= 0 && num < 10) {
                return '0' + Math.floor(num);
            } else {
                return num.toString();
            }
        }

        /* 
         * 功能：将y进制的数值，转换为x进制的数值
         * 参数：第1个参数表示欲转换的数值；第2个参数表示欲转换的进制；第3个参数可选，表示当前的进制数，如不写则为10
         * 返回值：返回转换后的字符串
         */
        GUID.prototype.hexadecimal = function (num, x, y) {
            if (y != undefined) {
                return parseInt(num.toString(), y).toString(x);
            } else {
                return parseInt(num.toString()).toString(x);
            }
        }

        /*
         * 功能：格式化32位的字符串为GUID模式的字符串
         * 参数：第1个参数表示32位的字符串
         * 返回值：标准GUID格式的字符串
         */
        GUID.prototype.formatGUID = function (guidStr) {
            var str1 = guidStr.slice(0, 8) + '-',
                str2 = guidStr.slice(8, 12) + '-',
                str3 = guidStr.slice(12, 16) + '-',
                str4 = guidStr.slice(16, 20) + '-',
                str5 = guidStr.slice(20);
            return str1 + str2 + str3 + str4 + str5;
        }
    }
}
var guidInstance;
function guid() {
    if (guidInstance == undefined) {
        guidInstance = new GUID();
    }
    return guidInstance.newGUID();
}


//转对象,支持字符串和对象参数
function objectParse(obj) {
    var thetype = typeof (obj);
    if (thetype == "undefined") {
        obj = {};
    }
    else if (thetype == "string") {
        try {
            var str = obj.trim();
            if (str.indexOf("{") == 0 || str.indexOf("[") == 0) {
                obj = eval("(" + str + ")");
            }
            else {
                obj = eval("(({" + str + "}))");
            }
        } catch (err) {
            alert(obj + "的ObjectParse转换失败，请检测格式!");
            throw new Error("ObjectParse Error!");
            //alert("错误信息: " + err.message );
            //obj = {};
        } finally {
        }
    }
    else if (thetype == "object") {
        return obj;
    }
    else {
        obj = {};
    }
    return obj;
}

//检测字符串是否是json格式
function isJson(str) {
    if (typeof str == 'string') {
        try {
            var obj = JSON.parse(str);
            if (typeof obj == 'object' && obj) {
                return true;
            } else {
                return false;
            }

        } catch (e) {
            return false;
        }
    }
}

function jsonParse(data) //转json，支持普通字符串，也支持js对象
{
    var theType = typeof (data);
    if (data == undefined) {
        return undefined;
    }
    if (theType == "object") {
        return data;
    }
    else if (theType == "string") {
        try {
            var str = data.trim();
            if (str == "{}") {
                return undefined;
            }
            if (str.indexOf("{") == 0 || str.indexOf("[") == 0) //json对象
            {
                data = JSON.parse(str);
            }
            else {
                return data;
            }
        } catch (err) {
            alert(data + "的jsonParse转换失败，请检测格式!");
            //alert("错误信息: " + err.message );
            data = undefined;
        } finally {
        }
    }
    return data;
}

//对象转字符串，普通js对象会转成json格式字符串
function jsonToString(obj) {
    if (typeof (obj) == "object") {
        obj = JSON.stringify(obj)
    }
    return obj;
}

function jsonFormat(s) {
    var newstr = "";
    for (var i = 0; i < s.length; i++) {
        c = s.charAt(i);
        switch (c) {
            case '\"':
                newstr += "\\\"";
                break;
            case '\\':
                newstr += "\\\\";
                break;
            case '/':
                newstr += "\\/";
                break;
            case '\b':
                newstr += "\\b";
                break;
            case '\f':
                newstr += "\\f";
                break;
            case '\n':
                newstr += "\\n";
                break;
            case '\r':
                newstr += "\\r";
                break;
            case '\t':
                newstr += "\\t";
                break;
            default:
                newstr += c;
        }
    }
    return newstr;
}

function multipleSelectToInput(jqObj, tojqObj) //把多选select的值转到input表单中
{
    if (jqObj.length == 0) { alert("对象不存在!"); return; }
    if (tojqObj.length == 0) { alert("目标对象不存在!"); return; }
    var vals = "";
    var options = jqObj.children("option");
    for (k = 0; k < options.length; k++) {
        var val = options.eq(k).val();
        if (vals != "") {
            vals += ",";
        }
        if (val != "") {
            vals += val;
        }
    }
    tojqObj.val(vals);
}


//获取时间差，根据类型
function getDateDiff(startTime, endTime, diffType) {
    startTime = startTime.replace(/-/g, "/");  //将xxxx-xx-xx的时间格式，转换为 xxxx/xx/xx的格式 
    endTime = endTime.replace(/-/g, "/");
    diffType = diffType.toLowerCase();  //将计算间隔类性字符转换为小写 
    var sTime = new Date(startTime); //开始时间 
    var eTime = new Date(endTime); //结束时间 
    var divNum = 1;  //作为除数的数字 
    switch (diffType) {
        case "second":
            divNum = 1000;
            break;
        case "minute":
            divNum = 1000 * 60;
            break;
        case "hour":
            divNum = 1000 * 3600;
            break;
        case "day":
            divNum = 1000 * 3600 * 24;
            break;
        default:
            break;
    }
    return parseInt((eTime.getTime() - sTime.getTime()) / parseInt(divNum));
}


//获取时间差,结果为，3天，5小时，40分钟，50秒
function getTimeDiff(ST, ET) {
    var rv;
    var ST = new Date(ST.replace(/-/g, '/'));			//开始时间转换为时间对象
    var ET = new Date(ET.replace(/-/g, '/'));			//结束时间转换为时间对象
    var DT = (ET - ST) / 1000;					//得到时间差，转换为秒

    var RD = Math.floor(DT / (60 * 60 * 24));			//得到天数
    var RH = Math.floor((DT % (60 * 60 * 24)) / (60 * 60));		//得到小时
    var RM = Math.floor(((DT % (60 * 60 * 24)) % (60 * 60)) / 60); 	//得到分钟
    var RS = (((DT % (60 * 60 * 24)) % (60 * 60)) % 60);		//得到秒
    RD = RD ? (RD + '天') : '';
    RH = RH ? (RH + '小时') : '';
    RM = RM ? (RM + '分钟') : '';
    RS = RS ? (RS + '秒') : '';
    rv = RD + RH + RM + RS;
    if (rv == "") {
        rv = "0秒";
    }
    return rv;
}

function dateToStr(datetime) {
    var year = datetime.getFullYear();
    var month = datetime.getMonth() + 1;//js从0开始取
    var date = datetime.getDate();
    var hour = datetime.getHours();
    var minutes = datetime.getMinutes();
    var second = datetime.getSeconds();
    if (month < 10) {
        month = "0" + month;
    }
    if (date < 10) {
        date = "0" + date;
    }
    if (hour < 10) {
        hour = "0" + hour;
    }
    if (minutes < 10) {
        minutes = "0" + minutes;
    }
    if (second < 10) {
        second = "0" + second;
    }
    var time = year + "-" + month + "-" + date + " " + hour + ":" + minutes + ":" + second; //2009-06-12 17:18:05
    return time;
}

//数字转中文
function moneyToChinese(money) {
    var cnNums = new Array("零", "壹", "贰", "叁", "肆", "伍", "陆", "柒", "捌", "玖"); //汉字的数字
    var cnIntRadice = new Array("", "拾", "佰", "仟"); //基本单位
    var cnIntUnits = new Array("", "万", "亿", "兆"); //对应整数部分扩展单位
    var cnDecUnits = new Array("角", "分", "毫", "厘"); //对应小数部分单位
    var cnInteger = "整"; //整数金额时后面跟的字符
    var cnIntLast = "元"; //整型完以后的单位
    var maxNum = 999999999999999.9999; //最大处理的数字

    var IntegerNum; //金额整数部分
    var DecimalNum; //金额小数部分
    var ChineseStr = ""; //输出的中文金额字符串
    var parts; //分离金额后用的数组，预定义

    if (money == "") {
        return "";
    }
    money = parseFloat(money);
    //alert(money);
    if (money >= maxNum) {
        this.alert('超出最大处理数字');
        return "";
    }
    if (money == 0) {
        ChineseStr = cnNums[0] + cnIntLast + cnInteger;
        //document.getElementById("show").value=ChineseStr;
        return ChineseStr;
    }
    money = money.toString(); //转换为字符串
    if (money.indexOf(".") == -1) {
        IntegerNum = money;
        DecimalNum = '';
    } else {
        parts = money.split(".");
        IntegerNum = parts[0];
        DecimalNum = parts[1].substr(0, 4);
    }
    if (parseInt(IntegerNum, 10) > 0) {//获取整型部分转换
        zeroCount = 0;
        IntLen = IntegerNum.length;
        for (i = 0; i < IntLen; i++) {
            n = IntegerNum.substr(i, 1);
            p = IntLen - i - 1;
            q = p / 4;
            m = p % 4;
            if (n == "0") {
                zeroCount++;
            } else {
                if (zeroCount > 0) {
                    ChineseStr += cnNums[0];
                }
                zeroCount = 0; //归零
                ChineseStr += cnNums[parseInt(n)] + cnIntRadice[m];
            }
            if (m == 0 && zeroCount < 4) {
                ChineseStr += cnIntUnits[q];
            }
        }
        ChineseStr += cnIntLast;
        //整型部分处理完毕
    }
    if (DecimalNum != '') {//小数部分
        decLen = DecimalNum.length;
        for (i = 0; i < decLen; i++) {
            n = DecimalNum.substr(i, 1);
            if (n != '0') {
                ChineseStr += cnNums[Number(n)] + cnDecUnits[i];
            }
        }
    }
    if (ChineseStr == '') {
        ChineseStr += cnNums[0] + cnIntLast + cnInteger;
    }
    else if (DecimalNum == '') {
        ChineseStr += cnInteger;
    }
    return ChineseStr;

}

/*获取url参数*/
function request(param, url) //获取url中参数
{
    if (param == undefined) {
        return "";
    }

    if (url == null) { url = location.href; }
    var paraString = url.substring(url.indexOf("?") + 1, url.length).split("&");
    var paraObj = {}
    var returnValue = "";
    for (var i = 0; i < paraString.length; i++) {
        var item = paraString[i].split("=");
        if (item.length == 2 && param.toLowerCase() == item[0].toLowerCase()) {
            if (returnValue != "") {
                returnValue += ",";
            }
            returnValue += item[1];
        }
    }
    if (typeof (returnValue) == "undefined") {
        return "";
    }
    else {
        return decodeURIComponent(returnValue);
    }
}

///获取url中参数,toObject:是否转为对象
function getQueryString(toObject) {
    //取得查询字符串并去掉开头的问号
    var qs = (location.search.length > 0 ? location.search.substring(1) : ""),
        //保存数据的对象
        args = {},
        params = [],
        //取得每一项
        items = qs.length ? qs.split("&") : [],
        item = null,
        name = null,
        value = null,
        //在 for 循环中使用
        i = 0,
        len = items.length;
    //逐个将每一项添加到 args 对象中
    for (i = 0; i < len; i++) {
        item = items[i].split("=");
        name = item[0];
        value = item[1];
        if (name.length) {
            if (toObject) {
                name = decodeURIComponent(name);
                value = decodeURIComponent(value);
                args[name] = value;
            }
            else {
                name = encodeURIComponent(name);
                value = encodeURIComponent(value);
                params.push(name + '=' + value);
            }
        }
    }
    if (toObject) {
        return args;
    }
    else {
        return params.join('&');
    }
}

function ajax(params, completecallBack) {
    var result;
    var defaultParams = {
        url: "",
        data: {},
        headers: {},
        type: "get",
        async: true,
        cache: false,
        ifModified: true,
        dataType: "json",
        traditional: true, //不进行深度序列化
        timeout: 0,//单位为秒，0代表永远不超时
        jsonp: "jsonpCallback",//服务端用于接收callback调用的function名的参数  
        contentType: "application/x-www-form-urlencoded;charset=utf-8", //可选：application/json;charset=utf-8,application/x-www-form-urlencoded;charset=utf-8
        beforeSend: function (xhr) { },
        success: function (data, xhr) { },
        error: function (xhr) { },
        complete: function (xhr) { }
    };
    var params = extend(defaultParams, params);
    var async = params.async;
    var type = (params.type || 'GET').toUpperCase();
    var cache = params.cache;
    var headers = params.headers;
    var error = params.error;
    if (Array.isArray(params.data)) {
        params.contentType = "application/json;charset=utf-8"; //data是数组的情况。
    }
    if (params.dataType == "jsonp") {
        jsonp(params);
    }
    else {
        json();
    }

    function json() { // ajax请求   
        var xhr = null;
        // 第一步：实例化XMLHttpRequest对象   
        if (window.XMLHttpRequest) {
            // code for IE7, Firefox, Opera, etc.
            xhr = new XMLHttpRequest();
        } else {
            xhr = new ActiveXObjcet("Microsoft.XMLHTTP");//IE6及其以下版本      
        };

        /*第二步：指定请求
        * open([请求方式],[URL],[boolean(同步或异步请求，默认false)]);
        * 调用open()方法并不会真正发送请求，而只是启动一个请求以备发送。
        * 
        * 第三步：设置请求头(post请求)
        * 第四步：发送请求
        * */
        xhr.withCredentials = true;
        if (params.type.toLowerCase() == "get") {
            // 三个参数：请求方式、请求地址(get方式时，传输数据是加在地址后的)、是否异步请求(同步请求的情况极少)；
            xhr.open(params.type, getUrl(), async);
            setHeader();
            xhr.send(null);
        } else {
            xhr.open(params.type, params.url, async);
            setHeader();
            var responseData = params.data;
            if (params.contentType.indexOf("json") > 0) {
                responseData = JSON.stringify(responseData);
            }
            else {
                responseData = formatParams(responseData);
            }
            xhr.send(responseData);
        }
        function setHeader() {
            for (var name in headers) {
                xhr.setRequestHeader(name, headers[name]);
            }
            if (!isCrossDomain())
            {
                xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");//注意跨域不能带上此参数，否则浏览器会拦截
            }
            if (!headers.hasOwnProperty("Content-Type") && params.type == "post") {
                xhr.setRequestHeader('Content-Type', params.contentType);
            }
        }

        // 第五步：监听响应，只要readyState的值变化，就会调用readystatechange事件 
        function stateChange() {
            //readyState属性表示请求/响应过程的当前活动阶段，4为完成，已经接收到全部响应数据
            if (xhr.readyState == 4) {
                var status = xhr.status;
                if (status >= 200 && status < 300) {    //status：响应的HTTP状态码，以2开头的都是成功
                    // 判断接受数据的内容类型  
                    var responseType = xhr.getResponseHeader("Content-type");
                    if (responseType == undefined) {
                        responseType = "";
                    }
                    if (responseType.indexOf('xml') !== -1 && xhr.responseXML) {
                        result = xhr.responseXML; //Document对象响应   
                    }
                    else if (responseType.indexOf('json') > 0) {
                        result = jsonParse(xhr.responseText); //返回json数据
                        if (httpCodeHandle(result)) {
                            completecallBack && completecallBack(xhr);
                            return;
                        }
                    }
                    else {
                        result = xhr.responseText; //字符串响应   
                    };
                    params.success && params.success(result, xhr);  // 成功回调函数  
                    params.complete && params.complete(xhr);
                    completecallBack && completecallBack(xhr);
                }
                else {
                    params.error && params.error(xhr);
                    params.complete && params.complete(xhr);
                    completecallBack && completecallBack(xhr);
                    throw "ajax请求：" + params.url + "出现" + xhr.status + "错误";
                }
            }
        };

        //对httpcode进行处理
        function httpCodeHandle(responseData) {
            var stopExecute = false;//是否终止调用方继续执行
            var httpCode = responseData.HttpCode;
            var msg = responseData.Msg;
            if (httpCode == undefined) {
                return;
            }
            if (!msg) {
                msg = "对不起，权限不足！";
            }
            var redirectUrl;
            var data = responseData.Data;
            if (data) {
                redirectUrl = data.RedirectUrl;//获取跳转地址
            }
            if (httpCode == 401 || httpCode == 403) //401：未登录或登录超时 ，403：没有权限操作
            {
                if (redirectUrl != undefined && params.type.toLowerCase() == "get") {
                    window.location.replace(redirectUrl);
                }
                else {
                    if (Vue && ELEMENT) {
                        new Vue({
                            created: function () {
                                vue.$notify({
                                    title: '提示',
                                    message: msg,
                                    type: 'error',
                                    position: 'top-left'
                                });
                            }
                        })
                    }
                    else {
                        alert(msg);
                    }
                }
                stopExecute = true;
            }
            return stopExecute;
        }

        if (params.timeout) {
            setTimeout(function () {
                xhr.abort();
            }, params.timeout * 1000);
        }
        if (async) {
            xhr.onreadystatechange = stateChange;
        }
        else {
            stateChange();
        }
        return result;
    }
    // jsonp请求   
    function jsonp(params) {
        //创建script标签并加入到页面中   
        var callbackName = params.jsonp;
        var head = document.getElementsByTagName('head')[0];
        // 设置传递给后台的回调参数名   
        params.data['callback'] = callbackName;
        var script = document.createElement('script');
        head.appendChild(script);
        //创建jsonp回调函数   
        window[callbackName] = function (responseData) {
            head.removeChild(script);
            clearTimeout(script.timer);
            window[callbackName] = null;
            result = responseData;
            if (isJson(result)) {
                result = jsonParse(responseData);
                //拦截httpcode请求
                if (httpCodeHandle(result)) {
                    completecallBack && completecallBack(xhr);
                    return;
                }
            }
            params.success && params.success(responseData);
            params.complete && params.complete(responseData);
            completecallBack && completecallBack(responseData);
        };
        //发送请求   
        script.src = getUrl();
        //为了得知此次请求是否成功，设置超时处理   
        if (params.timeout * 1000) {
            script.timer = setTimeout(function () {
                window[callbackName] = null;
                head.removeChild(script);
                params.error && params.error({
                    message: '超时'
                });
            }, params.timeout);
        }
    };
    //格式化参数
    function getUrl() {
        var url = params.url;
        var pararmString = formatParams(params.data);
        if (url.indexOf("?") > 0) {
            url += "&";
        }
        else {
            url += "?";
        }
        //console.log(url)
        return url + pararmString;
    }

    //对象转为参数连接
    function formatParams(data) {
        var arr = [];
        for (var name in data) {
            var val = data[name];
            if (val == null || val == undefined) {
                val = "";
            }
            arr.push(encodeURIComponent(name) + '=' + encodeURIComponent(val));
        };
        // 添加一个随机数参数，防止缓存   
        if (!cache) {
            arr.push('_r=' + random());
        }
        return arr.join('&');
    }
    // 获取随机数   
    function random() {
        return Math.floor(Math.random() * 10000 + 500);
    }
    //检测跨域
    function isCrossDomain()
    {
        var ajaxUrlUrl = params.url.toLowerCase();
        if (ajaxUrlUrl.indexOf("http://") < 0 && ajaxUrlUrl.indexOf("https://") < 0) {
            return false; //本地请求
        }
        var ajaxUrlDomainArray = ajaxUrlUrl.split("//");
        var localUrlArray = location.href.toLowerCase().split("//");
        if (ajaxUrlDomainArray[0] != localUrlArray[0]) //比较http协议
        {
            return true;
        }
        if (ajaxUrlDomainArray[1].split("/")[0] != localUrlArray[1].split("/")[0] ) //比较htost部分
        {
            return true;
        }
        return false;
    }
    return result;
}




if (!Array.prototype.map) //低版本ie兼容
    Array.prototype.map = function (fn, scope) {
        var result = [], ri = 0;
        for (var i = 0, n = this.length; i < n; i++) {
            if (i in this) {
                result[ri++] = fn.call(scope, this[i], i, this);
            }
        }
        return result;
    };

if (!Array.prototype.forEach) { //低版本ie兼容
    Array.prototype.forEach = function forEach(callback, thisArg) {
        var T, k;
        if (this == null) {
            throw new TypeError("this is null or not defined");
        }
        var O = Object(this);
        var len = O.length >>> 0;
        if (typeof callback !== "function") {
            throw new TypeError(callback + " is not a function");
        }
        if (arguments.length > 1) {
            T = thisArg;
        }
        k = 0;
        while (k < len) {

            var kValue;
            if (k in O) {
                kValue = O[k];
                callback.call(T, kValue, k, O);
            }
            k++;
        }
    };
}

//根据条件查找集合中符合条件的数据
if (!Array.prototype.find) {
    Array.prototype.find = function (predicate) {
        'use strict';
        if (this == null) {
            throw new TypeError("this is null or not defined");
        }
        if (typeof predicate !== 'function') {
            throw new TypeError('predicate must be a function');
        }
        var list = Object(this);
        var length = list.length >>> 0;
        var thisArg = arguments[1];
        var value;

        for (var i = 0; i < length; i++) {
            value = list[i];
            if (predicate.call(thisArg, value, i, list)) {
                return value;
            }
        }
        return undefined;
    };
}

//根据条件查找位置
if (!Array.prototype.findIndex) {
    Array.prototype.findIndex = function (predicate) {
        'use strict';
        if (this == null) {
            throw new TypeError("this is null or not defined");
        }
        if (typeof predicate !== 'function') {
            throw new TypeError('predicate must be a function');
        }
        var list = Object(this);
        var length = list.length >>> 0;
        var thisArg = arguments[1];
        var value;

        for (var i = 0; i < length; i++) {
            value = list[i];
            if (predicate.call(thisArg, value, i, list)) {
                return i;
            }
        }
        return -1;
    };
}


//根据条件移除
if (!Array.prototype.remove) {
    Array.prototype.remove = function (predicate) {
        if (this == null) {
            throw new TypeError("this is null or not defined");
        }
        if (typeof predicate !== 'function') {
            throw new TypeError('predicate must be a function');
        }
        var list = Object(this);
        var thisArg = arguments[1];
        var item;
        for (var i = 0; i < list.length; i++) {
            item = list[i];
            if (predicate.call(thisArg, item, i, list)) {
                list.splice(i, 1);
            }
        }
    };
}




