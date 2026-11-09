import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const source = readFileSync(resolve(process.cwd(), '../admin/plugins/cms/public/plugin-assets/cms/public/cms-form.js'), 'utf8');

const markup = `
<form data-cms-form method="post" enctype="multipart/form-data" action="/forms/contact" id="cms-form-0a1b2c3d" data-network-error="网络异常">
  <input type="hidden" name="_token" value="old-token">
  <input type="hidden" name="_locale" value="zh-cn">
  <input type="hidden" name="_source" value="/contact.html">
  <input type="hidden" name="channel" value="" data-from-query="channel">
  <div class="cms-form-field" data-field="name"><input name="name" value="张三"><p data-error hidden></p></div>
  <div class="cms-form-field" data-field="email"><input name="email" value="bad"><p data-error hidden></p></div>
  <div data-form-error hidden></div>
  <button type="submit">提交</button>
</form>`;

type Reply = { status: number; body: unknown };
const reply = ({ status, body }: Reply) => Promise.resolve({ status, ok: status >= 200 && status < 300, json: () => Promise.resolve(body) });
const submit = () => document.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

let fetchMock: ReturnType<typeof vi.fn>;
beforeEach(() => {
  document.body.innerHTML = markup;
  window.history.replaceState(null, '', '/contact.html?channel=ads');
  fetchMock = vi.fn();
  vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => {
  vi.unstubAllGlobals();
  window.sessionStorage.clear();
  document.body.innerHTML = '';
});

it('shows 422 errors beside matching fields as plain text and keeps the form', async () => {
  fetchMock.mockReturnValueOnce(reply({ status: 422, body: { code: 422, data: { errors: { email: '<b>邮箱无效</b>', _form: '提交过快' } } } }));
  new Function(source)();
  new Function(source)();
  const button = document.querySelector('button')!;
  submit();
  expect(button.disabled).toBe(true);
  expect(document.querySelector('form')!.getAttribute('aria-busy')).toBe('true');
  await vi.waitFor(() => expect(document.querySelector('[data-field="email"] [data-error]')!.textContent).toBe('<b>邮箱无效</b>'));
  expect(fetchMock).toHaveBeenCalledTimes(1);
  const [url, init] = fetchMock.mock.calls[0];
  expect(url).toBe('/forms/contact');
  expect(init.method).toBe('POST');
  expect(init.headers.Accept).toBe('application/json');
  expect(init.headers['X-Requested-With']).toBe('XMLHttpRequest');
  expect(init.headers.Authorization).toBeUndefined();
  expect((init.body as FormData).get('channel')).toBe('ads');
  expect((init.body as FormData).get('_source')).toBe(window.location.href);
  const slot = document.querySelector<HTMLElement>('[data-field="email"] [data-error]')!;
  expect(slot.hidden).toBe(false);
  expect(slot.querySelector('b')).toBeNull();
  expect(document.querySelector<HTMLElement>('[data-field="name"] [data-error]')!.hidden).toBe(true);
  const formError = document.querySelector<HTMLElement>('[data-form-error]')!;
  expect(formError.textContent).toBe('提交过快');
  expect(formError.hidden).toBe(false);
  expect(button.disabled).toBe(false);
  expect(document.querySelector('form')!.hasAttribute('aria-busy')).toBe(false);
  expect(document.querySelector('[data-field="email"]')!.hasAttribute('data-invalid')).toBe(true);
  expect(document.querySelector('[data-field="name"]')!.hasAttribute('data-invalid')).toBe(false);
  expect(document.querySelector('form')).not.toBeNull();
});

it('refreshes the token once on 419 and retries with the new token', async () => {
  window.sessionStorage.setItem('WEB_MEMBER_ACCESS_TOKEN', JSON.stringify('member-access'));
  fetchMock
    .mockReturnValueOnce(reply({ status: 419, body: { code: 419, data: { errors: { _form: '页面已过期' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { token: 'new-token' } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { message: '已收到' } } }));
  new Function(source)();
  submit();
  await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));
  expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(['/forms/contact', '/forms/contact/token', '/forms/contact']);
  expect((fetchMock.mock.calls[0][1].body as FormData).get('_token')).toBe('old-token');
  expect((fetchMock.mock.calls[1][1].body as FormData).get('_locale')).toBe('zh-cn');
  expect((fetchMock.mock.calls[2][1].body as FormData).get('_token')).toBe('new-token');
  expect(fetchMock.mock.calls[2][1].headers.Authorization).toBe('Bearer member-access');
});

it('stops after one retry when the token stays rejected', async () => {
  fetchMock
    .mockReturnValueOnce(reply({ status: 419, body: { code: 419, data: { errors: { _form: '页面已过期' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { token: 'new-token' } } }))
    .mockReturnValueOnce(reply({ status: 419, body: { code: 419, data: { errors: { _form: '页面已过期' } } } }));
  new Function(source)();
  submit();
  await vi.waitFor(() => expect(document.querySelector('[data-form-error]')!.textContent).toBe('页面已过期'));
  expect(fetchMock).toHaveBeenCalledTimes(3);
});

it('replaces the form with the success message', async () => {
  fetchMock.mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { message: '<i>已收到</i>' } } }));
  new Function(source)();
  submit();
  await vi.waitFor(() => expect(document.querySelector('form')).toBeNull());
  const success = document.querySelector('.cms-form-success')!;
  expect(success.textContent).toBe('<i>已收到</i>');
  expect(success.querySelector('i')).toBeNull();
});

