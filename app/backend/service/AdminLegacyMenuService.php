<?php

declare(strict_types=1);

namespace app\backend\service;

use think\facade\Cache;

final class AdminLegacyMenuService
{
    public function menuhtml(array $cate, bool $force = true): array|string
    {
        if ($force) {
            Cache::delete('adminmenushtml' . session('admin.id'));
        }
        $list = $this->filterAuthorizedMenus($cate);
        $theme = syscfg('site', 'site_theme');
        $cacheKey = 'adminmenushtml-' . $theme . md5((string) json_encode($list));
        return db_cache($cacheKey, function () use ($list, $theme): array|string {
            if (empty($theme) || in_array($theme, [1, 2, 5])) {
                $html = '';
                foreach ($list as $item) {
                    $html .= '<li class="layui-nav-item">';
                    $badge = strtolower((string) ($item['name'] ?? '')) === 'plugin'
                        ? '<span class="layui-badge" style="text-align: right;float: right;position: absolute;right: 10%;">new</span>'
                        : '';
                    if (!empty($item['child'])) {
                        $html .= $this->parentLink($item, $badge);
                        $html = $this->childmenuhtml($html, $item['child']);
                    } else {
                        $html .= $this->leafLink($item, $badge);
                    }
                    $html .= '</li>';
                }
                return $html;
            }

            $html = ['nav' => '', 'menu' => '', 'navm' => ''];
            if ($list === []) {
                return $html;
            }
            $html['navm'] = '<li class="layui-nav-item" menu-id="' . $this->attr($list[0]['id']) . '"><a href="javascript:;"><i class="fa fa-list-ul"></i> 请选择<span class="layui-nav-more"></span></a><dl class="layui-nav-child">';
            foreach ($list as $key => $item) {
                $current = $key === 0 ? 'layui-this' : '';
                $html['nav'] .= '<li class="layui-nav-item ' . $current . '" menu-id="' . $this->attr($item['id']) . '">';
                $html['navm'] .= '<dd><a href="javascript:;" menu-id="' . $this->attr($item['id']) . '" lay-id="' . $this->attr($item['id']) . '" data-id="' . $this->attr($item['id']) . '" title="' . $this->label($item) . '" data-tips="' . $this->label($item) . '"><i class="' . $this->attr($item['icon'] ?? '') . '"></i><cite> ' . $this->label($item) . '</cite></a></dd>';
                $badge = strtolower((string) ($item['name'] ?? '')) === 'plugin' ? '<span class="layui-badge">new</span>' : '';
                $hide = (int) $theme === 4 || ((int) $theme === 3 && $key > 0) ? 'layui-hide' : '';
                $html['menu'] .= '<ul style="display:block" lay-accordion class="layui-nav layui-nav-tree ' . $hide . '" menu-id="' . $this->attr($item['id']) . '" lay-filter="menulist" lay-shrink="all" id="layui-side-left-menu-ul">';
                if (!empty($item['child'])) {
                    $html['nav'] .= $this->parentLink($item, $badge, true);
                    foreach ($item['child'] as $child) {
                        if (!empty($child['child'])) {
                            $html['menu'] .= '<li class="layui-nav-item" menu-id="' . $this->attr($child['id']) . '">' . $this->parentLink($child, $badge);
                            $html['menu'] = $this->childmenuhtml($html['menu'], $child['child']);
                            $html['menu'] .= '</li>';
                        } else {
                            $html['menu'] .= '<li class="layui-nav-item" lay-id="' . $this->attr($child['id']) . '">' . $this->leafLink($child, $badge) . '</li>';
                        }
                    }
                } else {
                    $link = $this->leafLink($item, $badge, true);
                    $html['nav'] .= $link;
                    $html['menu'] .= '<li class="layui-nav-item" menu-id="' . $this->attr($item['id']) . '" lay-id="' . $this->attr($item['id']) . '">' . $this->leafLink($item, $badge) . '</li>';
                }
                $html['menu'] .= '</ul>';
                $html['nav'] .= '</li>';
            }
            $html['navm'] .= '</dl></li>';
            return $html;
        });
    }

    public function childmenuhtml(string $html, array $child, int $type = 1): string
    {
        $html .= '<dl class="layui-nav-child">';
        foreach ($child as $item) {
            $html .= '<dd>';
            if (!empty($item['child'])) {
                $html .= $this->parentLink($item);
                $html = $this->childmenuhtml($html, $item['child'], $type);
            } else {
                $html .= $this->leafLink($item);
            }
            $html .= '</dd>';
        }
        return $html . '</dl>';
    }

    public function filterAuthorizedMenus(array $menus, int $pid = 0, ?array $permissionIds = null): array
    {
        $scope = new RoleScopeService();
        $permissionIds ??= $scope->permissionIdsForRoles($scope->currentRoleIds());
        $list = [];
        foreach ($menus as $item) {
            if ((int) $item['pid'] !== $pid) {
                continue;
            }
            $children = $this->filterAuthorizedMenus($menus, (int) $item['id'], $permissionIds);
            if (!$scope->isSuperAdmin() && !in_array((int) ($item['permission_id'] ?? 0), $permissionIds, true) && $children === []) {
                continue;
            }

            $href = (string) ($item['href'] ?? '');
            $url = parse_url($href);
            if ($href !== '' && is_array($url) && empty($url['host'])) {
                $hrefPath = trim((string) ($url['path'] ?? ''), '/');
                $path = '/' . trim((string) $item['module'] . '/' . $hrefPath, '/');
                $query = trim((string) ($url['query'] ?? '') . '&' . (string) ($item['query'] ?? ''), '&');
                if (!str_ends_with($path, '/index')) {
                    $path .= '/index';
                }
                $item['href'] = $path . ($query !== '' ? '?' . $query : '');
            }
            $item['child'] = $children;
            $list[] = $item;
        }
        return $list;
    }

    private function parentLink(array $item, string $badge = '', bool $menuId = false): string
    {
        $menu = $menuId ? ' menu-id="' . $this->attr($item['id']) . '"' : '';
        return '<a href="javascript:;"' . $menu . ' lay-id="' . $this->attr($item['id']) . '" data-id="' . $this->attr($item['id']) . '" title="' . $this->label($item) . '" data-tips="' . $this->label($item) . '"><i class="' . $this->attr($item['icon'] ?? '') . '"></i><cite> ' . $this->label($item) . '</cite>' . $badge . '</a>';
    }

    private function leafLink(array $item, string $badge = '', bool $tab = false): string
    {
        $event = $tab ? ' lay-event="tab"' : '';
        $target = (string) (($item['target'] ?? '') ?: '_self');
        return '<a href="javascript:;"' . $event . ' lay-id="' . $this->attr($item['id']) . '" data-id="' . $this->attr($item['id']) . '" title="' . $this->label($item) . '" data-tips="' . $this->label($item) . '" data-url="' . $this->attr($item['href'] ?? '') . '" target="' . $this->attr($target) . '"><i class="' . $this->attr($item['icon'] ?? '') . '"></i><cite> ' . $this->label($item) . '</cite>' . $badge . '</a>';
    }

    private function label(array $item): string
    {
        return $this->attr(lang((string) ($item['name'] ?? '')));
    }

    private function attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
