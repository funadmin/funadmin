// 通过 ego-browser nodejs 加载；仅在已创建的独占测试空间中验证。
const fs = await import('node:fs/promises');
const root = process.env.FIXTURE_ROOT;
const space = Number(process.env.FIXTURE_SPACE);
if (!root || !/^\/.*\/runtime\/browser-isolated-[a-f0-9]{12}$/.test(root) || !space) throw new Error('必须指定隔离根目录和已有 Browser 空间');
const state = JSON.parse(await fs.readFile(`${root}/fixture.json`, 'utf8'));
const credentials = JSON.parse(await fs.readFile(`${root}/credentials.json`, 'utf8'));
const task = await taskSpace(space);
const page = task.page('p1');
const evidence = [];
function expect(ok, check) {
  evidence.push({ check, passed: Boolean(ok) });
  if (!ok) throw new Error(check);
}
const api = async (path, options) => {
  const response = await page.fetch(`/console/${path}`, options);
  return { status: response.status, payload: JSON.parse(response.body) };
};
async function login(account) {
  await page.goto(`http://127.0.0.1:${state.frontendPort}/admin-web/#/login`);
  // 只清理本 fixture 独占端口的前端标记，不触碰其他站点或 Cookie。
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await page.fill('input[placeholder="账号 / 邮箱"]', account.username);
  await page.fill('input[placeholder="密码"]', account.password);
  await page.click('loc=role:button[name="登 录"]');
  await page.waitForURL('**/dashboard');
  await page.goto(`http://127.0.0.1:${state.frontendPort}/admin-web/#/development/business/mine`);
  await page.waitForFunction(() => document.body.innerText.includes('还没有业务模块'));
}
await login(credentials.super);
let me = await api('auth/me');
expect(me.payload.data.username === credentials.super.username, '真实超管登录会话');
expect(me.payload.data.permissions.includes('*'), '超管真实权限');
expect((await api('development/business/modules')).payload.code === 200, '超管真实业务列表');
await page.screenshot({ path: `${root}/super-ready.png` });
const csrf = await api('auth/csrf');
expect(csrf.payload.data.captchaEnabled === false, '隔离配置合法关闭验证码');
await api('auth/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf.payload.data.csrfToken } });
expect((await api('development/business/modules')).payload.code === 401, '未登录被真实中间件拒绝');
await login(credentials.reader);
await page.waitForFunction(() => document.body.innerText.includes('隔离测试reader'));
me = await api('auth/me');
expect(me.payload.data.username === credentials.reader.username, '普通用户真实表单登录');
expect(!me.payload.data.permissions.includes('*'), '普通用户无超管权限');
expect((await api('development/business/modules')).payload.code === 200, '普通角色允许读取模块');
const token = (await api('auth/csrf')).payload.data.csrfToken;
const forbidden = await api('development/business/modules/visual', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: '{}' });
expect(forbidden.payload.code === 403, '普通角色创建被真实 RBAC 拒绝');
const noCsrf = await api('auth/logout', { method: 'POST' });
expect(noCsrf.payload.code !== 200, 'CSRF 保护仍有效');
await page.screenshot({ path: `${root}/reader-ready.png` });
await fs.writeFile(`${root}/browser-evidence.json`, JSON.stringify({ verifiedAt: new Date().toISOString(), frontend: `http://127.0.0.1:${state.frontendPort}/admin-web/`, backend: `http://127.0.0.1:${state.backendPort}/console`, evidence }, null, 2), { mode: 0o600 });
console.log(JSON.stringify(evidence, null, 2));
console.log(await page.snapshot());
await task.finish({ keep: ['p1'] });
