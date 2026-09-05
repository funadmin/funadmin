const Index = {
  name: 'Plugin{%plugin%}Index',
  template: '<section><h2>{%title%}</h2></section>'
};

export function register() {
  return {
    components: { Index }
  };
}
