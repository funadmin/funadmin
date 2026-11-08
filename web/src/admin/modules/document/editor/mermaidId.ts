/** 模块级递增编号：多个预览实例（主预览、版本抽屉）同时渲染时 SVG 的 id、样式和 marker 引用不会互相串。 */
let sequence = 0;
export function nextMermaidId(): string {
  sequence += 1;
  return `doc-mermaid-${sequence}`;
}
