import type { ModelField, FormData } from '../types';
export function planModelUpgrade(previous: ModelField[], next: ModelField[], values: FormData) {
  const data: FormData = {}, removed: Array<{name:string;label:string;value:unknown}> = [];
  const compatible = (left:string, right:string) => left === right || ['text','textarea'].includes(left) && ['text','textarea'].includes(right);
  for (const field of previous) {
    const replacement = next.find(item => item.name === field.name);
    if (!replacement || !compatible(field.type,replacement.type)) {
      if (Object.prototype.hasOwnProperty.call(values,field.name)) removed.push({name:field.name,label:field.label,value:values[field.name]});
    }
  }
  for (const field of next) {
    const old = previous.find(item => item.name === field.name);
    data[field.name] = old && compatible(old.type,field.type) && Object.prototype.hasOwnProperty.call(values,field.name) ? values[field.name] : field.default ?? (field.type === 'boolean' ? false : field.type === 'gallery' ? [] : null);
  }
  return {data,removed};
}
