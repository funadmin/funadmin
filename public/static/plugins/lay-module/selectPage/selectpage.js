layui.define(["jquery"], function (exports) {
    var jQuery = layui.$;
    (function ($) {
        "use strict";
        var defaults = {
            data: undefined,
            lang: "cn",
            multiple: false,
            pagination: true,
            dropButton: true,
            listSize: 10,
            multipleControlbar: true,
            maxSelectLimit: 0,
            selectToCloseList: false,
            initRecord: undefined,
            dbTable: "",
            keyField: "id",
            showField: "name",
            selectFields: undefined,
            andOr: "AND",
            orderBy: false,
            pageSize: 10,
            params: undefined,
            formatItem: undefined,
            autoFillResult: false,
            autoSelectFirst: false,
            noResultClean: true,
            selectOnly: false,
            inputDelay: .5,
            isHtml: true,
            isTree: false,
            eSelect: undefined,
            eOpen: undefined,
            eAjaxSuccess: undefined,
            eTagRemove: undefined,
            eClear: undefined,
            method: "GET",
            verify: "",
            vertype: "tips"
        };
        var SelectPage = function (input, option) {
            this.setOption(option);
            this.setLanguage();
            this.setCssClass();
            this.setProp();
            this.setElem(input);
            this.setButtonAttrDefault();
            this.setInitRecord();
            this.eDropdownButton();
            this.eInput();
            this.eWhole()
        };
        SelectPage.version = "2.20";
        SelectPage.dataKey = "selectPageObject";
        SelectPage.prototype.setOption = function (option) {
            option.selectFields = option.selectFields || option.showField;
            option.andOr = option.andOr.toUpperCase();
            if (option.andOr !== "AND" && option.andOr !== "OR") option.andOr = "AND";
            var arr = ["selectFields"];
            for (var i = 0; i < arr.length; i++) {
                option[arr[i]] = this.strToArray(option[arr[i]])
            }
            if (option.orderBy !== false) option.orderBy = this.setOrderbyOption(option.orderBy, option.showField);
            if (option.multiple && !option.selectToCloseList) {
                option.autoFillResult = false;
                option.autoSelectFirst = false
            }
            if (!option.pagination) option.pageSize = 200;
            if ($.type(option.listSize) !== "number" || option.listSize < 0) option.listSize = 10;
            this.option = option
        };
        SelectPage.prototype.strToArray = function (str) {
            return str ? str.replace(/[\s　]+/g, "").split(",") : ""
        };
        SelectPage.prototype.setOrderbyOption = function (arg_order, arg_field) {
            var arr = [], orders = [];
            if (typeof arg_order === "object") {
                for (var i = 0; i < arg_order.length; i++) {
                    orders = $.trim(arg_order[i]).split(" ");
                    if (orders.length) {
                        arr.push(orders.length === 2 ? orders.concat() : [orders[0], "ASC"])
                    }
                }
            } else {
                orders = $.trim(arg_order).split(" ");
                arr[0] = orders.length === 2 ? orders.concat() : orders[0].toUpperCase().match(/^(ASC|DESC)$/i) ? [arg_field, orders[0].toUpperCase()] : [orders[0], "ASC"]
            }
            return arr
        };
        SelectPage.prototype.setLanguage = function () {
            var message, p = this.option;
            switch (p.lang) {
                case"de":
                    message = {
                        add_btn: "Hinzufügen-Button",
                        add_title: "Box hinzufügen",
                        del_btn: "Löschen-Button",
                        del_title: "Box löschen",
                        next: "Nächsten",
                        next_title: "Nächsten" + p.pageSize + " (Pfeil-rechts)",
                        prev: "Vorherigen",
                        prev_title: "Vorherigen" + p.pageSize + " (Pfeil-links)",
                        first_title: "Ersten (Umschalt + Pfeil-links)",
                        last_title: "Letzten (Umschalt + Pfeil-rechts)",
                        get_all_btn: "alle (Pfeil-runter)",
                        get_all_alt: "(Button)",
                        close_btn: "Schließen (Tab)",
                        close_alt: "(Button)",
                        loading: "lade...",
                        loading_alt: "(lade)",
                        page_info: "page_num von page_count",
                        select_ng: "Achtung: Bitte wählen Sie aus der Liste aus.",
                        select_ok: "OK : Richtig ausgewählt.",
                        not_found: "nicht gefunden",
                        ajax_error: "Bei der Verbindung zum Server ist ein Fehler aufgetreten.",
                        clear: "Löschen Sie den Inhalt",
                        select_all: "Wähle diese Seite",
                        unselect_all: "Diese Seite entfernen",
                        clear_all: "Alles löschen",
                        max_selected: "Sie können nur bis zu max_selected_limit Elemente auswählen"
                    };
                    break;
                case"en":
                    message = {
                        add_btn: "Add button",
                        add_title: "add a box",
                        del_btn: "Del button",
                        del_title: "delete a box",
                        next: "Next",
                        next_title: "Next" + p.pageSize + " (Right key)",
                        prev: "Prev",
                        prev_title: "Prev" + p.pageSize + " (Left key)",
                        first_title: "First (Shift + Left key)",
                        last_title: "Last (Shift + Right key)",
                        get_all_btn: "Get All (Down key)",
                        get_all_alt: "(button)",
                        close_btn: "Close (Tab key)",
                        close_alt: "(button)",
                        loading: "loading...",
                        loading_alt: "(loading)",
                        page_info: "Page page_num of page_count",
                        select_ng: "Attention : Please choose from among the list.",
                        select_ok: "OK : Correctly selected.",
                        not_found: "not found",
                        ajax_error: "An error occurred while connecting to server.",
                        clear: "Clear content",
                        select_all: "Select current page",
                        unselect_all: "Clear current page",
                        clear_all: "Clear all selected",
                        max_selected: "You can only select up to max_selected_limit items"
                    };
                    break;
                case"es":
                    message = {
                        add_btn: "Agregar boton",
                        add_title: "Agregar una opcion",
                        del_btn: "Borrar boton",
                        del_title: "Borrar una opcion",
                        next: "Siguiente",
                        next_title: "Proximas " + p.pageSize + " (tecla derecha)",
                        prev: "Anterior",
                        prev_title: "Anteriores " + p.pageSize + " (tecla izquierda)",
                        first_title: "Primera (Shift + Left)",
                        last_title: "Ultima (Shift + Right)",
                        get_all_btn: "Ver todos (tecla abajo)",
                        get_all_alt: "(boton)",
                        close_btn: "Cerrar (tecla TAB)",
                        close_alt: "(boton)",
                        loading: "Cargando...",
                        loading_alt: "(Cargando)",
                        page_info: "page_num de page_count",
                        select_ng: "Atencion: Elija una opcion de la lista.",
                        select_ok: "OK: Correctamente seleccionado.",
                        not_found: "no encuentre",
                        ajax_error: "Un error ocurrió mientras conectando al servidor.",
                        clear: "Borrar el contenido",
                        select_all: "Elija esta página",
                        unselect_all: "Borrar esta página",
                        clear_all: "Borrar todo marcado",
                        max_selected: "Solo puedes seleccionar hasta max_selected_limit elementos"
                    };
                    break;
                case"pt-br":
                    message = {
                        add_btn: "Adicionar botão",
                        add_title: "Adicionar uma caixa",
                        del_btn: "Apagar botão",
                        del_title: "Apagar uma caixa",
                        next: "Próxima",
                        next_title: "Próxima " + p.pageSize + " (tecla direita)",
                        prev: "Anterior",
                        prev_title: "Anterior " + p.pageSize + " (tecla esquerda)",
                        first_title: "Primeira (Shift + Left)",
                        last_title: "Última (Shift + Right)",
                        get_all_btn: "Ver todos (Seta para baixo)",
                        get_all_alt: "(botão)",
                        close_btn: "Fechar (tecla TAB)",
                        close_alt: "(botão)",
                        loading: "Carregando...",
                        loading_alt: "(Carregando)",
                        page_info: "page_num de page_count",
                        select_ng: "Atenção: Escolha uma opção da lista.",
                        select_ok: "OK: Selecionado Corretamente.",
                        not_found: "não encontrado",
                        ajax_error: "Um erro aconteceu enquanto conectando a servidor.",
                        clear: "Limpe o conteúdo",
                        select_all: "Selecione a página atual",
                        unselect_all: "Remova a página atual",
                        clear_all: "Limpar tudo",
                        max_selected: "Você só pode selecionar até max_selected_limit itens"
                    };
                    break;
                case"ja":
                    message = {
                        add_btn: "追加ボタン",
                        add_title: "入力ボックスを追加します",
                        del_btn: "削除ボタン",
                        del_title: "入力ボックスを削除します",
                        next: "次へ",
                        next_title: "次の" + p.pageSize + "件 (右キー)",
                        prev: "前へ",
                        prev_title: "前の" + p.pageSize + "件 (左キー)",
                        first_title: "最初のページへ (Shift + 左キー)",
                        last_title: "最後のページへ (Shift + 右キー)",
                        get_all_btn: "全件取得 (下キー)",
                        get_all_alt: "画像:ボタン",
                        close_btn: "閉じる (Tabキー)",
                        close_alt: "画像:ボタン",
                        loading: "読み込み中...",
                        loading_alt: "画像:読み込み中...",
                        page_info: "page_num 件 (全 page_count 件)",
                        select_ng: "注意 : リストの中から選択してください",
                        select_ok: "OK : 正しく選択されました。",
                        not_found: "(0 件)",
                        ajax_error: "サーバとの通信でエラーが発生しました。",
                        clear: "コンテンツをクリアする",
                        select_all: "当ページを選びます",
                        unselect_all: "移して当ページを割ります",
                        clear_all: "選択した項目をクリアする",
                        max_selected: "最多で max_selected_limit のプロジェクトを選ぶことしかできません"
                    };
                    break;
                case"cn":
                default:
                    message = {
                        add_btn: "添加按钮",
                        add_title: "添加区域",
                        del_btn: "删除按钮",
                        del_title: "删除区域",
                        next: "下一页",
                        next_title: "下" + p.pageSize + " (→)",
                        prev: "上一页",
                        prev_title: "上" + p.pageSize + " (←)",
                        first_title: "首页 (Shift + ←)",
                        last_title: "尾页 (Shift + →)",
                        get_all_btn: "获得全部 (↓)",
                        get_all_alt: "(按钮)",
                        close_btn: "关闭 (Tab键)",
                        close_alt: "(按钮)",
                        loading: "读取中...",
                        loading_alt: "(读取中)",
                        page_info: "第 page_num 页(共page_count页)",
                        select_ng: "请注意：请从列表中选择.",
                        select_ok: "OK : 已经选择.",
                        not_found: "无查询结果",
                        ajax_error: "连接到服务器时发生错误！",
                        clear: "清除内容",
                        select_all: "选择当前页项目",
                        unselect_all: "取消选择当前页项目",
                        clear_all: "清除全部已选择项目",
                        max_selected: "最多只能选择 max_selected_limit 个项目"
                    };
                    break
            }
            this.message = message
        };
        SelectPage.prototype.setCssClass = function () {
            var css_class = {
                container: "sp_container",
                container_open: "sp_container_open",
                re_area: "sp_result_area",
                result_open: "sp_result_area_open",
                control_box: "sp_control_box",
                element_box: "sp_element_box",
                navi: "sp_navi",
                results: "sp_results",
                re_off: "sp_results_off",
                select: "sp_over",
                select_ok: "sp_select_ok",
                select_ng: "sp_select_ng",
                selected: "sp_selected",
                input_off: "sp_input_off",
                message_box: "sp_message_box",
                disabled: "sp_disabled",
                button: "sp_button",
                caret_open: "sp_caret_open",
                btn_on: "sp_btn_on",
                btn_out: "sp_btn_out",
                input: "sp_input",
                clear_btn: "sp_clear_btn",
                align_right: "sp_align_right"
            };
            this.css_class = css_class
        };
        SelectPage.prototype.setProp = function () {
            this.prop = {
                disabled: false,
                current_page: 1,
                max_page: 1,
                is_loading: false,
                xhr: false,
                key_paging: false,
                key_select: false,
                prev_value: "",
                selected_text: "",
                last_input_time: undefined,
                init_set: false
            };
            this.template = {
                tag: {
                    content: '<li class="selected_tag" itemvalue="#item_value#">#item_text#<span class="tag_close"><i class="sp-iconfont if-close"></i></span></li>',
                    textKey: "#item_text#",
                    valueKey: "#item_value#"
                }, page: {current: "page_num", total: "page_count"}, msg: {maxSelectLimit: "max_selected_limit"}
            }
        };
        SelectPage.prototype.elementRealSize = function (element, method) {
            var defaults = {absolute: false, clone: false, includeMargin: false, display: "block"};
            var configs = defaults, $target = element.eq(0), fix, restore, tmp = [], style = "", $hidden;
            fix = function () {
                $hidden = $target.parents().addBack().filter(":hidden");
                style += "visibility: hidden !important; display: " + configs.display + " !important; ";
                if (configs.absolute === true) style += "position: absolute !important;";
                $hidden.each(function () {
                    var $this = $(this), thisStyle = $this.attr("style");
                    tmp.push(thisStyle);
                    $this.attr("style", thisStyle ? thisStyle + ";" + style : style)
                })
            };
            restore = function () {
                $hidden.each(function (i) {
                    var $this = $(this), _tmp = tmp[i];
                    if (_tmp === undefined) $this.removeAttr("style"); else $this.attr("style", _tmp)
                })
            };
            fix();
            var actual = /(outer)/.test(method) ? $target[method](configs.includeMargin) : $target[method]();
            restore();
            return actual
        };
        SelectPage.prototype.setElem = function (combo_input) {
            var elem = {}, p = this.option, css = this.css_class, msg = this.message, input = $(combo_input);
            var orgWidth = input.outerWidth();
            if (orgWidth <= 0) orgWidth = this.elementRealSize(input, "outerWidth");
            if (orgWidth < 150) orgWidth = 150;
            elem.combo_input = input.attr({autocomplete: "off"}).addClass(css.input).wrap("<div>");
            if (p.selectOnly) elem.combo_input.prop("readonly", true);
            elem.container = elem.combo_input.parent().addClass(css.container);
            if (elem.combo_input.prop("disabled")) {
                if (p.multiple) elem.container.addClass(css.disabled); else elem.combo_input.addClass(css.input_off)
            }
            elem.container.width(orgWidth);
            elem.button = $("<div>").addClass(css.button);
            elem.dropdown = $('<span class="sp_caret"></span>');
            elem.clear_btn = $("<div>").html($("<i>").addClass("sp-iconfont if-close")).addClass(css.clear_btn).attr("title", msg.clear);
            if (!p.dropButton) elem.clear_btn.addClass(css.align_right);
            elem.element_box = $("<ul>").addClass(css.element_box);
            if (p.multiple && p.multipleControlbar) elem.control = $("<div>").addClass(css.control_box);
            elem.result_area = $("<div>").addClass(css.re_area);
            if (p.pagination) elem.navi = $("<div>").addClass("sp_pagination").append("<ul>");
            elem.results = $("<ul>").addClass(css.results);
            var namePrefix = "_text", input_id = elem.combo_input.attr("id") || elem.combo_input.attr("name"),
                input_name = elem.combo_input.attr("name") || "selectPage", hidden_name = input_name,
                hidden_id = input_id;
            elem.hidden = $('<input type="text" class="sp_hidden layui-input layui-form-required-hidden" />').attr({
                name: hidden_name,
                id: hidden_id,
                "lay-verify": this.option.verify,
                "lay-vertype": this.option.vertype,
            }).val("");
            elem.combo_input.attr({name: input_name + namePrefix, id: input_id + namePrefix});
            elem.container.append(elem.hidden);
            if (p.dropButton) {
                elem.container.append(elem.button);
                elem.button.append(elem.dropdown)
            }
            $(document.body).append(elem.result_area);
            elem.result_area.append(elem.results);
            if (p.pagination) elem.result_area.append(elem.navi);
            if (p.multiple) {
                if (p.multipleControlbar) {
                    elem.control.append('<button type="button" class="btn btn-default sp_clear_all" ><i class="sp-iconfont if-clear"></i></button>');
                    elem.control.append('<button type="button" class="btn btn-default sp_unselect_all" ><i class="sp-iconfont if-unselect-all"></i></button>');
                    elem.control.append('<button type="button" class="btn btn-default sp_select_all" ><i class="sp-iconfont if-select-all"></i></button>');
                    elem.control_text = $("<p>");
                    elem.control.append(elem.control_text);
                    elem.result_area.prepend(elem.control)
                }
                elem.container.addClass("sp_container_combo");
                elem.combo_input.addClass("sp_combo_input").before(elem.element_box);
                var li = $("<li>").addClass("input_box");
                li.append(elem.combo_input);
                elem.element_box.append(li);
                if (elem.combo_input.attr("placeholder")) elem.combo_input.attr("placeholder_bak", elem.combo_input.attr("placeholder"))
            }
            this.elem = elem
        };
        SelectPage.prototype.setButtonAttrDefault = function () {
            if (this.option.dropButton) this.elem.button.attr("title", this.message.close_btn)
        };
        SelectPage.prototype.setInitRecord = function (refresh) {
            var self = this, p = self.option, el = self.elem, key = "";
            if ($.type(el.combo_input.data("init")) != "undefined") {
                p.initRecord = String(el.combo_input.data("init"))
            }
            if (!refresh && !p.initRecord && el.combo_input.val()) {
                p.initRecord = el.combo_input.val()
            }
            el.combo_input.val("");
            if (!refresh) {
                el.hidden.val(p.initRecord)
            }
            key = refresh && el.hidden.val() ? el.hidden.val() : p.initRecord;
            if (key) {
                if (typeof p.data === "object") {
                    var data = new Array;
                    var keyarr = key.split(",");
                    $.each(keyarr, function (index, row) {
                        for (var i = 0; i < p.data.length; i++) {
                            if (p.data[i][p.keyField] == row) {
                                data.push(p.data[i]);
                                break
                            }
                        }
                    });
                    if (!p.multiple && data.length > 1) data = [data[0]];
                    self.afterInit(self, data)
                } else {
                    $.ajax({
                        dataType: "json",
                        type: p.method,
                        url: p.data,
                        data: {
                            searchTable: p.dbTable,
                            searchKey: p.keyField,
                            searchValue: key,
                            orderBy: p.orderBy,
                            showField: p.showField,
                            keyField: p.keyField,
                            keyValue: key,
                            selectFields: p.selectFields,
                            isTree: p.isTree,
                            isHtml: p.isHtml
                        },
                        success: function (json) {
                            var d = null;
                            if (p.eAjaxSuccess && $.isFunction(p.eAjaxSuccess)) d = p.eAjaxSuccess(json);
                            self.afterInit(self, d.list)
                        },
                        error: function () {
                            self.ajaxErrorNotify(self)
                        },
                        complete: function (xhr) {
                            var token = xhr.getResponseHeader("__token__");
                            if (token) {
                                $("input[name='__token__']").val(token)
                            }
                        }
                    })
                }
            }
        };
        SelectPage.prototype.afterInit = function (self, data) {
            if (!data || $.isArray(data) && data.length === 0) return;
            if (!$.isArray(data)) data = [data];
            var p = self.option, css = self.css_class;
            var getText = function (row) {
                var text = row[p.showField];
                if (p.formatItem && $.isFunction(p.formatItem)) {
                    try {
                        text = p.formatItem(row)
                    } catch (e) {
                    }
                }
                return text
            };
            if (p.multiple) {
                self.prop.init_set = true;
                self.clearAll(self);
                $.each(data, function (i, row) {
                    var item = {text: getText(row), value: row[p.keyField]};
                    if (!self.isAlreadySelected(self, item)) self.addNewTag(self, row, item)
                });
                self.tagValuesSet(self);
                self.inputResize(self);
                self.prop.init_set = false
            } else {
                var row = data[0];
                self.elem.combo_input.val(getText(row));
                self.elem.hidden.val(row[p.keyField]);
                self.prop.prev_value = getText(row);
                self.prop.selected_text = getText(row);
                if (p.selectOnly) {
                    self.elem.combo_input.attr("title", self.message.select_ok).removeClass(css.select_ng).addClass(css.select_ok)
                }
                self.putClearButton()
            }
        };
        SelectPage.prototype.eDropdownButton = function () {
            var self = this;
            if (self.option.dropButton) {
                self.elem.button.mouseup(function (ev) {
                    ev.stopPropagation();
                    if (self.elem.result_area.is(":hidden") && !self.elem.combo_input.prop("disabled")) {
                        self.elem.combo_input.focus()
                    } else self.hideResults(self)
                })
            }
        };
        SelectPage.prototype.eInput = function () {
            var self = this, p = self.option, el = self.elem, msg = self.message;
            var showList = function () {
                self.prop.page_move = false;
                self.suggest(self);
                self.setCssFocusedInput(self)
            };
            el.combo_input.keyup(function (e) {
                self.processKey(self, e)
            }).keydown(function (e) {
                self.processControl(self, e)
            }).focus(function (e) {
                if (el.result_area.is(":hidden")) {
                    e.stopPropagation();
                    self.prop.first_show = true;
                    showList()
                }
            });
            el.container.on("click.SelectPage", "div." + self.css_class.clear_btn, function (e) {
                e.stopPropagation();
                if (!self.disabled(self)) {
                    self.clearAll(self, true);
                    if (p.eClear && $.isFunction(p.eClear)) p.eClear(self)
                }
            });
            el.result_area.on("mousedown.SelectPage", function (e) {
                e.stopPropagation()
            });
            if (p.multiple) {
                if (p.multipleControlbar) {
                    el.control.find(".sp_select_all").on("click.SelectPage", function () {
                        self.selectAllLine(self)
                    }).hover(function () {
                        el.control_text.html(msg.select_all)
                    }, function () {
                        el.control_text.html("")
                    });
                    el.control.find(".sp_unselect_all").on("click.SelectPage", function () {
                        self.unSelectAllLine(self)
                    }).hover(function () {
                        el.control_text.html(msg.unselect_all)
                    }, function () {
                        el.control_text.html("")
                    });
                    el.control.find(".sp_clear_all").on("click.SelectPage", function () {
                        self.clearAll(self, true)
                    }).hover(function () {
                        el.control_text.html(msg.clear_all)
                    }, function () {
                        el.control_text.html("")
                    })
                }
                el.element_box.on("click.SelectPage", function (e) {
                    var srcEl = e.target || e.srcElement;
                    if ($(srcEl).is("ul")) el.combo_input.focus()
                });
                el.element_box.on("click.SelectPage", "span.tag_close", function () {
                    var li = $(this).closest("li"), data = li.data("dataObj");
                    self.removeTag(self, li);
                    showList();
                    if (p.eTagRemove && $.isFunction(p.eTagRemove)) p.eTagRemove([data])
                });
                self.inputResize(self)
            }
        };
        SelectPage.prototype.eWhole = function () {
            var self = this, css = self.css_class;
            var cleanContent = function (obj) {
                obj.elem.combo_input.val("");
                if (!obj.option.multiple) obj.elem.hidden.val("");
                obj.prop.selected_text = ""
            };
            $(document.body).off("mousedown.selectPage").on("mousedown.selectPage", function (e) {
                var ele = e.target || e.srcElement;
                var sp = $(ele).closest("div." + css.container);
                $("div." + css.container + "." + css.container_open).each(function () {
                    if (this == sp[0]) return;
                    var $this = $(this), d = $this.find("input." + css.input).data(SelectPage.dataKey);
                    if (!d.elem.combo_input.val() && d.elem.hidden.val() && !d.option.multiple) {
                        d.prop.current_page = 1;
                        cleanContent(d);
                        d.hideResults(d);
                        return true
                    }
                    if (d.elem.results.find("li").not("." + css.message_box).length) {
                        if (d.option.autoFillResult) {
                            if (d.elem.hidden.val()) d.hideResults(d); else if (d.elem.results.find("li.sp_over").length) {
                                d.selectCurrentLine(d)
                            } else if (d.option.autoSelectFirst) {
                                d.nextLine(d);
                                d.selectCurrentLine(d)
                            } else d.hideResults(d)
                        } else d.hideResults(d)
                    } else {
                        if (d.option.noResultClean) cleanContent(d); else {
                            if (!d.option.multiple) d.elem.hidden.val("")
                        }
                        d.hideResults(d)
                    }
                })
            })
        };
        SelectPage.prototype.eResultList = function () {
            var self = this, css = this.css_class;
            self.elem.results.children("li").hover(function () {
                if (self.prop.key_select) {
                    self.prop.key_select = false;
                    return
                }
                if (!$(this).hasClass(css.selected) && !$(this).hasClass(css.message_box)) {
                    $(this).addClass(css.select);
                    self.setCssFocusedResults(self)
                }
            }, function () {
                $(this).removeClass(css.select)
            }).click(function (e) {
                if (self.prop.key_select) {
                    self.prop.key_select = false;
                    return
                }
                e.preventDefault();
                e.stopPropagation();
                if (!$(this).hasClass(css.selected)) self.selectCurrentLine(self)
            })
        };
        SelectPage.prototype.eScroll = function () {
            var css = this.css_class;
            $(window).on("scroll.SelectPage", function () {
                $("div." + css.container + "." + css.container_open).each(function () {
                    var $this = $(this), d = $this.find("input." + css.input).data(SelectPage.dataKey),
                        offset = d.elem.result_area.offset(), screenScrollTop = $(window).scrollTop(),
                        docHeight = $(document).height(), viewHeight = $(window).height(),
                        listHeight = d.elem.result_area.outerHeight(), listBottom = offset.top + listHeight,
                        hasOverflow = docHeight > viewHeight, down = d.elem.result_area.hasClass("shadowDown");
                    if (hasOverflow) {
                        if (down) {
                            if (listBottom > viewHeight + screenScrollTop) d.calcResultsSize(d)
                        } else {
                            if (offset.top < screenScrollTop) d.calcResultsSize(d)
                        }
                    }
                })
            })
        };
        SelectPage.prototype.ePaging = function () {
            var self = this;
            if (!self.option.pagination) return;
            self.elem.navi.find("li.csFirstPage").off("click").on("click", function (ev) {
                ev.preventDefault();
                self.firstPage(self)
            });
            self.elem.navi.find("li.csPreviousPage").off("click").on("click", function (ev) {
                ev.preventDefault();
                self.prevPage(self)
            });
            self.elem.navi.find("li.csNextPage").off("click").on("click", function (ev) {
                ev.preventDefault();
                self.nextPage(self)
            });
            self.elem.navi.find("li.csLastPage").off("click").on("click", function (ev) {
                ev.preventDefault();
                self.lastPage(self)
            })
        };
        SelectPage.prototype.ajaxErrorNotify = function (self) {
            self.showMessage(self.message.ajax_error)
        };
        SelectPage.prototype.showMessage = function (self, msg) {
            if (!msg) return;
            var msgLi = '<li class="' + self.css_class.message_box + '"><i class="sp-iconfont if-warning"></i> ' + msg + "</li>";
            self.elem.results.empty().append(msgLi).show();
            self.calcResultsSize(self);
            self.setOpenStatus(self, true);
            self.elem.control.hide();
            if (self.option.pagination) self.elem.navi.hide()
        };
        SelectPage.prototype.scrollWindow = function (self, enforce) {
            var current_result = self.getCurrentLine(self);
            var target_size;
            var target_top = current_result && !enforce ? current_result.offset().top : self.elem.container.offset().top;
            self.prop.size_li = self.elem.results.children("li:first").outerHeight();
            target_size = self.prop.size_li;
            var gap;
            var client_height = $(window).height();
            var scroll_top = $(window).scrollTop();
            var scroll_bottom = scroll_top + client_height - target_size;
            if (current_result.length) {
                if (target_top < scroll_top || target_size > client_height) {
                    gap = target_top - scroll_top
                } else if (target_top > scroll_bottom) {
                    gap = target_top - scroll_bottom
                } else return
            } else if (target_top < scroll_top) gap = target_top - scroll_top;
            window.scrollBy(0, gap)
        };
        SelectPage.prototype.setOpenStatus = function (self, status) {
            var el = self.elem, css = self.css_class;
            if (status) {
                el.container.addClass(css.container_open);
                el.result_area.addClass(css.result_open)
            } else {
                el.container.removeClass(css.container_open);
                el.result_area.removeClass(css.result_open)
            }
        };
        SelectPage.prototype.setCssFocusedInput = function (self) {
        };
        SelectPage.prototype.setCssFocusedResults = function (self) {
        };
        SelectPage.prototype.checkValue = function (self) {
            var now_value = self.elem.combo_input.val();
            if (now_value != self.prop.prev_value) {
                self.prop.prev_value = now_value;
                self.prop.first_show = false;
                if (self.option.selectOnly) self.setButtonAttrDefault();
                if (!self.option.multiple && !now_value) {
                    self.elem.combo_input.val("");
                    self.elem.hidden.val("");
                    self.elem.clear_btn.remove()
                }
                self.suggest(self)
            }
        };
        SelectPage.prototype.processKey = function (self, e) {
            if ($.inArray(e.keyCode, [37, 38, 39, 40, 27, 9, 13]) === -1) {
                if (e.keyCode != 16) self.setCssFocusedInput(self);
                self.inputResize(self);
                if ($.type(self.option.data) === "string") {
                    self.prop.last_input_time = e.timeStamp;
                    setTimeout(function () {
                        if (e.timeStamp - self.prop.last_input_time === 0) self.checkValue(self)
                    }, self.option.inputDelay * 1e3)
                } else {
                    self.checkValue(self)
                }
            }
        };
        SelectPage.prototype.processControl = function (self, e) {
            if ($.inArray(e.keyCode, [37, 38, 39, 40, 27, 9]) > -1 && self.elem.result_area.is(":visible") || $.inArray(e.keyCode, [13, 9]) > -1 && self.getCurrentLine(self)) {
                e.preventDefault();
                e.stopPropagation();
                e.cancelBubble = true;
                e.returnValue = false;
                switch (e.keyCode) {
                    case 37:
                        if (e.shiftKey) self.firstPage(self); else self.prevPage(self);
                        break;
                    case 38:
                        self.prop.key_select = true;
                        self.prevLine(self);
                        break;
                    case 39:
                        if (e.shiftKey) self.lastPage(self); else self.nextPage(self);
                        break;
                    case 40:
                        if (self.elem.results.children("li").length) {
                            self.prop.key_select = true;
                            self.nextLine(self)
                        } else self.suggest(self);
                        break;
                    case 9:
                        self.prop.key_paging = true;
                        self.selectCurrentLine(self);
                        break;
                    case 13:
                        self.selectCurrentLine(self);
                        break;
                    case 27:
                        self.prop.key_paging = true;
                        self.hideResults(self);
                        break
                }
            }
        };
        SelectPage.prototype.abortAjax = function (self) {
            if (self.prop.xhr) {
                self.prop.xhr.abort();
                self.prop.xhr = false
            }
        };
        SelectPage.prototype.suggest = function (self) {
            var q_word, val = $.trim(self.elem.combo_input.val());
            if (self.option.multiple) q_word = val; else {
                if (val && val === self.prop.selected_text) q_word = ""; else q_word = val
            }
            q_word = q_word.split(/[\s　]+/);
            if (self.option.eOpen && $.isFunction(self.option.eOpen)) self.option.eOpen.call(self);
            self.abortAjax(self);
            var which_page_num = self.prop.current_page || 1;
            if (typeof self.option.data == "object") self.searchForJson(self, q_word, which_page_num); else self.searchForDb(self, q_word, which_page_num)
        };
        SelectPage.prototype.setLoading = function (self) {
            if (self.elem.results.html() === "") {
                self.setOpenStatus(self, true)
            }
        };
        SelectPage.prototype.searchForDb = function (self, q_word, which_page_num) {
            var p = self.option;
            if (!p.eAjaxSuccess || !$.isFunction(p.eAjaxSuccess)) self.hideResults(self);
            var _paramsFunc = p.params, _params = {}, searchKey = p.selectFields;
            if (q_word.length && q_word[0] && q_word[0] !== self.prop.prev_value) which_page_num = 1;
            var _orgParams = {
                q_word: q_word,
                pageNumber: which_page_num,
                pageSize: p.pageSize,
                andOr: p.andOr,
                orderBy: p.orderBy,
                searchTable: p.dbTable,
                showField: self.option.showField,
                keyField: self.option.keyField,
                selectFields: self.option.selectFields,
                isTree: self.option.isTree,
                isHtml: self.option.isHtml
            };
            if (p.orderBy !== false) _orgParams.orderBy = p.orderBy;
            _orgParams[searchKey] = q_word[0];
            if (_paramsFunc && $.isFunction(_paramsFunc)) {
                var result = _paramsFunc.call(self);
                if (result && $.isPlainObject(result)) {
                    _params = $.extend({}, _orgParams, result)
                } else _params = _orgParams
            } else _params = _orgParams;
            self.prop.xhr = $.ajax({
                dataType: "json", url: p.data, type: p.method, data: _params, success: function (returnData) {
                    if (!returnData || !$.isPlainObject(returnData)) {
                        self.hideResults(self);
                        self.ajaxErrorNotify(self);
                        return
                    }
                    var data = {}, json = {};
                    try {
                        data = p.eAjaxSuccess(returnData);
                        json.originalResult = data.list;
                        json.cnt_whole = data.totalRow
                    } catch (e) {
                        self.showMessage(self, self.message.ajax_error);
                        return
                    }
                    json.candidate = [];
                    json.keyField = [];
                    if (typeof json.originalResult != "object") {
                        self.prop.xhr = null;
                        self.notFoundSearch(self);
                        return
                    }
                    json.cnt_page = json.originalResult.length;
                    for (var i = 0; i < json.cnt_page; i++) {
                        for (var key in json.originalResult[i]) {
                            if (key == p.keyField) {
                                json.keyField.push(json.originalResult[i][key])
                            }
                            if (key == p.showField) {
                                json.candidate.push(json.originalResult[i][key])
                            }
                        }
                    }
                    self.prepareResults(self, json, q_word, which_page_num)
                }, error: function (jqXHR, textStatus) {
                    if (textStatus != "abort") {
                        self.hideResults(self);
                        self.ajaxErrorNotify(self)
                    }
                }, complete: function (xhr, data) {
                    var __token__ = xhr.getResponseHeader("__token__");
                    $('input[name="__token__"]').val(__token__);
                    self.prop.xhr = null
                }
            })
        };
        SelectPage.prototype.searchForJson = function (self, q_word, which_page_num) {
            var p = self.option, matched = [], esc_q = [], sorted = [], json = {}, i = 0, arr_reg = [];
            do {
                esc_q[i] = q_word[i].replace(/\W/g, "\\$&").toString();
                arr_reg[i] = new RegExp(esc_q[i], "gi");
                i++
            } while (i < q_word.length);
            for (i = 0; i < p.data.length; i++) {
                var flag = false, row = p.data[i], itemText;
                for (var j = 0; j < arr_reg.length; j++) {
                    itemText = row[p.selectFields];
                    if (p.formatItem && $.isFunction(p.formatItem)) itemText = p.formatItem(row);
                    if (itemText.match(arr_reg[j])) {
                        flag = true;
                        if (p.andOr == "OR") break
                    } else {
                        flag = false;
                        if (p.andOr == "AND") break
                    }
                }
                if (flag) matched.push(row)
            }
            if (p.orderBy === false) sorted = matched.concat(); else {
                var reg1 = new RegExp("^" + esc_q[0] + "$", "gi"), reg2 = new RegExp("^" + esc_q[0], "gi"),
                    matched1 = [], matched2 = [], matched3 = [];
                for (i = 0; i < matched.length; i++) {
                    var orderField = p.orderBy[0][0];
                    var orderValue = String(matched[i][orderField]);
                    if (orderValue.match(reg1)) {
                        matched1.push(matched[i])
                    } else if (orderValue.match(reg2)) {
                        matched2.push(matched[i])
                    } else {
                        matched3.push(matched[i])
                    }
                }
                if (p.orderBy[0][1].match(/^asc$/i)) {
                    matched1 = self.sortAsc(self, matched1);
                    matched2 = self.sortAsc(self, matched2);
                    matched3 = self.sortAsc(self, matched3)
                } else {
                    matched1 = self.sortDesc(self, matched1);
                    matched2 = self.sortDesc(self, matched2);
                    matched3 = self.sortDesc(self, matched3)
                }
                sorted = sorted.concat(matched1).concat(matched2).concat(matched3)
            }
            json.cnt_whole = sorted.length;
            if (!self.prop.page_move) {
                if (!p.multiple) {
                    var currentValue = self.elem.hidden.val();
                    if ($.type(currentValue) !== "undefined" && $.trim(currentValue) !== "") {
                        var index = 0;
                        $.each(sorted, function (i, row) {
                            if (row[p.keyField] == currentValue) {
                                index = i + 1;
                                return false
                            }
                        });
                        which_page_num = Math.ceil(index / p.pageSize);
                        if (which_page_num < 1) which_page_num = 1;
                        self.prop.current_page = which_page_num
                    }
                }
            } else {
                if (sorted.length <= (which_page_num - 1) * p.pageSize) {
                    which_page_num = 1;
                    self.prop.current_page = 1
                }
            }
            var start = (which_page_num - 1) * p.pageSize, end = start + p.pageSize;
            json.originalResult = [];
            for (i = start; i < end; i++) {
                if (sorted[i] === undefined) break;
                json.originalResult.push(sorted[i]);
                for (var key in sorted[i]) {
                    if (key == p.keyField) {
                        if (json.keyField === undefined) json.keyField = [];
                        json.keyField.push(sorted[i][key])
                    }
                    if (key == p.showField) {
                        if (json.candidate === undefined) json.candidate = [];
                        json.candidate.push(sorted[i][key])
                    }
                }
            }
            if (json.candidate === undefined) json.candidate = [];
            json.cnt_page = json.candidate.length;
            self.prepareResults(self, json, q_word, which_page_num)
        };
        SelectPage.prototype.sortAsc = function (self, arr) {
            arr.sort(function (a, b) {
                var valA = a[self.option.orderBy[0][0]], valB = b[self.option.orderBy[0][0]];
                return $.type(valA) === "number" ? valA - valB : String(valA).localeCompare(String(valB))
            });
            return arr
        };
        SelectPage.prototype.sortDesc = function (self, arr) {
            arr.sort(function (a, b) {
                var valA = a[self.option.orderBy[0][0]], valB = b[self.option.orderBy[0][0]];
                return $.type(valA) === "number" ? valB - valA : String(valB).localeCompare(String(valA))
            });
            return arr
        };
        SelectPage.prototype.notFoundSearch = function (self) {
            self.elem.results.empty();
            self.calcResultsSize(self);
            self.setOpenStatus(self, true);
            self.setCssFocusedInput(self)
        };
        SelectPage.prototype.prepareResults = function (self, json, q_word, which_page_num) {
            if (self.option.pagination) self.setNavi(self, json.cnt_whole, json.cnt_page, which_page_num);
            if (!json.keyField) json.keyField = false;
            if (self.option.selectOnly && json.candidate.length === 1 && json.candidate[0] == q_word[0]) {
                self.elem.hidden.val(json.keyField[0]);
                this.setButtonAttrDefault()
            }
            var is_query = false;
            if (q_word && q_word.length && q_word[0]) is_query = true;
            self.displayResults(self, json, is_query)
        };
        SelectPage.prototype.setNavi = function (self, cnt_whole, cnt_page, page_num) {
            var msg = self.message;
            var buildPageNav = function (self, pagebar, page_num, last_page) {
                var updatePageInfo = function () {
                    var pageInfo = msg.page_info;
                    return pageInfo.replace(self.template.page.current, page_num).replace(self.template.page.total, last_page)
                };
                if (pagebar.find("li").length === 0) {
                    pagebar.hide().empty();
                    var iconFist = "sp-iconfont if-first", iconPrev = "sp-iconfont if-previous",
                        iconNext = "sp-iconfont if-next", iconLast = "sp-iconfont if-last";
                    pagebar.append('<li class="csFirstPage" title="' + msg.first_title + '" ><a href="javascript:void(0);"> <i class="' + iconFist + '"></i> </a></li>');
                    pagebar.append('<li class="csPreviousPage" title="' + msg.prev_title + '" ><a href="javascript:void(0);"><i class="' + iconPrev + '"></i></a></li>');
                    pagebar.append('<li class="pageInfoBox"><a href="javascript:void(0);"> ' + updatePageInfo() + " </a></li>");
                    pagebar.append('<li class="csNextPage" title="' + msg.next_title + '" ><a href="javascript:void(0);"><i class="' + iconNext + '"></i></a></li>');
                    pagebar.append('<li class="csLastPage" title="' + msg.last_title + '" ><a href="javascript:void(0);"> <i class="' + iconLast + '"></i> </a></li>');
                    pagebar.show()
                } else {
                    pagebar.find("li.pageInfoBox a").html(updatePageInfo())
                }
            };
            var pagebar = self.elem.navi.find("ul"), last_page = Math.ceil(cnt_whole / self.option.pageSize);
            if (last_page === 0) page_num = 0; else {
                if (last_page < page_num) page_num = last_page; else if (page_num === 0) page_num = 1
            }
            self.prop.current_page = page_num;
            self.prop.max_page = last_page;
            buildPageNav(self, pagebar, page_num, last_page);
            var dClass = "disabled", first = pagebar.find("li.csFirstPage"),
                previous = pagebar.find("li.csPreviousPage"), next = pagebar.find("li.csNextPage"),
                last = pagebar.find("li.csLastPage");
            if (page_num === 1 || page_num === 0) {
                if (!first.hasClass(dClass)) first.addClass(dClass);
                if (!previous.hasClass(dClass)) previous.addClass(dClass)
            } else {
                if (first.hasClass(dClass)) first.removeClass(dClass);
                if (previous.hasClass(dClass)) previous.removeClass(dClass)
            }
            if (page_num === last_page || last_page === 0) {
                if (!next.hasClass(dClass)) next.addClass(dClass);
                if (!last.hasClass(dClass)) last.addClass(dClass)
            } else {
                if (next.hasClass(dClass)) next.removeClass(dClass);
                if (last.hasClass(dClass)) last.removeClass(dClass)
            }
            if (last_page > 1) self.ePaging()
        };
        SelectPage.prototype.displayResults = function (self, json, is_query) {
            var p = self.option, el = self.elem;
            el.results.hide().empty();
            if (p.multiple && $.type(p.maxSelectLimit) === "number" && p.maxSelectLimit > 0) {
                var selectedSize = el.element_box.find("li.selected_tag").length;
                if (selectedSize > 0 && selectedSize >= p.maxSelectLimit) {
                    var msg = self.message.max_selected;
                    self.showMessage(self, msg.replace(self.template.msg.maxSelectLimit, p.maxSelectLimit));
                    return
                }
            }
            if (json.candidate.length) {
                var arr_candidate = json.candidate, arr_primary_key = json.keyField, keystr = el.hidden.val(),
                    keyArr = keystr ? keystr.split(",") : new Array, itemText = "";
                for (var i = 0; i < arr_candidate.length; i++) {
                    if (p.formatItem && $.isFunction(p.formatItem)) {
                        try {
                            itemText = p.formatItem(json.originalResult[i])
                        } catch (e) {
                            console.error("formatItem 内容格式化函数内容设置不正确！");
                            itemText = arr_candidate[i]
                        }
                    } else {
                        itemText = arr_candidate[i]
                    }
                    var list = $("<li>").html(itemText).attr({pkey: arr_primary_key[i]});
                    if (!p.formatItem) list.attr("title", itemText);
                    if ($.inArray(arr_primary_key[i].toString(), keyArr) !== -1) {
                        list.addClass(self.css_class.selected)
                    }
                    list.data("dataObj", json.originalResult[i]);
                    el.results.append(list)
                }
            } else {
                var li = '<li class="' + self.css_class.message_box + '"><i class="sp-iconfont if-warning"></i> ' + self.message.not_found + "</li>";
                el.results.append(li)
            }
            el.results.show();
            if (p.multiple && p.multipleControlbar) el.control.show();
            if (p.pagination) el.navi.show();
            self.calcResultsSize(self);
            self.setOpenStatus(self, true);
            self.eResultList();
            self.eScroll();
            if (is_query && json.candidate.length && p.autoSelectFirst) self.nextLine(self)
        };
        SelectPage.prototype.calcResultsSize = function (self) {
            var p = self.option, el = self.elem;
            var rePosition = function () {
                if (el.container.css("position") === "static") {
                    var st_offset = el.combo_input.offset();
                    el.result_area.css({
                        top: st_offset.top + el.combo_input.outerHeight() + "px",
                        left: st_offset.left + "px"
                    })
                } else {
                    var listHeight;
                    if (!p.pagination) {
                        var itemHeight = el.results.find("li:first").outerHeight(true);
                        listHeight = itemHeight * p.listSize;
                        el.results.css({"max-height": listHeight, "overflow-y": "auto"})
                    }
                    var docWidth = $(document).width(), docHeight = $(document).height(),
                        viewHeight = $(window).height(), offset = el.container.offset(),
                        screenScrollTop = $(window).scrollTop(), listWidth = el.result_area.outerWidth(),
                        defaultLeft = offset.left, inputHeight = el.container.outerHeight(),
                        left = offset.left + listWidth > docWidth ? defaultLeft - (listWidth - el.container.outerWidth()) : defaultLeft,
                        screenTop = offset.top, top = 0, dist = 5,
                        listBottom = screenTop + inputHeight + listHeight + dist, hasOverflow = docHeight > viewHeight;
                    listHeight = el.result_area.outerHeight();
                    if (screenTop - screenScrollTop - dist > listHeight && hasOverflow && listBottom > viewHeight + screenScrollTop || !hasOverflow && listBottom > viewHeight) {
                        top = offset.top - listHeight - dist;
                        el.result_area.removeClass("shadowUp shadowDown").addClass("shadowUp")
                    } else {
                        top = offset.top + (p.multiple ? el.container.outerHeight() : inputHeight);
                        el.result_area.removeClass("shadowUp shadowDown").addClass("shadowDown");
                        top += dist
                    }
                    return {top: top + "px", left: left + "px"}
                }
            };
            if (el.result_area.is(":visible")) {
                el.result_area.css(rePosition())
            } else {
                var pss = rePosition();
                el.result_area.css(pss).show(1, function () {
                    var repss = rePosition();
                    if (pss.top !== repss.top || pss.left !== repss.left) el.result_area.css(repss)
                })
            }
        };
        SelectPage.prototype.hideResults = function (self) {
            if (self.prop.key_paging) {
                self.scrollWindow(self, true);
                self.prop.key_paging = false
            }
            self.setCssFocusedInput(self);
            if (self.option.autoFillResult) {
            }
            self.elem.results.empty();
            self.elem.result_area.hide();
            self.setOpenStatus(self, false);
            $(window).off("scroll.SelectPage");
            self.abortAjax(self);
            self.setButtonAttrDefault()
        };
        SelectPage.prototype.disabled = function (self, disabled) {
            var el = self.elem;
            if ($.type(disabled) === "undefined") return el.combo_input.prop("disabled");
            if ($.type(disabled) === "boolean") {
                el.combo_input.prop("disabled", disabled);
                if (disabled) el.container.addClass(self.css_class.disabled); else el.container.removeClass(self.css_class.disabled)
            }
        };
        SelectPage.prototype.firstPage = function (self) {
            if (self.prop.current_page > 1) {
                self.prop.current_page = 1;
                self.prop.page_move = true;
                self.suggest(self)
            }
        };
        SelectPage.prototype.prevPage = function (self) {
            if (self.prop.current_page > 1) {
                self.prop.current_page--;
                self.prop.page_move = true;
                self.suggest(self)
            }
        };
        SelectPage.prototype.nextPage = function (self) {
            if (self.prop.current_page < self.prop.max_page) {
                self.prop.current_page++;
                self.prop.page_move = true;
                self.suggest(self)
            }
        };
        SelectPage.prototype.lastPage = function (self) {
            if (self.prop.current_page < self.prop.max_page) {
                self.prop.current_page = self.prop.max_page;
                self.prop.page_move = true;
                self.suggest(self)
            }
        };
        SelectPage.prototype.afterAction = function (self, reOpen) {
            self.inputResize(self);
            self.elem.combo_input.change();
            self.setCssFocusedInput(self);
            if (self.prop.init_set) return;
            if (self.option.multiple) {
                if (self.option.selectToCloseList) {
                    self.hideResults(self);
                    self.elem.combo_input.blur()
                }
                if (!self.option.selectToCloseList && reOpen) {
                    self.suggest(self);
                    self.elem.combo_input.focus()
                }
            } else {
                self.hideResults(self);
                self.elem.combo_input.blur()
            }
        };
        SelectPage.prototype.selectCurrentLine = function (self) {
            self.scrollWindow(self, true);
            var p = self.option, current = self.getCurrentLine(self);
            if (current) {
                var data = current.data("dataObj");
                if (p.multiple) {
                    self.elem.combo_input.val("");
                    var item = {text: current.text(), value: current.attr("pkey")};
                    if (!self.isAlreadySelected(self, item)) {
                        self.addNewTag(self, data, item);
                        self.tagValuesSet(self)
                    }
                } else {
                    self.elem.combo_input.val(current.text());
                    self.elem.combo_input.data("dataObj", data);
                    self.elem.hidden.val(current.attr("pkey"))
                }
                if (p.selectOnly) self.setButtonAttrDefault();
                if (p.eSelect && $.isFunction(p.eSelect)) p.eSelect(data, self);
                self.prop.prev_value = self.elem.combo_input.val();
                self.prop.selected_text = self.elem.combo_input.val();
                self.putClearButton()
            }
            self.afterAction(self, true)
        };
        SelectPage.prototype.putClearButton = function () {
            if (!this.option.multiple && !this.elem.combo_input.prop("disabled")) {
                this.elem.container.append(this.elem.clear_btn)
            }
        };
        SelectPage.prototype.selectAllLine = function (self) {
            var p = self.option, jsonarr = new Array;
            self.elem.results.find("li").each(function (i, row) {
                var $row = $(row), data = $row.data("dataObj");
                var item = {text: $row.text(), value: $row.attr("pkey")};
                if (!self.isAlreadySelected(self, item)) {
                    self.addNewTag(self, data, item);
                    self.tagValuesSet(self)
                }
                jsonarr.push(data);
                if ($.type(p.maxSelectLimit) === "number" && p.maxSelectLimit > 0 && p.maxSelectLimit === self.elem.element_box.find("li.selected_tag").length) {
                    return false
                }
            });
            if (p.eSelect && $.isFunction(p.eSelect)) p.eSelect(jsonarr, self);
            self.afterAction(self, true)
        };
        SelectPage.prototype.unSelectAllLine = function (self) {
            var p = self.option, ds = [];
            self.elem.results.find("li").each(function (i, row) {
                var key = $(row).attr("pkey");
                var tag = self.elem.element_box.find('li.selected_tag[itemvalue="' + key + '"]');
                if (tag.length) ds.push(tag.data("dataObj"));
                self.removeTag(self, tag)
            });
            self.afterAction(self, true);
            if (p.eTagRemove && $.isFunction(p.eTagRemove)) p.eTagRemove(ds)
        };
        SelectPage.prototype.clearAll = function (self, open) {
            var p = self.option, ds = [];
            if (p.multiple) {
                self.elem.element_box.find("li.selected_tag").each(function (i, row) {
                    ds.push($(row).data("dataObj"));
                    row.remove()
                });
                self.elem.element_box.find("li.selected_tag").remove()
            } else {
                $(self.elem.combo_input).removeData("dataObj")
            }
            self.reset(self);
            self.afterAction(self, open);
            if (p.multiple) {
                if (p.eTagRemove && $.isFunction(p.eTagRemove)) p.eTagRemove(ds)
            } else self.elem.clear_btn.remove()
        };
        SelectPage.prototype.reset = function (self) {
            self.elem.combo_input.val("");
            self.elem.hidden.val("");
            self.prop.prev_value = "";
            self.prop.selected_text = "";
            self.prop.current_page = 1
        };
        SelectPage.prototype.getCurrentLine = function (self) {
            if (self.elem.result_area.is(":hidden")) return false;
            var obj = self.elem.results.find("li." + self.css_class.select);
            return obj.length ? obj : false
        };
        SelectPage.prototype.isAlreadySelected = function (self, item) {
            var isExist = false;
            if (item.value) {
                var keys = self.elem.hidden.val();
                if (keys) {
                    var karr = keys.split(",");
                    if (karr && karr.length && $.inArray(item.value, karr) != -1) isExist = true
                }
            }
            return isExist
        };
        SelectPage.prototype.addNewTag = function (self, data, item) {
            if (!self.option.multiple || !data || !item) return;
            var tmp = self.template.tag.content, tag;
            tmp = tmp.replace(self.template.tag.textKey, item.text);
            tmp = tmp.replace(self.template.tag.valueKey, item.value);
            tag = $(tmp);
            tag.data("dataObj", data);
            if (self.elem.combo_input.prop("disabled")) {
                tag.find("span.tag_close").hide()
            }
            self.elem.combo_input.closest("li").before(tag)
        };
        SelectPage.prototype.removeTag = function (self, item) {
            var key = $(item).attr("itemvalue");
            var keys = self.elem.hidden.val();
            if ($.type(key) != "undefined" && keys) {
                var keyarr = keys.split(","), index = $.inArray(key.toString(), keyarr);
                if (index != -1) {
                    keyarr.splice(index, 1);
                    self.elem.hidden.val(keyarr.toString())
                }
            }
            $(item).remove();
            self.inputResize(self)
        };
        SelectPage.prototype.tagValuesSet = function (self) {
            if (!self.option.multiple) return;
            var tags = self.elem.element_box.find("li.selected_tag");
            if (tags && tags.length) {
                var result = new Array;
                $.each(tags, function (i, li) {
                    var v = $(li).attr("itemvalue");
                    if ($.type(v) !== "undefined") result.push(v)
                });
                if (result.length) {
                    self.elem.hidden.val(result.join(","))
                }
            }
        };
        SelectPage.prototype.inputResize = function (self) {
            if (!self.option.multiple) return;
            var inputLi = self.elem.combo_input.closest("li");
            var setDefaultSize = function (self, inputLi) {
                inputLi.removeClass("full_width");
                var minimumWidth = self.elem.combo_input.val().length + 1, width = minimumWidth * .75 + "em";
                self.elem.combo_input.css("width", width).removeAttr("placeholder").removeAttr("lay-verify")
            };
            if (self.elem.element_box.find("li.selected_tag").length === 0) {
                if (self.elem.combo_input.attr("placeholder_bak")) {
                    if (!inputLi.hasClass("full_width")) inputLi.addClass("full_width");
                    self.elem.combo_input.attr("placeholder", self.elem.combo_input.attr("placeholder_bak")).removeAttr("style").attr("lay-verify", self.option.verify)
                } else setDefaultSize(self, inputLi)
            } else setDefaultSize(self, inputLi)
        };
        SelectPage.prototype.nextLine = function (self) {
            var obj = self.getCurrentLine(self), idx;
            if (!obj) {
                idx = -1
            } else {
                idx = self.elem.results.children("li").index(obj);
                obj.removeClass(self.css_class.select)
            }
            idx++;
            if (idx < self.elem.results.children("li").length) {
                var next = self.elem.results.children("li").eq(idx);
                next.addClass(self.css_class.select);
                self.setCssFocusedResults(self)
            } else {
                self.setCssFocusedInput(self)
            }
            self.scrollWindow(self, false)
        };
        SelectPage.prototype.prevLine = function (self) {
            var obj = self.getCurrentLine(self), idx;
            if (!obj) idx = self.elem.results.children("li").length; else {
                idx = self.elem.results.children("li").index(obj);
                obj.removeClass(self.css_class.select)
            }
            idx--;
            if (idx > -1) {
                var prev = self.elem.results.children("li").eq(idx);
                prev.addClass(self.css_class.select);
                self.setCssFocusedResults(self)
            } else self.setCssFocusedInput(self);
            self.scrollWindow(self, false)
        };

        function Plugin(option) {
            return this.each(function () {
                var $this = $(this), data = $this.data(SelectPage.dataKey),
                    params = $.extend({}, defaults, $this.data(), data && data.option, typeof option === "object" && option);
                if (!data) $this.data(SelectPage.dataKey, data = new SelectPage(this, params))
            })
        }

        function getPlugin(obj) {
            return $(obj).closest("div.sp_container").find("input.sp_input")
        }

        function ClearSelected() {
            return this.each(function () {
                var $this = getPlugin(this), data = $this.data(SelectPage.dataKey);
                if (data) {
                    data.prop.init_set = true;
                    data.clearAll(data);
                    data.prop.init_set = false
                }
            })
        }

        function SelectedRefresh() {
            return this.each(function () {
                var $this = getPlugin(this), data = $this.data(SelectPage.dataKey);
                if (data && data.elem.hidden.val()) data.setInitRecord(true)
            })
        }

        function ModifyDataSource(data) {
            return this.each(function () {
                if (data && $.isArray(data)) {
                    var $this = getPlugin(this), plugin = $this.data(SelectPage.dataKey);
                    if (plugin) {
                        plugin.clearAll(plugin);
                        plugin.option.data = data
                    }
                }
            })
        }

        function PluginDisabled(disabled) {
            var status = false;
            this.each(function () {
                var $this = getPlugin(this), plugin = $this.data(SelectPage.dataKey);
                if (plugin) {
                    if ($.type(disabled) !== "undefined") plugin.disabled(plugin, disabled); else status = plugin.disabled(plugin)
                }
            });
            return status
        }

        function GetInputText() {
            var str = "";
            this.each(function () {
                var $this = getPlugin(this), data = $this.data(SelectPage.dataKey);
                if (data) {
                    if (data.option.multiple) {
                        var tags = [];
                        data.elem.element_box.find("li.selected_tag").each(function (i, tag) {
                            tags.push($(tag).text())
                        });
                        str += tags.toString()
                    } else str += data.elem.combo_input.val()
                }
            });
            return str
        }

        function GetSelectedData() {
            var results = [];
            this.each(function () {
                var $this = getPlugin(this), data = $this.data(SelectPage.dataKey);
                if (data) {
                    if (data.option.multiple) {
                        data.elem.element_box.find("li.selected_tag").each(function (i, tag) {
                            results.push($(tag).data("dataObj"))
                        })
                    } else {
                        var selected = data.elem.combo_input.data("dataObj");
                        if (selected) results.push(selected)
                    }
                }
            });
            return results
        }

        var old = $.fn.selectPage;
        $.fn.selectPage = Plugin;
        $.fn.selectPage.Constructor = SelectPage;
        $.fn.selectPageClear = ClearSelected;
        $.fn.selectPageRefresh = SelectedRefresh;
        $.fn.selectPageData = ModifyDataSource;
        $.fn.selectPageDisabled = PluginDisabled;
        $.fn.selectPageText = GetInputText;
        $.fn.selectPageSelectedData = GetSelectedData;
        $.fn.selectPage.noConflict = function () {
            $.fn.selectPage = old;
            return this
        }
    })(window.jQuery);
    layui.link("/static/plugins/lay-module/selectPage/selectpage.css?v=v2.20");
    exports("selectPage", $.fn.selectPage)
});