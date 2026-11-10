import { describe, expect, it } from 'vitest';
import { runBackendTests } from './backend-runtime';

describe('Service 定向缓存失效', () => {
  it('后台权限写入保留会员注册限流与无关缓存', () => {
    const output = runBackendTests('CacheInvalidationScopeTest.php', 'test_admin_permission_write_does_not_reset_member_registration_throttle');
    expect(output).toContain('OK (1 test');
  });

  it('配置变更只失效受影响的 syscfg 键', () => {
    const output = runBackendTests('CacheInvalidationScopeTest.php', 'test_config_write_invalidates_only_its_own_syscfg_entries');
    expect(output).toContain('OK (1 test');
  });

  it('核心代码不能整体清空缓存', () => {
    const output = runBackendTests('CacheInvalidationScopeTest.php', 'test_core_code_never_flushes_the_whole_cache_store');
    expect(output).toContain('OK (1 test');
  });
});