const captchaBlock = '<div data-field="captcha"><input name="_captcha" value=""><img alt="验证码" data-captcha-image hidden><button type="button" data-captcha-refresh hidden>换一张</button><input type="hidden" name="_captcha_id" value=""><p data-error hidden></p></div>';
const addCaptcha = () => document.querySelector('[data-form-error]')!.insertAdjacentHTML('beforebegin', captchaBlock);
const focusCaptcha = () => document.querySelector('input[name="_captcha"]')!.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
const flush = () => new Promise((done) => setTimeout(done, 0));

it('does not spend the captcha budget on init and loads it once on the first focus', async () => {
  addCaptcha();
  fetchMock.mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'first-id', image: 'data:image/png;base64,first' } } }));
  new Function(source)();
  await flush();
  expect(fetchMock).not.toHaveBeenCalled();
  const image = document.querySelector<HTMLImageElement>('[data-captcha-image]')!;
  expect(image.hidden).toBe(true);
  expect(document.querySelector<HTMLButtonElement>('[data-captcha-refresh]')!.hidden).toBe(false);
  focusCaptcha();
  focusCaptcha();
  document.querySelector('input[name="name"]')!.dispatchEvent(new Event('pointerdown', { bubbles: true }));
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('first-id'));
  await flush();
  expect(fetchMock).toHaveBeenCalledTimes(1);
  expect(fetchMock.mock.calls[0][0]).toBe('/forms/contact/captcha?locale=zh-cn');
  expect(fetchMock.mock.calls[0][1].method).toBe('GET');
  expect(image.getAttribute('src')).toBe('data:image/png;base64,first');
  expect(image.hidden).toBe(false);
});

it('loads the captcha before a submit when it was never fetched, without sending the form', async () => {
  addCaptcha();
  fetchMock.mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'first-id', image: 'data:image/png;base64,first' } } }));
  new Function(source)();
  submit();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('first-id'));
  await flush();
  expect(fetchMock).toHaveBeenCalledTimes(1);
  expect(fetchMock.mock.calls[0][0]).toBe('/forms/contact/captcha?locale=zh-cn');
  expect(document.querySelector<HTMLButtonElement>('button[type="submit"]')!.disabled).toBe(false);
});

