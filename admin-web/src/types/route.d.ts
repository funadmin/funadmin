import 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    icon?: string;
    hidden?: boolean;
    keepAlive?: boolean;
    affix?: boolean;
    permission?: string | string[];
    roles?: string[];
    activeMenu?: string;
    breadcrumb?: boolean;
    transition?: string;
    rank?: number;
  }
}

export {};
