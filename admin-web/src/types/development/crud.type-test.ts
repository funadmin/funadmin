import type { CrudArtifactMap, CrudDefinition } from './crud';

declare const base: Omit<CrudDefinition, 'target' | 'generationTargets'>;
declare const artifacts: CrudArtifactMap;

const coreDefinition: CrudDefinition = {
  ...base,
  target: { type: 'core' },
  generationTargets: artifacts
};

const pluginDefinition: CrudDefinition = {
  ...base,
  target: { type: 'plugin', plugin: 'shop', scope: 'both' }
};

// @ts-expect-error 插件目标路径必须由服务派生
const unsafePluginDefinition: CrudDefinition = {
  ...pluginDefinition,
  generationTargets: artifacts
};

void coreDefinition;
void unsafePluginDefinition;
