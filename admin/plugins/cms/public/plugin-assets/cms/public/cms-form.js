/* CMS 公开表单的渐进增强：首次聚焦或点按表单时才从限流接口获取验证码（失败时在表单级提示）、fetch 提交、419 时刷新令牌重试一次、错误显示到字段旁、成功后替换为提示。无框架依赖。 */
(function () {
  'use strict';

  var MEMBER_TOKEN_KEY = 'WEB_MEMBER_ACCESS_TOKEN';

  /** 会员只通过现有 Bearer 令牌识别：与会员前台共用 sessionStorage 中的访问令牌（JSON 字符串）。 */
  function memberToken() {
    try {
      var raw = window.sessionStorage.getItem(MEMBER_TOKEN_KEY);
      var token = raw ? JSON.parse(raw) : '';
      return typeof token === 'string' ? token : '';
    } catch (error) {
      return '';
    }
  }

  function request(url, options) {
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var token = memberToken();
    if (token) headers.Authorization = 'Bearer ' + token;
    options.headers = headers;
    options.credentials = 'same-origin';
    return fetch(url, options).then(function (response) {
      return Promise.resolve(response.json()).catch(function () { return {}; }).then(function (body) {
        return { status: response.status, body: body || {} };
      });
    });
  }

  function slots(form) {
    return Array.prototype.slice.call(form.querySelectorAll('[data-error], [data-form-error]'));
  }

  function fieldSlot(form, name) {
    var fields = form.querySelectorAll('[data-field]');
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].getAttribute('data-field') === name) return fields[i].querySelector('[data-error]');
    }
    return null;
  }

  function setText(slot, text) {
    if (!slot) return;
    slot.textContent = text;
    slot.hidden = text === '';
    var field = slot.closest ? slot.closest('[data-field]') : null;
    if (field) {
      if (text === '') field.removeAttribute('data-invalid');
      else field.setAttribute('data-invalid', '');
    }
  }

  function showErrors(form, errors) {
    var formSlot = form.querySelector('[data-form-error]');
    var orphan = [];
    Object.keys(errors).forEach(function (name) {
      var text = String(errors[name]);
      var slot = name === '_form' ? null : fieldSlot(form, name);
      if (slot) setText(slot, text);
      else orphan.push(text);
    });
    if (orphan.length) setText(formSlot, orphan.join(' '));
  }

  function captchaUrl(form, action) {
    var locale = form.querySelector('input[name="_locale"]');
    return action + '/captcha' + (locale && locale.value ? '?locale=' + encodeURIComponent(locale.value) : '');
  }

  /** 验证码失败的提示放在表单级错误槽：429 用接口返回的翻译文字，网络异常或响应无法解析时用 data-network-error。 */
  function captchaFailure(form, result) {
    var body = result && result.body ? result.body : {};
    var errors = body.data && body.data.errors;
    var text = result && result.status === 429 ? String((errors && errors._form) || body.msg || '') : '';
    return text || form.getAttribute('data-network-error') || '';
  }

  /**
   * 获取一张新验证码。成功时显示图片并清掉此前的验证码失败提示；失败时提示，刷新按钮保持可用以便重试。
   * consumed：旧验证码已被一次提交校验并删除，失败时一并清空，下次提交会先重新获取而不是再报“验证码错误”。
   */
  function refreshCaptcha(form, action, consumed) {
    var image = form.querySelector('[data-captcha-image]');
    var id = form.querySelector('input[name="_captcha_id"]');
    if (!image || !id) return Promise.resolve(false);
    if (form.cmsCaptchaPending) return form.cmsCaptchaPending;
    var answer = form.querySelector('input[name="_captcha"]');
    if (answer) answer.value = '';
    var formSlot = form.querySelector('[data-form-error]');
    var pending = request(captchaUrl(form, action), { method: 'GET' }).then(function (result) {
      var data = result.body && result.body.data;
      if (result.status === 200 && data && data.id && data.image) {
        image.setAttribute('src', data.image);
        image.hidden = false;
        id.value = data.id;
        if (form.cmsCaptchaError && formSlot && formSlot.textContent === form.cmsCaptchaError) setText(formSlot, '');
        form.cmsCaptchaError = '';
        return true;
      }
      return Promise.reject(result);
    }).catch(function (result) {
      var text = captchaFailure(form, result);
      form.cmsCaptchaError = text;
      setText(formSlot, text);
      if (consumed) {
        id.value = '';
        image.removeAttribute('src');
        image.hidden = true;
      }
      return false;
    }).then(function (loaded) {
      form.cmsCaptchaPending = null;
      return loaded;
    });
    form.cmsCaptchaPending = pending;
    return pending;
  }

  function prefill(form) {
    var params = new URLSearchParams(window.location.search);
    form.querySelectorAll('input[type="hidden"][data-from-query]').forEach(function (input) {
      var value = params.get(input.getAttribute('data-from-query'));
      if (value) input.value = value.slice(0, 500);
    });
    var source = form.querySelector('input[name="_source"]');
    if (source) source.value = window.location.href;
  }

  function enhance(form) {
    if (form.getAttribute('data-cms-form-ready') === '1') return;
    form.setAttribute('data-cms-form-ready', '1');
    var action = form.getAttribute('action') || '';
    var busy = false;
    prefill(form);

    var refresh = form.querySelector('[data-captcha-refresh]');
    var hasCaptcha = !!form.querySelector('[data-captcha-image]');
    var captchaRequested = false;
    if (refresh) {
      refresh.hidden = false;
      refresh.addEventListener('click', function () { captchaRequested = true; refreshCaptcha(form, action); });
    }
    // 页面渲染与脚本初始化都不签发验证码：每张验证码都占用按 IP 与按站点的签发额度，只在访客开始填写（首次聚焦或点按表单）时取第一张。
    // 首次尝试之后不再因聚焦重复请求，失败时由“换一张”或提交重试。
    function firstCaptcha() {
      if (!hasCaptcha || captchaRequested) return;
      captchaRequested = true;
      refreshCaptcha(form, action);
    }
    form.addEventListener('focusin', firstCaptcha);
    form.addEventListener('pointerdown', firstCaptcha);

    function renewToken() {
      var body = new FormData();
      var locale = form.querySelector('input[name="_locale"]');
      if (locale) body.append('_locale', locale.value);
      return request(action + '/token', { method: 'POST', body: body }).then(function (result) {
        var token = result.body && result.body.data && result.body.data.token;
        var input = form.querySelector('input[name="_token"]');
        if (result.status !== 200 || !token || !input) return false;
        input.value = token;
        return true;
      });
    }

    function send(retried) {
      return request(action, { method: 'POST', body: new FormData(form) }).then(function (result) {
        if (result.status !== 419 || retried) return result;
        return renewToken().then(function (renewed) { return renewed ? send(true) : result; });
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy) return;
      var captchaId = form.querySelector('input[name="_captcha_id"]');
      if (hasCaptcha && captchaId && captchaId.value === '') {
        // 还没有验证码（从未获取或上次失败）：先取一张让访客填写，本次不发送。
        captchaRequested = true;
        refreshCaptcha(form, action);
        return;
      }
      busy = true;
      form.setAttribute('aria-busy', 'true');
      var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
      buttons.forEach(function (button) { button.disabled = true; });
      slots(form).forEach(function (slot) { setText(slot, ''); });
      send(false).then(function (result) {
        var data = result.body.data || {};
        if (result.status === 200 && result.body.code === 0) {
          var success = document.createElement('div');
          success.className = 'cms-form-success';
          success.setAttribute('role', 'status');
          success.textContent = String(data.message || '');
          form.parentNode.replaceChild(success, form);
          return;
        }
        showErrors(form, data.errors && typeof data.errors === 'object' ? data.errors : { _form: result.body.msg || form.getAttribute('data-network-error') || '' });
        return refreshCaptcha(form, action, true);
      }).catch(function () {
        showErrors(form, { _form: form.getAttribute('data-network-error') || '' });
      }).then(function () {
        busy = false;
        form.removeAttribute('aria-busy');
        buttons.forEach(function (button) { button.disabled = false; });
      });
    });
  }

  function init() {
    document.querySelectorAll('form[data-cms-form]').forEach(enhance);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
