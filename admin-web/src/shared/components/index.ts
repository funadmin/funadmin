import type { App } from 'vue';
import SvgIcon from './SvgIcon/index.vue';
import PageWrapper from './PageWrapper/index.vue';
import SearchForm from './SearchForm/index.vue';
import DataTableShell from './DataTable/DataTableShell.vue';
import DataTableToolbar from './DataTable/DataTableToolbar.vue';

export function setupComponents(app: App) {
  app.component('SvgIcon', SvgIcon);
  app.component('PageWrapper', PageWrapper);
  app.component('SearchForm', SearchForm);
  app.component('DataTableShell', DataTableShell);
  app.component('DataTableToolbar', DataTableToolbar);
}

export { SvgIcon, PageWrapper, SearchForm, DataTableShell, DataTableToolbar };
