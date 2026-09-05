const Index = {
  name: 'PluginExampleIndex',
  template: '<section><h2>测试示例插件</h2></section>'
};

export function register() {
  return {
    components: { Index }
  };
}
