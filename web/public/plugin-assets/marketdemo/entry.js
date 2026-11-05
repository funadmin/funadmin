export function register({ name, version }) {
  return {
    components: {
      Index: {
        name: 'MarketDemoPluginPage',
        render() {
          return `市场示例插件页面 ${name} ${version}`;
        }
      }
    }
  };
}
