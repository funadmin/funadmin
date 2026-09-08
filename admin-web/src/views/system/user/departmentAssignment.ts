import type { DeptModel } from '@/api/system/dept';

export function filterDepartmentTree(tree: DeptModel[], excludedId?: number): DeptModel[] {
  return tree.flatMap((department) => {
    const children = filterDepartmentTree(department.children || [], excludedId);
    if (department.id === excludedId) return children;
    return [{ ...department, children }];
  });
}
