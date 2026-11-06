#!/usr/bin/env bash
# 在一次性 MySQL 8.4 容器里验证按方法权限迁移（025 插件、027 宿主）的授权等价性：
#   1. 执行 025/027 之前的全部核心 SQL 迁移；2. 写入升级前状态（每个旧权限一个角色、插件菜单、停用的旧权限）；
#   3. 025、027 各执行两次；4. 逐个 (角色, 接口) 比较升级前后能否访问，并检查站点操作、菜单改绑、停用状态。
# 用法：admin/tools/permission-migrations/verify-mysql.sh（需要 podman 或 docker；CONTAINER_CLI 可指定）。
set -euo pipefail
cd "$(dirname "$0")/../.."
CLI=${CONTAINER_CLI:-$(command -v podman || command -v docker)}
export PERM_WORK=${PERM_WORK:-$(mktemp -d)}
NAME=funadmin-permtest-$(openssl rand -hex 4)
PW=$(openssl rand -hex 16)
"$CLI" run -d --rm --name "$NAME" -e MYSQL_ROOT_PASSWORD="$PW" docker.io/library/mysql:8.4 >/dev/null
trap '"$CLI" rm -f "$NAME" >/dev/null 2>&1 || true' EXIT
run() { "$CLI" exec -i "$NAME" sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" mysql --default-character-set=utf8mb4 --protocol=TCP -h127.0.0.1 -uroot -N $1"; }
for _ in $(seq 1 90); do run "-e 'SELECT 1'" >/dev/null 2>&1 && break; sleep 1; done
echo "CREATE DATABASE permtest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | run ""
for f in $(ls database/core/migrations/archive/baseline-2026-11-06/*.sql | sort); do
  case "$(basename "$f")" in 025_plugin_permission_per_method.sql|027_host_permission_per_method.sql) continue;; esac
  run permtest < "$f"
done
php tools/permission-migrations/seed-plugin.php
php tools/permission-migrations/seed-host.php
run permtest < "$PERM_WORK/permtest-seed.sql"
run permtest < "$PERM_WORK/host-seed.sql"
echo "SELECT code,obj,act,status,resource_type,id FROM fun_permission WHERE source_type='plugin';" | run permtest > "$PERM_WORK/mysql-perms-before.txt"
echo "SELECT id, IFNULL(code,'NULL'), obj, act, status, is_public, resource_type FROM fun_permission;" | run permtest > "$PERM_WORK/h-before-perm.txt"
echo "SELECT v0, v2, v3 FROM fun_casbin_rule WHERE ptype='p' AND (v0 LIKE 'role:8%' OR v0='role:99999');" | run permtest > "$PERM_WORK/h-before-grant.txt"
for _ in 1 2; do
  run permtest < database/core/migrations/archive/baseline-2026-11-06/025_plugin_permission_per_method.sql
  run permtest < database/core/migrations/archive/baseline-2026-11-06/027_host_permission_per_method.sql
done
echo "SELECT ptype,v0,v1,v2,v3 FROM fun_casbin_rule WHERE v0 LIKE 'role:7%' ORDER BY v0,v2,v3;" | run permtest > "$PERM_WORK/mysql-after.txt"
echo "SELECT code,obj,act,status,resource_type,id FROM fun_permission WHERE source_type='plugin';" | run permtest > "$PERM_WORK/mysql-perms.txt"
echo "SELECT name,permission_id,query FROM fun_admin_menu WHERE href LIKE '/plugin/test/m%';" | run permtest > "$PERM_WORK/mysql-menus.txt"
echo "SELECT id, IFNULL(code,'NULL'), obj, act, status, is_public, resource_type FROM fun_permission;" | run permtest > "$PERM_WORK/h-after-perm.txt"
echo "SELECT v0, v2, v3 FROM fun_casbin_rule WHERE ptype='p' AND (v0 LIKE 'role:8%' OR v0='role:99999');" | run permtest > "$PERM_WORK/h-after-grant.txt"
php tools/permission-migrations/check-plugin.php
php tools/permission-migrations/check-host.php
groups=$(echo "SELECT CONCAT(SUM(code IS NOT NULL OR obj <> '' OR act <> ''), ' ', SUM(NOT EXISTS (SELECT 1 FROM fun_permission c WHERE c.pid = g.id) AND NOT EXISTS (SELECT 1 FROM fun_admin_menu m WHERE m.permission_id = g.id))) FROM fun_permission g WHERE resource_type = 'group';" | run permtest)
echo "groups with code: ${groups% *}, empty groups: ${groups#* }"
[ "$groups" = "0 0" ]
