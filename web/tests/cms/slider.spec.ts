import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, expect, it } from 'vitest';
const source=readFileSync(resolve(process.cwd(),'../admin/plugins/cms/public/plugin-assets/cms/public/cms.js'),'utf8');
afterEach(()=>{document.body.innerHTML='';});
it('enhances native slides without Vue and keeps controls in sync with the visible position',()=>{
 document.body.innerHTML='<section data-cms-slider><div data-slides-track><article>A</article><article>B</article></div><div data-slider-controls hidden><button data-slide-direction="-1"></button><span data-slider-status></span><button data-slide-direction="1"></button></div></section>';
 const track=document.querySelector<HTMLElement>('[data-slides-track]')!;
 Object.defineProperty(track,'clientWidth',{value:400});
 track.scrollTo=(options:any)=>{track.scrollLeft=options.left;track.dispatchEvent(new Event('scroll'));};
 new Function(source)();
 const controls=document.querySelector<HTMLElement>('[data-slider-controls]')!;
 const previous=document.querySelector<HTMLButtonElement>('[data-slide-direction="-1"]')!,next=document.querySelector<HTMLButtonElement>('[data-slide-direction="1"]')!;
 expect(controls.hidden).toBe(false);expect(previous.disabled).toBe(true);
 next.click();expect(track.scrollLeft).toBe(400);expect(next.disabled).toBe(true);expect(document.querySelector('[data-slider-status]')?.textContent).toBe('2 / 2');
 previous.click();expect(track.scrollLeft).toBe(0);expect(previous.disabled).toBe(true);
});
it('keeps slider controls hidden for a single slide',()=>{
 document.body.innerHTML='<section data-cms-slider><div data-slides-track><article>A</article></div><div data-slider-controls hidden></div></section>';
 new Function(source)();expect((document.querySelector('[data-slider-controls]') as HTMLElement).hidden).toBe(true);
});
