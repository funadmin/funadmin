import type { BusinessGenerationFile } from '@/api/development/business';
import { FILE_STATUS_META, type GenerationFileStatus } from '../constants';
import type { CodeLanguage } from './highlighter';

export type PreviewFileGroup = 'backend' | 'database' | 'frontend' | 'lang' | 'test' | 'other';

export const PREVIEW_FILE_GROUPS: readonly PreviewFileGroup[] = ['backend', 'database', 'frontend', 'lang', 'test', 'other'];

/** 插件目标的文件都在 `plugins/{code}/` 下，去掉该前缀后与核心目标共用同一套规则。 */
function projectRelative(path: string): string {
  return path.replace(/\\/g, '/').replace(/^\.\//, '').replace(/^plugins\/[^/]+\//, '');
}

/** 按项目相对路径前缀判断文件类别；只用于展示分组，不参与任何生成决策。 */
export function previewFileGroup(path: string): PreviewFileGroup {
  const relative = projectRelative(path);
  if (/^(?:web\/)?tests\//.test(relative)) return 'test';
  if (/^app\/(?:[^/]+\/)*lang\//.test(relative)) return 'lang';
  if (/^database\//.test(relative) || /\.sql$/i.test(relative)) return 'database';
  if (/^web\//.test(relative)) return 'frontend';
  if (/^app\/.+\.php$/i.test(relative)) return 'backend';
  return 'other';
}

export function groupPreviewFiles(files: readonly BusinessGenerationFile[]): Array<{ group: PreviewFileGroup; files: BusinessGenerationFile[] }> {
  const buckets = new Map<PreviewFileGroup, BusinessGenerationFile[]>();
  for (const file of files) {
    const group = previewFileGroup(file.path);
    const bucket = buckets.get(group);
    if (bucket) bucket.push(file);
    else buckets.set(group, [file]);
  }
  return PREVIEW_FILE_GROUPS.filter((group) => buckets.has(group)).map((group) => ({ group, files: buckets.get(group)! }));
}

const LANGUAGE_BY_EXTENSION: Record<string, CodeLanguage> = {
  php: 'php',
  sql: 'sql',
  ts: 'typescript',
  mts: 'typescript',
  cts: 'typescript',
  vue: 'vue',
  json: 'json'
};

export function fileLanguage(path: string): CodeLanguage {
  const name = path.split('/').pop() ?? '';
  const dot = name.lastIndexOf('.');
  if (dot <= 0) return 'text';
  return LANGUAGE_BY_EXTENSION[name.slice(dot + 1).toLowerCase()] ?? 'text';
}

function basename(path: string): string {
  return path.split('/').pop() || path;
}

/** 文件树/页签显示名：默认文件名；同组存在重名文件（如 zh-cn / en-us 语言包）时带上父目录。 */
export function fileLabel(path: string, siblings: readonly BusinessGenerationFile[]): string {
  const name = basename(path);
  const duplicated = siblings.some((file) => file.path !== path && basename(file.path) === name);
  if (!duplicated) return name;
  const segments = path.split('/');
  return segments.slice(-2).join('/');
}

export function countFileStatuses(files: readonly BusinessGenerationFile[]): Array<{ status: GenerationFileStatus; count: number }> {
  const counts = new Map<string, number>();
  for (const file of files) counts.set(file.status, (counts.get(file.status) ?? 0) + 1);
  return (Object.keys(FILE_STATUS_META) as GenerationFileStatus[])
    .filter((status) => counts.has(status))
    .map((status) => ({ status, count: counts.get(status)! }));
}
