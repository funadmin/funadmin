/* 全局事件总线 */
import mitt, { type Emitter } from 'mitt';

export type AppEvents = {
  /** 打开右上角设置抽屉 */
  'open:setting': void;
  /** 打开菜单搜索弹窗 */
  'open:menu-search': void;
  /** 通知刷新 */
  'refresh:notification': void;
};

const emitter: Emitter<AppEvents> = mitt<AppEvents>();

export default emitter;
