/* CMS 公开表单的渐进增强：fetch 提交、419 时刷新令牌重试一次、错误显示到字段旁、成功后替换为提示。无框架依赖。 */
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

  function refreshCaptcha(form, action) {
    var image = form.querySelector('[data-captcha-image]');
    var id = form.querySelector('input[name="_captcha_id"]');
    if (!image || !id) return Promise.resolve();
    var answer = form.querySelector('input[name="_captcha"]');
    if (answer) answer.value = '';
    return request(captchaUrl(form, action), { method: 'GET' }).then(function (result) {
      var data = result.body && result.body.data;
      if (result.status === 200 && data && data.id && data.image) {
        image.setAttribute('src', data.image);
        id.value = data.id;
      }
    }).catch(function () {});
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
    if (refresh) refresh.addEventListener('click', function () { refreshCaptcha(form, action); });

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
      busy = true;
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
        return refreshCaptcha(form, action);
      }).catch(function () {
        showErrors(form, { _form: form.getAttribute('data-network-error') || '' });
      }).then(function () {
        busy = false;
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
