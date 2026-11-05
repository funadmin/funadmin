import { describe, expect, it } from 'vitest';
import { roleOptionsForRow, userRowAccess } from './superAdminAccess';

const superRow = { id: 1, isSuper: true };
const normalRow = { id: 2, isSuper: false };

describe('userRowAccess', () => {
  it('超管本人：可编辑资料和重置自己的密码，但不能删除、停用或改角色', () => {
    expect(userRowAccess(superRow, 1)).toEqual({
      editable: true, resettable: true, deletable: false, statusToggle: false, roleEditable: false, selectable: false
    });
  });

  it('其他管理员看到超管行：一律只读', () => {
    expect(userRowAccess(superRow, 2)).toEqual({
      editable: false, resettable: false, deletable: false, statusToggle: false, roleEditable: false, selectable: false
    });
  });

  it('普通管理员行：保持原有全部操作', () => {
    expect(userRowAccess(normalRow, 1)).toEqual({
      editable: true, resettable: true, deletable: true, statusToggle: true, roleEditable: true, selectable: true
    });
  });

  it('后端未返回 isSuper 时按普通行处理（兼容旧接口）', () => {
    expect(userRowAccess({ id: 3 }, 1).deletable).toBe(true);
  });
});

describe('roleOptionsForRow', () => {
  const ops = { id: 2, name: '测试运营', code: 'test_ops', level: 10, dataScope: 'all' as const, status: 1 as const, parentId: 0, parentRoleIds: [], departmentIds: [], permissionIds: [] };

  it('超管行：补上可分配列表里没有的超管角色，显示名称而不是 ID', () => {
    const options = roleOptionsForRow([ops], { isSuper: true, roleIds: [1] }, '超级管理员');
    expect(options.map((role) => [role.id, role.name])).toEqual([[1, '超级管理员'], [2, '测试运营']]);
  });

  it('普通行：不补任何角色，超管角色仍不可分配', () => {
    expect(roleOptionsForRow([ops], { isSuper: false, roleIds: [1] }, '超级管理员')).toEqual([ops]);
  });

  it('超管角色已在列表中时不重复添加', () => {
    const superRole = { ...ops, id: 1, name: '超级管理员' };
    expect(roleOptionsForRow([superRole, ops], { isSuper: true, roleIds: [1] }, 'x')).toHaveLength(2);
  });
});
