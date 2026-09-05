export interface EnvironmentCheck {
  key: string;
  required: boolean;
  passed: boolean;
}

export interface InstallForm {
  hostname: string;
  port: string;
  database: string;
  prefix: string;
  username: string;
  password: string;
  adminUserName: string;
  adminPassword: string;
  rePassword: string;
  email: string;
  appDebug: boolean;
}

/* 匿名访问默认去向：未安装引导到安装页，已安装引导到登录页 */
export const anonymousTarget = (installed: boolean): '/install' | '/login' => (installed ? '/login' : '/install');

export const canContinueInstallation = (checks: EnvironmentCheck[]) =>
  checks.length > 0 && checks.every((check) => !check.required || check.passed);

export const validateInstallForm = (form: InstallForm): string => {
  if (!form.hostname.trim() || !form.port.trim() || !form.database.trim()) return '数据库配置不完整';
  if (!form.adminUserName.trim()) return '管理员账号不能为空';
  if (!/^[A-Za-z0-9_$]{6,16}$/.test(form.adminPassword)) return '管理员密码必须为6-16位';
  if (!/[A-Za-z]/.test(form.adminPassword) || !/[0-9]/.test(form.adminPassword)) return '管理员密码必须同时包含字母和数字';
  if (form.adminPassword !== form.rePassword) return '两次输入密码不一致';
  if (form.email.length > 60) return '管理员邮箱不能超过60个字符';
  if (!/^\S+@\S+\.\S+$/.test(form.email)) return '请输入正确的邮箱';
  return '';
};
