(function () {
  'use strict';

  // 验证码点击刷新
  document.querySelectorAll('[data-captcha]').forEach(function (img) {
    img.addEventListener('click', function () {
      img.src = img.getAttribute('data-captcha') + '?t=' + Date.now();
    });
  });

  // 微信支付二维码：本地生成，不把支付链接发给第三方服务
  var qr = document.querySelector('[data-qrcode]');
  if (qr && typeof window.qrcode === 'function') {
    var code = window.qrcode(0, 'M');
    code.addData(qr.getAttribute('data-qrcode'));
    code.make();
    qr.innerHTML = code.createImgTag(6, 0, '微信支付二维码');
  }

  // 收银台轮询订单状态，支付完成后刷新页面
  var poll = document.querySelector('[data-order-status]');
  if (poll) {
    var url = poll.getAttribute('data-order-status');
    var attempts = 0;
    var timer = setInterval(function () {
      attempts += 1;
      if (attempts > 200) { clearInterval(timer); return; }
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
          if (data && data.status && data.status !== 'pending') {
            clearInterval(timer);
            window.location.reload();
          }
        })
        .catch(function () {});
    }, 3000);
  }

  // 复制按钮
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-copy'));
      if (!target || !navigator.clipboard) return;
      navigator.clipboard.writeText(target.textContent.trim()).then(function () {
        var text = button.textContent;
        button.textContent = '已复制';
        setTimeout(function () { button.textContent = text; }, 1500);
      });
    });
  });

  // 危险操作二次确认
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });
})();
