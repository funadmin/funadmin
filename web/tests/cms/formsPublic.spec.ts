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

it('loads the captcha from the throttled endpoint on init, then replaces it after a failed submission', async () => {
  document.querySelector('[data-form-error]')!.insertAdjacentHTML('beforebegin', '<div data-field="captcha"><input name="_captcha" value=""><img alt="验证码" data-captcha-image><button type="button" data-captcha-refresh>换一张</button><input type="hidden" name="_captcha_id" value=""><p data-error hidden></p></div>');
  fetchMock
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'first-id', image: 'data:image/png;base64,first' } } }))
    .mockReturnValueOnce(reply({ status: 422, body: { code: 422, data: { errors: { captcha: '验证码错误' } } } }))
    .mockReturnValueOnce(reply({ status: 200, body: { code: 0, data: { id: 'new-id', image: 'data:image/png;base64,new' } } }));
  new Function(source)();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('first-id'));
  expect(fetchMock.mock.calls[0][0]).toBe('/forms/contact/captcha?locale=zh-cn');
  expect(fetchMock.mock.calls[0][1].method).toBe('GET');
  expect(document.querySelector('[data-captcha-image]')!.getAttribute('src')).toBe('data:image/png;base64,first');
  document.querySelector<HTMLInputElement>('input[name="_captcha"]')!.value = 'abcd';
  submit();
  await vi.waitFor(() => expect(document.querySelector<HTMLInputElement>('input[name="_captcha_id"]')!.value).toBe('new-id'));
  expect((fetchMock.mock.calls[1][1].body as FormData).get('_captcha_id')).toBe('first-id');
  expect(fetchMock.mock.calls[2][0]).toBe('/forms/contact/captcha?locale=zh-cn');
  expect(fetchMock.mock.calls[2][1].method).toBe('GET');
  expect(document.querySelector('[data-captcha-image]')!.getAttribute('src')).toBe('data:image/png;base64,new');
  expect(document.querySelector<HTMLInputElement>('input[name="_captcha"]')!.value).toBe('');
  expect(document.querySelector('[data-field="captcha"] [data-error]')!.textContent).toBe('验证码错误');
});

it('forms without a captcha block do not request one on init', () => {
  new Function(source)();
  expect(fetchMock).not.toHaveBeenCalled();
});
