import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import * as Vue from 'vue';
import * as ElementPlus from 'element-plus';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
const paths=['../admin/plugins/cms/public/plugin-assets/cms/public/cms-element.js','../admin/public/plugin-assets/cms/public/cms-element.js'];
function fixture(){
 document.body.innerHTML=`<div id="cms-site" data-cms-ui><el-container direction="vertical" class="cms-page-shell">
 <el-header class="site-header" height="auto" role="banner"><nav class="desktop-navigation" aria-label="菜单"><el-menu mode="horizontal" ellipsis="false" class="cms-el-menu"><el-menu-item index="home"><a href="/">首页</a></el-menu-item><el-sub-menu index="products" show-timeout="80" popper-class="cms-el-menu-popup"><span data-cms-slot="title">产品中心</span><el-menu-item index="all"><a href="/products/">查看全部产品</a></el-menu-item><el-menu-item index="solutions"><a href="/solutions/">解决方案</a></el-menu-item></el-sub-menu></el-menu></nav>
 <details class="mobile-navigation" data-cms-fallback><summary>菜单</summary><a href="/solutions/">解决方案</a></details>
 <div class="mobile-navigation" data-cms-enhance><el-button class="cms-mobile-menu-trigger" data-cms-open="mobileMenu" aria-label="菜单">菜单</el-button><el-drawer data-cms-model="mobileMenu" model-value="false" title="菜单" append-to-body="true" class="cms-el-drawer"><el-menu mode="vertical" data-cms-close="mobileMenu"><el-menu-item index="solutions"><a href="/solutions/">解决方案</a></el-menu-item><el-menu-item index="search"><a href="/search/">搜索</a></el-menu-item></el-menu></el-drawer></div>
 </el-header>
 <el-main id="main-content" role="main"><h1>{{ window.__cmsUiProbe = 1 }}</h1><div class="article-body" data-cms-static><p><strong>保留正文</strong> {{ 7 * 7 }}</p><a href="/news/?q=a&amp;b=c">正文链接</a></div>
 <nav class="breadcrumb" aria-label="首页"><el-breadcrumb separator="/"><el-breadcrumb-item><a href="/">首页</a></el-breadcrumb-item><el-breadcrumb-item><a href="/news/">新闻</a></el-breadcrumb-item><el-breadcrumb-item>当前页面</el-breadcrumb-item></el-breadcrumb></nav>
 <div class="content-grid"><el-row gutter="24"><el-col xs="24" sm="12" lg="8"><article class="story-card"><el-card class="cms-content-card" shadow="hover"><a class="card-cover" href="/article/"><el-image src="/cover.webp" alt="封面" fit="cover"><img data-cms-fallback src="/cover.webp" alt="封面"></el-image></a><div class="card-info"><h3><a href="/article/">文章标题</a></h3></div></el-card></article></el-col></el-row></div>
 <a href="/contact/"><el-button tag="span" type="primary" class="button">联系我们</el-button></a>
 <section data-cms-slider class="home-slides"><el-carousel autoplay="false" loop="false" height="300px" arrow="always"><el-carousel-item><article class="home-slide"><picture><img src="/first.webp" alt="第一张"></picture><div class="slide-caption"><h1>第一张轮播</h1><a href="/one/">了解更多</a></div></article></el-carousel-item><el-carousel-item><article class="home-slide"><picture><img src="/second.webp" alt="第二张"></picture><div class="slide-caption"><h1>第二张轮播</h1><a href="/two/">了解更多</a></div></article></el-carousel-item></el-carousel></section>
 <form class="search-form cms-el-search" action="/search/" method="get" data-cms-search><label for="q">搜索</label><el-input id="q" data-cms-model="query" model-value="产品 &amp; 方案" name="q" placeholder="搜索内容"><input data-cms-fallback name="q" value="产品 &amp; 方案"><span data-cms-slot="append"><el-button native-type="submit">搜索</el-button></span></el-input></form>
 <nav class="pagination"><el-pagination data-cms-pagination data-cms-change="pagination" total="70" page-size="12" current-page="2" layout="prev, slot, next" data-base-path="/english/news/" data-search="false" data-query=""><a href="/english/news/">1</a><a href="/english/news/page/2/" aria-current="page">2</a><a href="/english/news/page/3/">3</a></el-pagination></nav>
 </el-main><el-footer class="site-footer" height="auto" role="contentinfo">网站页脚</el-footer></el-container></div>`;
}
beforeEach(()=>{
 fixture();(window as any).Vue=Vue;(window as any).ElementPlus=ElementPlus;(window as any).ElementPlusLocaleZhCn=zhCn;
 (window as any).__cmsUiProbe=0;
 vi.stubGlobal('matchMedia',(query:string)=>({matches:false,media:query,addEventListener(){},removeEventListener(){},addListener(){},removeListener(){}}));
});
afterEach(()=>{
 const root=document.querySelector('#cms-site') as any;root?.__vue_app__?.unmount();document.body.innerHTML='';vi.unstubAllGlobals();
 delete (window as any).Vue;delete (window as any).ElementPlus;delete (window as any).ElementPlusLocaleZhCn;
});
describe.each(paths)('%s 官网 Element UI',path=>{
 function start(){const file=resolve(process.cwd(),path);new Function(existsSync(file)?readFileSync(file,'utf8'):'')();}
 it('真实组件渲染页面骨架、布局、卡片和按钮，同时把正文中的模板语法当作文本',async()=>{
  start();await flushPromises();
  for(const selector of ['.el-container','.el-header','.el-main','.el-footer','.el-row','.el-col','.el-card','.el-image','.el-button','.el-breadcrumb'])expect(document.querySelector(selector),selector).not.toBeNull();
  expect(document.querySelector('h1')?.textContent).toBe('{{ window.__cmsUiProbe = 1 }}');
  expect(document.querySelector('.article-body strong')?.textContent).toBe('保留正文');
  expect(document.querySelector('.article-body')?.textContent).toContain('{{ 7 * 7 }}');
  expect((window as any).__cmsUiProbe).toBe(0);
  expect(!!document.querySelector('a[href="/contact/"] .el-button')).toBe(true);
 });
 it('桌面菜单用 Element 子菜单展开，并保留实际链接',async()=>{
  start();await flushPromises();const parent=document.querySelector('.desktop-navigation .el-sub-menu')!;
  expect(parent).not.toBeNull();parent.dispatchEvent(new MouseEvent('mouseenter'));
  await new Promise(resolve=>setTimeout(resolve,200));await flushPromises();
  const link=document.querySelector('.el-menu--popup a[href="/solutions/"]');expect(link?.textContent).toBe('解决方案');
 });
 it('桌面菜单通过 Enter 激活焦点链接而不是被父菜单拦截',async()=>{
  start();await flushPromises();let clicks=0;
  const anchor=document.querySelector('.desktop-navigation a[href="/"]') as HTMLAnchorElement;
  anchor.addEventListener('click',event=>{event.preventDefault();clicks++;});anchor.focus();
  anchor.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',code:'Enter',bubbles:true,cancelable:true}));
  expect(clicks).toBe(1);
 });
 it('语言菜单的键盘选项能激活实际语言链接',async()=>{
  const header=document.querySelector('.site-header')!;header.insertAdjacentHTML('beforeend','<div class="language-switch"><el-dropdown trigger="click"><el-button class="cms-language-trigger">zh-cn</el-button><div data-cms-slot="dropdown"><el-dropdown-menu><el-dropdown-item><a href="/en/">English</a></el-dropdown-item></el-dropdown-menu></div></el-dropdown></div>');
  start();await flushPromises();(document.querySelector('.cms-language-trigger') as HTMLButtonElement).click();await flushPromises();
  const anchor=document.querySelector('.el-dropdown-menu a[href="/en/"]') as HTMLAnchorElement;expect(anchor).not.toBeNull();let clicks=0;
  anchor.addEventListener('click',event=>{event.preventDefault();clicks++;});
  const item=anchor.closest('li')!;item.focus();item.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',code:'Enter',bubbles:true,cancelable:true}));
  expect(clicks).toBe(1);
 });
 it('手机菜单使用抽屉，打开后可以访问搜索和栏目链接',async()=>{
  start();await flushPromises();
  (document.querySelector('.cms-mobile-menu-trigger') as HTMLButtonElement)?.click();await flushPromises();
  const drawer=document.querySelector('.el-drawer');expect(drawer).not.toBeNull();
  expect(drawer?.querySelector('a[href="/solutions/"]')?.textContent).toBe('解决方案');
  expect(drawer?.querySelector('a[href="/search/"]')?.textContent).toBe('搜索');
  (drawer?.querySelector('.el-drawer__close-btn') as HTMLButtonElement).click();await flushPromises();
 });
 it('轮播使用真实 Element Carousel 并保留两张图片及目标链接',async()=>{
  start();await flushPromises();
  expect(document.querySelectorAll('.el-carousel__item')).toHaveLength(2);
  expect(document.querySelector('.el-carousel a[href="/two/"]')?.textContent).toBe('了解更多');
  expect(document.querySelector('.el-carousel img[src="/second.webp"]')).not.toBeNull();
 });
 it('搜索保留 GET 表单及 Element 输入框中的已编码关键词',async()=>{
  start();await flushPromises();const form=document.querySelector('[data-cms-search]') as HTMLFormElement;
  expect(form.getAttribute('method')).toBe('get');expect(form.getAttribute('action')).toBe('/search/');
  const input=form.querySelector('.el-input input[name="q"]') as HTMLInputElement;
  expect(input.value).toBe('产品 & 方案');
  input.value='新关键词';input.dispatchEvent(new Event('input',{bubbles:true}));await flushPromises();
  expect(new FormData(form).get('q')).toBe('新关键词');
 });
 it('分页下一页保留语言和自定义栏目路径',async()=>{
  start();await flushPromises();const assigned:string[]=[];const realWindow=window;
  vi.stubGlobal('window',new Proxy(realWindow,{get(target,key){return key==='location'?{href:realWindow.location.href,assign:(url:string)=>assigned.push(url)}:Reflect.get(target,key,target);}}));
  (document.querySelector('.el-pagination .btn-next') as HTMLButtonElement)?.click();await flushPromises();
  expect(assigned).toEqual(['/english/news/page/3/']);
 });
 it('搜索分页保留编码关键词',async()=>{
  const pager=document.querySelector('[data-cms-pagination]') as HTMLElement;pager.dataset.search='true';pager.dataset.basePath='/english/search/';pager.dataset.query='产品 & 方案';
  start();await flushPromises();const assigned:string[]=[];const realWindow=window;
  vi.stubGlobal('window',new Proxy(realWindow,{get(target,key){return key==='location'?{href:realWindow.location.href,assign:(url:string)=>assigned.push(url)}:Reflect.get(target,key,target);}}));
  (document.querySelector('.el-pagination .btn-next') as HTMLButtonElement)?.click();await flushPromises();
  expect(assigned).toEqual(['/english/search/?q=%E4%BA%A7%E5%93%81%20%26%20%E6%96%B9%E6%A1%88&page=3']);
 });
 it('页面布局只产生一层 main、header 和 footer 地标',async()=>{
  start();await flushPromises();
  expect(document.querySelectorAll('main')).toHaveLength(1);
  expect(document.querySelector('header.site-header header')).toBeNull();
  expect(document.querySelector('footer.site-footer footer')).toBeNull();
 });
 it('只挂载模板声明的组件，不再根据页面 CSS 类创建组件',async()=>{
  document.querySelector('el-main')!.insertAdjacentHTML('beforeend','<article class="story-card" id="plain-story"><p>普通内容</p></article>');
  start();await flushPromises();expect(document.querySelector('#plain-story .el-card')).toBeNull();
 });
 it('组件挂载后分页依旧包含服务端输出的真实链接',async()=>{
  start();await flushPromises();
  expect(document.querySelector('.el-pagination a[href="/english/news/page/3/"]')?.textContent).toBe('3');
  expect(document.querySelector('.el-pagination a[aria-current="page"]')?.getAttribute('href')).toBe('/english/news/page/2/');
 });
 it('富文本中的组件与交互标记保持静态，文本表达式不执行',async()=>{
  document.querySelector('.article-body')!.insertAdjacentHTML('beforeend','<el-button data-cms-open="mobileMenu">正文组件字样 {{ 2 + 2 }}</el-button>');
  start();await flushPromises();
  const literal=document.querySelector('.article-body el-button');expect(literal?.textContent).toContain('{{ 2 + 2 }}');
  expect(document.querySelector('.article-body .el-button')).toBeNull();(literal as HTMLElement).click();await flushPromises();expect((document.querySelector('.el-overlay') as HTMLElement | null)?.style.display ?? 'none').toBe('none');
 });
 it('组件库未加载时保留完整服务端页面',()=>{
  delete (window as any).ElementPlus;const before=document.querySelector('#cms-site')!.innerHTML;start();
  expect(document.querySelector('#cms-site')!.innerHTML).toBe(before);
  expect(document.querySelector('a[href="/solutions/"]')).not.toBeNull();
 });
 it('组件初始化报错时恢复服务端正文并取消增强标记',async()=>{
  const logged=vi.spyOn(console,'error').mockImplementation(()=>{});
  (window as any).ElementPlus={...ElementPlus,ElMain:Vue.defineComponent({setup(){throw new Error('Component failed');}})};
  try{start();await flushPromises();expect(document.querySelector('.article-body strong')?.textContent).toBe('保留正文');expect(document.querySelector('#cms-site')?.hasAttribute('data-cms-ui-ready')).toBe(false);}
  finally{logged.mockRestore();}
 });
 it('实际生产版 Vue 捕获初始化异常时仍恢复完整服务端页面',async()=>{
  const logged=vi.spyOn(console,'error').mockImplementation(()=>{});
  const vendor=resolve(process.cwd(),'../admin/plugins/cms/public/plugin-assets/cms/public/vendor');
  const productionVue=new Function(readFileSync(resolve(vendor,'vue.global.prod.js'),'utf8')+'\n; return Vue;')();
  const box={Vue:productionVue};
  const productionElement=new Function('globalThis','exports','module','define',readFileSync(resolve(vendor,'element-plus.full.min.js'),'utf8')+'\n; return globalThis.ElementPlus;')(box,undefined,undefined,undefined);
  expect(productionElement?.ElContainer).toBeDefined();let invoked=0;
  (window as any).Vue=productionVue;(window as any).ElementPlus={...productionElement,ElMain:{setup(){invoked++;throw new Error('Production component failed');}}};
  try{start();await flushPromises();expect(invoked).toBe(1);expect(document.querySelector('.article-body strong')?.textContent).toBe('保留正文');expect(document.querySelector('#cms-site')?.hasAttribute('data-cms-ui-ready')).toBe(false);}
  finally{logged.mockRestore();}
 });
 it('组件更新报错时也恢复服务端正文',async()=>{
  const logged=vi.spyOn(console,'error').mockImplementation(()=>{});const fail=Vue.ref(false);
  (window as any).ElementPlus={...ElementPlus,ElCard:Vue.defineComponent({setup(_props,{slots}){return()=>{if(fail.value)throw new Error('Update failed');return Vue.h('div',slots.default?.());};}})};
  try{start();await flushPromises();expect(document.querySelector('#cms-site')?.getAttribute('data-cms-ui-ready')).toBe('true');fail.value=true;await flushPromises();expect(document.querySelector('.article-body strong')?.textContent).toBe('保留正文');expect(document.querySelector('#cms-site')?.hasAttribute('data-cms-ui-ready')).toBe(false);}
  finally{logged.mockRestore();}
 });
});