it('shows the 429 message in the form error slot and clears it after a successful retry', async () => {
  addCaptcha();
  fetchMock
    .mockReturnValueOnce(reply({ status: 429, body: { code: 429, msg: '提交过于频繁，请稍后再试', data: { errors: { _form: '提交过于频繁，请稍后再试' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'retry-id', image: 'data:image/png;base64,retry' } } }));
  new Function(source)();
  focusCaptcha();
  const formError = document.querySelector<HTMLElement>('[data-form-error]')!;
  await vi.waitFor(() => expect(formError.textContent).toBe('提交过于频繁，请稍后再试'));
  expect(formError.hidden).toBe(false);
  const image = document.querySelector<HTMLImageElement>('[data-captcha-image]')!;
  expect(image.hasAttribute('src')).toBe(false);
  expect(image.hidden).toBe(true);
  focusCaptcha();
  await flush();
  expect(fetchMock).toHaveBeenCalledTimes(1);
  const refresh = document.querySelector<HTMLButtonElement>('[data-captcha-refresh]')!;
  expect(refresh.disabled).toBe(false);
  refresh.click();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('retry-id'));
  expect(formError.textContent).toBe('');
  expect(formError.hidden).toBe(true);
  expect(image.hidden).toBe(false);
});

it('shows the network error when the captcha request fails or is not JSON', async () => {
  addCaptcha();
  fetchMock
    .mockReturnValueOnce(Promise.reject(new TypeError('offline')))
    .mockReturnValueOnce(Promise.resolve({ status: 200, ok: true, json: () => Promise.reject(new SyntaxError('html')) }));
  new Function(source)();
  focusCaptcha();
  const formError = document.querySelector<HTMLElement>('[data-form-error]')!;
  await vi.waitFor(() => expect(formError.textContent).toBe('网络异常'));
  formError.textContent = '';
  document.querySelector<HTMLButtonElement>('[data-captcha-refresh]')!.click();
  await vi.waitFor(() => expect(formError.textContent).toBe('网络异常'));
  expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('');
});

it('replaces the captcha after a failed submission', async () => {
  addCaptcha();
  fetchMock
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'first-id', image: 'data:image/png;base64,first' } } }))
    .mockReturnValueOnce(reply({ status: 422, body: { code: 422, data: { errors: { captcha: '验证码错误' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'new-id', image: 'data:image/png;base64,new' } } }));
  new Function(source)();
  focusCaptcha();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('first-id'));
  document.querySelector<HTMLInputElement>('input[name="_captcha"]')!.value = 'abcd';
  submit();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('new-id'));
  expect((fetchMock.mock.calls[1][1].body as FormData).get('_captcha_id')).toBe('first-id');
  expect(fetchMock.mock.calls[2][0]).toBe('/forms/contact/captcha?locale=zh-cn');
  expect(document.querySelector('[data-captcha-image]')!.getAttribute('src')).toBe('data:image/png;base64,new');
  expect(document.querySelector<HTMLInputElement>('input[name="_captcha"]')!.value).toBe('');
  expect(document.querySelector('[data-field="captcha"] [data-error]')!.textContent).toBe('验证码错误');
});

it('forms without a captcha block do not request one on init', () => {
  new Function(source)();
  expect(fetchMock).not.toHaveBeenCalled();
});

it('drops the consumed captcha when the refresh after a failed submission is throttled', async () => {
  addCaptcha();
  fetchMock
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'first-id', image: 'data:image/png;base64,first' } } }))
    .mockReturnValueOnce(reply({ status: 422, body: { code: 422, data: { errors: { captcha: '验证码错误' } } } }))
    .mockReturnValueOnce(reply({ status: 429, body: { code: 429, msg: '提交过于频繁，请稍后再试', data: { errors: { _form: '提交过于频繁，请稍后再试' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'next-id', image: 'data:image/png;base64,next' } } }));
  new Function(source)();
  focusCaptcha();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('first-id'));
  submit();
  await vi.waitFor(() => expect(document.querySelector('[data-form-error]')!.textContent).toBe('提交过于频繁，请稍后再试'));
  expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('');
  expect(document.querySelector<HTMLImageElement>('[data-captcha-image]')!.hidden).toBe(true);
  await vi.waitFor(() => expect(document.querySelector('form')!.hasAttribute('aria-busy')).toBe(false));
  submit();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('next-id'));
  expect(fetchMock).toHaveBeenCalledTimes(4);
  expect(fetchMock.mock.calls[3][0]).toBe('/forms/contact/captcha?locale=zh-cn');
});
