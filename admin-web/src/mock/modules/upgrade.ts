import type { UpgradeManifest, UpgradeTask } from '@/api/system/upgrade';
import type { MockRoute } from '../types';
import { ok } from '../types';

const manifest: UpgradeManifest = {
  manifestId: 'manifest-mock-1234567890',
  version: '2.0.0',
  changelog: '安全更新与 Vue 管理页面升级',
  expiresAt: '2026-09-06 05:35:00'
};

const initialTasks = (): UpgradeTask[] => [{
  id: 1,
  from_version: '1.9.0',
  to_version: '2.0.0',
  status: 'failed',
  stage: 'restore',
  progress: 85,
  backup_path: '/var/backups/funadmin/task-1',
  error_message: '部署失败，已保留可恢复备份',
  created_at: '2026-09-06 05:00:00'
}];

const tasks = initialTasks();
let nextId = 2;

function completedTask(toVersion: string): UpgradeTask {
  const task: UpgradeTask = {
    id: nextId++,
    from_version: '1.9.0',
    to_version: toVersion,
    status: 'success',
    stage: 'complete',
    progress: 100,
    backup_path: `/var/backups/funadmin/task-${nextId - 1}`,
    error_message: '',
    created_at: '2026-09-06 05:30:00'
  };
  tasks.unshift(task);
  return task;
}

export const upgradeMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/upgrade/status',
    handler: () => ok({ currentVersion: '1.9.0', tasks })
  },
  {
    method: 'GET',
    url: '/system/upgrade/check',
    handler: () => ok(manifest)
  },
  {
    method: 'POST',
    url: '/system/upgrade/execute',
    handler: ({ body }) => body.manifestId === manifest.manifestId
      ? ok(completedTask(manifest.version))
      : { code: 409, msg: '升级 manifest 不存在、已过期或已使用', data: null }
  },
  {
    method: 'POST',
    url: '/system/upgrade/upload',
    handler: () => ok(completedTask('2.0.0'))
  },
  {
    method: 'POST',
    url: /^\/system\/upgrade\/(\d+)\/restore$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const task = tasks.find((item) => item.id === Number(pathParams.id));
      if (task) {
        task.status = 'restored';
        task.stage = 'restored';
        task.progress = 100;
        task.error_message = '';
      }
      return ok(task || null);
    }
  }
];
