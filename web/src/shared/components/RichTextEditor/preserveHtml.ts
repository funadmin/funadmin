/** Check for data loss before allowing a schema editor to replace stored HTML. */
export function preservesHtml(original: string, projected: string): boolean {
  if (!original.trim()) return true;
  const parser = new DOMParser();
  const before = parser.parseFromString(original, 'text/html').body;
  const after = parser.parseFromString(projected, 'text/html').body;
  const aliases: Record<string,string> = { B: 'STRONG', I: 'EM', STRIKE: 'S' };
  const children = (node: Node) => Array.from(node.childNodes).filter(child => child.nodeType === Node.ELEMENT_NODE || child.nodeType === Node.TEXT_NODE && child.textContent?.trim());
  const same = (a: Node, b: Node): boolean => {
    if (a.nodeType !== b.nodeType) return false;
    if (a.nodeType === Node.TEXT_NODE) return a.textContent === b.textContent;
    if (a instanceof Element && b instanceof Element) {
      if ((aliases[a.tagName] || a.tagName) !== (aliases[b.tagName] || b.tagName)) return false;
      // Added editor attributes are harmless; every original attribute must survive.
      if (Array.from(a.attributes).some(attr => b.getAttribute(attr.name) !== attr.value)) return false;
    }
    const left=children(a),right=children(b);
    return left.length === right.length && left.every((child,index) => same(child,right[index]!));
  };
  return same(before,after);
}
