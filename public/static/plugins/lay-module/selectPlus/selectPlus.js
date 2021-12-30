/**
 * @ Name：selectPlus 加强版的选择框，可以多选、单选以及通过ulr导入数据
 * @ Author： YWang,FunAdmin修改版
 * @ License： MIT
 */

layui.define('form', function (exports) {
  var $ = layui.$,
    form = layui.form,
    hint = layui.hint()
    // 字符常量
    ,
    MOD_NAME = 'selectPlus',
    SELECT = 'layui-form-select',
    SELECTED = 'layui-form-selected'

    ,
    selectPlus = {
      index: layui.selectPlus ? layui.selectPlus.index : 0
        // 设置全局项
        ,
      set: function (options) {
          var that = this;
          that.config = $.extend({}, that.config, options);
          return that;
        }
        // 事件监听
        ,
      on: function (events, callback) {
        return layui.onevent.call(this, MOD_NAME, events, callback);
      }
    }
    // 操作当前实例
    ,
    thisIns = function () {
      var that = this,
        options = that.config;

      return {
        // 获取数据
        getChecked: function () {
          return that.getChecked.call(that);
        },
        // 配置数据
        config: options
      }
    }

    // 构造器
    ,
    Class = function (options) {
      var that = this;
      that.index = ++selectPlus.index;
      that.config = $.extend({}, that.config, selectPlus.config, options);
      that.render();
    };

  //默认配置
  Class.prototype.config = {
    type: 'checkbox',
    delimiter: ',',
    fielddelimiter: '  ---  ',
    placeholder: '请选择',
    data: [],
    name: 'name',//input 表单名
    field: ['id','title'],
    values: [],
    titles: [],
    url: '',
    method: 'get',
    where: '',
    search: '',
    contentType: '',
    headers: '',
    response: 'data',
    parseData: null,
    config: {
      checkedName: 'SELECTPLUS_CHECKED',
      indexName: 'SELECTPLUS_INDEX'
    },
    error: ''
  };
  //渲染视图
  Class.prototype.render = function () {
    var that = this,
        options = that.config;
    typeof (options.el) === 'string' ? options.el = $(options.el): options.el;
    options.reElem = $('<div class="layui-unselect layui-form-select">' +
      '<div class="layui-select-title">' +
      '<input type="hidden" name="'+options.name+'"><input type="text" placeholder="' + options.placeholder + '" value="" readonly="" class="layui-input layui-unselect">' +
      '<i class="layui-edge"></i>' +
      '</div>' +
      '<dl class="layui-anim layui-anim-upbit">' +
      '<dd lay-value="" class="layui-select-tips layui-hide">' + options.placeholder + '</dd>' +
      '</dl>' +
      '</div>');
    // 事件
    options.reElem.find('.layui-select-title').on('click', function (e) {
      !$(this).parent().hasClass(SELECTED) ? $(document).find('.' + SELECT).removeClass(SELECTED) : "";
      $(this).parent().toggleClass(SELECTED);
    });
    $(document).on('click', function (e) {
      ($(e.target).parents('.' + SELECT).length <= 0) && (options.reElem.hasClass(SELECTED)) ? options.reElem.removeClass(SELECTED): "";
    });


    !Array.isArray(options.values) ? options.values = [options.values] : "";

    // 查找 表单的 filter
    options.filter = options.el.parents('.layui-form').attr('lay-filter');

    options.el.html(options.reElem);


    if (options.url) { // 获取后端数据
      this.pullData();
    } else {
      that.renderData(); // 数据渲染
    }

    options.el.on('click', '.layui-select-title', function () {
      var $title = $(this),
        $dd0 = $title.next().find('dd').eq(0);
      if (!$dd0.hasClass('layui-hide')) {
        $dd0.addClass('layui-hide');
      }
      $title.find('input[name="+options.name+"]').val(options.values.join(options.delimiter));
      $title.find('input[type="text"]').val(options.titles.join(options.delimiter));
    })

  }

  Class.prototype.pullData = function () {
    var that = this,
      options = that.config;
    $.ajax({
      type: options.method || 'get',
      url: options.url,
      contentType: options.contentType,
      data: options.where || {},
      dataType: 'json',
      headers: options.headers || {},
      success: function (res) {
        //如果有数据解析的回调，则获得其返回的数据
        if (typeof options.parseData === 'function') {
          res = options.parseData(res) || res[options.response];
        }
        // 如果是数组，则覆盖options.data
        if (res.code>0 && Array.isArray(res.data)) {
          options.data = that.formatData(res.data);
          options.error = '';
          that.renderData();
        } else {
          options.error = '数据格式不对';
        }
      },
      error: function (e, m) {
        options.error = '数据接口请求异常：' + m;
      }
    });

  }

  // 格式化数据
  Class.prototype.formatData = function (data) {
    var that = this,
      options = that.config,
        field = options.field,
      values = options.values,
      checkedName = options.config.checkedName,
      indexName = options.config.indexName;
    layui.each(data, function (i, item) {
      if (typeof item !== 'object') {
        data[i] = {
          title: item
        }
      }
      data[i][indexName] = i;
      if (!data[i][checkedName]) data[i][checkedName] = false;
      layui.each(values, function (index, value) {
        if (data[i][field[0]] === value) {
          data[i][checkedName] = true;
        }
      })
    });
    values.splice(0);
    return data;
  }


  // 渲染数据
  Class.prototype.renderData = function (data) {
    var that = this,
      options = that.config,
      type = options.type,
      id = that.index,
      data = data ? that.formatData(data) : that.formatData(options.data);
    items = {
      // 多选
      checkbox: function (config, data, id) {
        var CLASSNAME = 'layui-form-checkbox',
          CHECKED = 'layui-form-checked',
          el = config.reElem.find('dl'),
          checkedName = config.config.checkedName,
          indexName = config.config.indexName,
          values = config.values,
          titles = config.titles,
          field = config.field,
          filter = config.filter,
          fielddelimiter = config.fielddelimiter,
          delimiter = config.delimiter,
          sum = 0;
        // 添加选项
        el.append($('<dd lay-value="全选"></dd>'));
        layui.each(data, function (i, item) {
          el.append($('<dd lay-value="' + item[field[0]] + '"></dd>'));
        })
        var allEle = el.find('dd').eq(1);
        // 添加多选框
        allEle.nextAll().each(function (index) {
          var $dd = $(this),
            item = data[index],
            title = titletemp = item[field[1]],
          value = item[field[0]];
          if (field.length > 0) {
            title = "";
            layui.each(field, function (i, n) {
              title += item[n];
              i < (field.length - 1) ? title += fielddelimiter : '';
            })
          }
          var checkbox = $('<input  type="checkbox" name="' + MOD_NAME + 'checkbox' + id + '"  yw-index="' + item[indexName] + '" lay-skin="primary" title="' + title + '" layui-value="' + value + '">');
          if (item[checkedName]) {
            checkbox.prop('checked', true);
            values.push(value);
            titles.push(titletemp);
            sum++;
          }
          $dd.html(checkbox);
        })
        var allcheckbox = $('<input  type="checkbox"  selectplus-all  lay-skin="primary" title="全选" layui-value="全选">');
        sum === data.length ? allcheckbox.prop('checked', true) : "";
        allEle.html(allcheckbox);
        allEle.parent().prev().find('input[name="'+config.name+'"]').val(values.join(delimiter));
        allEle.parent().prev().find('input[type="text"]').val(titles.join(delimiter));
        // 添加事件
        allEle.on('click', function (event) {
          var $all = $(this),
            checked = event.target.nodeName === 'DD' ? $all.find('.' + CLASSNAME).toggleClass(CHECKED).hasClass(CHECKED) : $all.find('input').prop('checked');

          // 禁止下拉框收回
          $all.parents('.' + SELECT).addClass(SELECTED);
          // 设置选中状态
          $all.find('input').prop('checked', checked);

          $all.nextAll().each(function () {
            var dd = $(this);
            checked ? dd.find('.' + CLASSNAME).addClass(CHECKED) : dd.find('.' + CLASSNAME).removeClass(CHECKED);
            dd.find('input').prop('checked', checked);
          })
          // 显示选中数据
          layui.event.call($all, MOD_NAME, 'checkbox' + '(' + MOD_NAME + ')' + that.index, {
            type: "checkbox",
            ele: $all,
            eleChecked: checked,
            isAll: checked
          });
          return false;
        })
        allEle.nextAll().on('click', function (event) {
          var $dd = $(this),
            checked = event.target.nodeName === 'DD' ? $dd.find('.' + CLASSNAME).toggleClass(CHECKED).hasClass(CHECKED) : $dd.find('input').prop('checked');
          // 禁止下拉框收回
          $dd.parents('.' + SELECT).addClass(SELECTED);
          // 设置选中状态
          $dd.find('input').prop('checked', checked);

          // 判断全选
          var $all = $dd.parents('dl').find('dd').eq(1),
            $dds = $all.nextAll(),
            sum = 0;
          $dds.each(function () {
            $(this).find('input').prop('checked') ? sum++ : '';
          })
          if (sum === $dds.length) {
            $all.find('input').prop('checked', true);
            $all.find('.' + CLASSNAME).addClass(CHECKED);
          } else {
            $all.find('input').prop('checked', false);
            $all.find('.' + CLASSNAME).removeClass(CHECKED);
          }
          // 显示选中数据
          layui.event.call($all, MOD_NAME, 'checkbox' + '(' + MOD_NAME + ')' + that.index, {
            type: "checkbox",
            ele: $dd,
            eleChecked: checked,
            isAll: (sum === $dds.length)
          });
          return false;
        })
        // 渲染多选框
        // el.next().find('dl').addClass('yw-selectPlus');
        form.render('checkbox', filter);
      },
      // 单选
      radio: function (config, data, id) {
        var CLASSNAME = 'layui-form-radio',
          CHECKED = 'layui-form-radioed',
          ICON = ['&#xe643;', '&#xe63f;'],
          CHECKED_ICON = 'layui-anim-scaleSpring',
          elID = config.el,
          el = config.reElem.find('dl'),
          checkedName = config.config.checkedName,
          indexName = config.config.indexName,
          checkedData = data.filter(function (item) {
            return item[checkedName] === true;
          }),
        values = config.values,
          titles = config.titles,
          field = config.field,
          filter = config.filter,
          fielddelimiter = config.fielddelimiter,
          delimiter = config.delimiter;
        // 添加选项
        layui.each(data, function (i, item) {
          el.append('<dd lay-value="' + item[field[0]] + '"></dd>');
        })
        form.render('select', options.filter);
        // 渲染单选框
        el.find('dd').eq(0).nextAll().each(function (index) {
          var $dd = $(this),
            item = data[index],
            title = titletemp = item[field[1]],
            value = item[field[0]];
          if (field.length > 0) {
            title = "";
            layui.each(field, function (i, n) {
              title += item[n];
              i < (field.length - 1) ? title += fielddelimiter : '';
            })
          }
          var dd = $('<input type="radio" name="' + MOD_NAME + 'radio' + id + '"  yw-index="' + item[indexName] + '" lay-skin="primary" title="' + title + '" layui-value="' + value + '">');
          if (checkedData.length > 0 && checkedData[0][indexName] === item[indexName]) {
            dd.prop('checked', true);
            values.push(value);
            titles.push(titletemp);
            $dd.parent().prev().find('input[name="'+config.name+'"]').val(values.join(delimiter))
            $dd.parent().prev().find('input[type="text"]').val(titles.join(delimiter))
          }
          $dd.html(dd);
        })
        el.next().find('dl').addClass('yw-selectPlus');
        form.render('radio', filter);
        // 事件
        el.find('dd').on('click', function (event) {
          var $dd = $(this);
          $dd.find('.' + CLASSNAME).addClass(CHECKED).find('i').addClass(CHECKED_ICON).html(ICON[0]);
          $dd.find('input').prop('checked', true);
          $dd.siblings().find('.' + CLASSNAME).removeClass(CHECKED).find('i').removeClass(CHECKED_ICON).html(ICON[1]);
          $dd.siblings().find('input').prop('checked', false);
          // 显示选中数据
          layui.event.call($dd, MOD_NAME, 'radio' + '(' + MOD_NAME + ')' + that.index, {
            type: "radio",
            ele: $dd,
            eleChecked: true,
            isAll: false
          });
        })
      }
    };

    // 选择时触发的事件
    layui.onevent.call(that, MOD_NAME, type + '(' + MOD_NAME + ')' + that.index, that.checked.bind(that));

    items[type] ? items[type](options, data, id) : hint.error('不支持的' + type + '表单渲染');

  }

  // 选中数据处理
  Class.prototype.checked = function (res) {
    var that = this,
      options = that.config,
      data = options.data,
      checkedName = options.config.checkedName,
      type = res.type,
      isAll = res.isAll,
      ele = res.ele,
      eleChecked = res.eleChecked,
      filter = options.el.attr('lay-filter');

    if (type === 'checkbox') {
      options.values = [];
      options.titles = [];
      ele.parents('dl').find('[type="checkbox"]').each(function (i) {
        var $dd = $(this),
          ywIndex = $dd.attr('yw-index'),
          checked = $dd.prop('checked');
        ywIndex ? data[ywIndex][checkedName] = checked : "";
        checked && ywIndex ? options.values.push($dd.attr('layui-value')) : "";
        checked && ywIndex ? options.titles.push(data[ywIndex][options.field[1]]) : "";
      })
      ele.parent().prev().find('input[type="hidden"]').val(options.values.join(options.delimiter));
      ele.parent().prev().find('input[type="text"]').val(options.titles.join(options.delimiter));

      layui.event.call(ele, MOD_NAME, MOD_NAME + '(' + filter + ')', {
        checked: eleChecked,
        isAll: isAll,
        values: options.values,
        titles: options.titles,
        checkedData: data.filter(function (item) {
          return item[checkedName] === true;
        }),
        ele: ele
      });
    } else if (type === 'radio') {
      var index = ele.find('input').attr('yw-index'),
        value = ele.find('input').attr('layui-value');
        title = data[index]['title'];
      options.values = [value];
      options.titles = [title.split(options.delimiter)[0]];
      ele.parent().prev().find('input[type="hidden"]').val(value);
      ele.parent().prev().find('input[type="text"]').val(title);

      layui.each(data, function (i, item) {
        item[checkedName] = false;
      })

      data[index][checkedName] = true;

      layui.event.call(ele, MOD_NAME, MOD_NAME + '(' + filter + ')', {
        value: value,
        title: title,
        checkedData: data[index],
        ele: ele
      });
    }

  }

  // 获取选中数据
  Class.prototype.getChecked = function () {
    var that = this,
      options = that.config,
      data = options.data,
      checkedName = options.config.checkedName;

    return {
      values: options.values,
      data: data.filter(function (item) {
        return item[checkedName] === true;
      })
    };
  }

  // 核心入口
  selectPlus.render = function (options) {
    var ins = new Class(options);
    return thisIns.call(ins);
  };
  exports('selectPlus', selectPlus);
})