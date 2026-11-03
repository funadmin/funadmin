<?php

declare(strict_types=1);

namespace app\admin\system\service;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/** 原生 PG 快照：先隔离还原验证，再在单事务内恢复目标 schema。 */
final class PostgresqlDatabaseBackup
{
    private readonly string $schema;
    private readonly array $config;
    private ?int $serverMajor = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->schema = (string) ($config['search_path'] ?? 'public');
        if (!preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/D', $this->schema) || str_starts_with($this->schema, 'pg_') || $this->schema === 'information_schema') throw new RuntimeException('PostgreSQL 备份 schema 不合法');
        foreach (['host', 'database', 'username', 'password'] as $key) if (!is_string($config[$key] ?? null) || preg_match('/[\x00\r\n]/', $config[$key])) throw new RuntimeException('PostgreSQL 备份连接配置不合法');
        if (!preg_match('/\A[A-Za-z0-9_.:-]+\z/D', $config['host']) || !preg_match('/\A[A-Za-z0-9_]{1,63}\z/D', $config['database']) || !in_array($config['sslmode'] ?? 'prefer', ['disable','allow','prefer','require','verify-ca','verify-full'], true)) throw new RuntimeException('PostgreSQL 备份连接参数不合法');
    }

    public function preflight(): void
    {
        $pdo = $this->pdo();
        $server = (int) $pdo->query('SHOW server_version_num')->fetchColumn();
        $this->serverMajor = intdiv($server, 10000);
        foreach (['pg_dump', 'pg_restore', 'psql'] as $tool) {
            $version = $this->process([$tool, '--version']);
            if (!preg_match('/\b(\d+)\.\d+/', $version, $match) || (int) $match[1] !== intdiv($server, 10000)) throw new RuntimeException('PostgreSQL 客户端主版本必须与服务器一致：' . $tool);
        }
        $privilege = $pdo->query('SELECT rolcreatedb OR rolsuper FROM pg_roles WHERE rolname=current_user')->fetchColumn();
        if (!in_array($privilege, [true, 't', 1, '1'], true)) throw new RuntimeException('PostgreSQL 升级恢复预检要求创建临时验证库权限');
        $statement = $pdo->prepare('SELECT pg_get_userbyid(nspowner)=current_user OR (SELECT rolsuper FROM pg_roles WHERE rolname=current_user) FROM pg_namespace WHERE nspname=?');
        $statement->execute([$this->schema]);
        if (!in_array($statement->fetchColumn(), [true, 't', 1, '1'], true)) throw new RuntimeException('PostgreSQL 升级恢复要求拥有目标 schema');
        $this->assertDependencies($pdo);
        $validation = 'funadmin_pg_preflight_' . bin2hex(random_bytes(8));
        $pdo->exec('CREATE DATABASE "' . $validation . '" TEMPLATE template0 ENCODING \'UTF8\'');
        try { $this->pdo($validation); }
        finally { $pdo->exec('DROP DATABASE "' . $validation . '" WITH (FORCE)'); }
    }

    public function backup(string $path): string
    {
        $this->safePath($path, false);
        $this->preflight();
        $temporary = $this->temporary();
        $pdo = $this->pdo();
        try {
            $pdo->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $snapshot = (string) $pdo->query('SELECT pg_export_snapshot()')->fetchColumn();
            $manifest = ['format' => 'funadmin-pg-backup-v1', 'driver' => 'pgsql', 'database' => $this->config['database'], 'schema' => $this->schema, 'serverVersion' => (int) $pdo->query('SHOW server_version_num')->fetchColumn(), 'createdAt' => date(DATE_ATOM), 'objects' => $this->inventory($pdo)];
            $this->process(['pg_dump', '--format=custom', '--no-owner', '--no-acl', '--schema=' . $this->schema, '--snapshot=' . $snapshot, '--file=' . $temporary . '/dump', '--dbname=' . $this->config['database']]);
            $pdo->rollBack();
            $manifest['dumpHash'] = hash_file('sha256', $temporary . '/dump');
            $manifest['toolVersion'] = trim($this->process(['pg_dump', '--version']));
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('无法创建 PostgreSQL 备份');
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $zip->addFile($temporary . '/dump', 'database.dump');
            if (!$zip->close()) throw new RuntimeException('无法保存 PostgreSQL 备份');
            chmod($path, 0600);
            return (string) hash_file('sha256', $path);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (is_file($path)) unlink($path);
            throw $error;
        } finally { $this->cleanup($temporary); }
    }

    public function restore(string $path, string $expectedHash): array
    {
        $this->safePath($path, true);
        if (!hash_equals($expectedHash, (string) hash_file('sha256', $path))) throw new RuntimeException('PostgreSQL 备份 SHA-256 校验失败');
        $this->preflight();
        $temporary = $this->temporary();
        $database = 'funadmin_pg_verify_' . bin2hex(random_bytes(8));
        $pdo = $this->pdo();
        $created = false;
        $locked = false;
        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) throw new RuntimeException('PostgreSQL 备份不是有效快照');
            try {
                if ($zip->numFiles !== 2 || $zip->statName('manifest.json')['size'] > 16777216) throw new RuntimeException('PostgreSQL 备份内容不合法');
                $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
                if (($manifest['format'] ?? '') !== 'funadmin-pg-backup-v1' || ($manifest['driver'] ?? '') !== 'pgsql' || ($manifest['database'] ?? '') !== $this->config['database'] || ($manifest['schema'] ?? '') !== $this->schema) throw new RuntimeException('PostgreSQL 备份身份不匹配');
                if ((int) ($manifest['serverVersion'] ?? 0) > (int) $pdo->query('SHOW server_version_num')->fetchColumn()) throw new RuntimeException('禁止恢复较新服务器的 PostgreSQL 备份');
                $stream = $zip->getStream('database.dump');
                if ($stream === false) throw new RuntimeException('PostgreSQL dump 缺失');
                $output = fopen($temporary . '/dump', 'xb');
                stream_copy_to_stream($stream, $output);
                fclose($stream); fclose($output);
            } finally { $zip->close(); }
            if (!hash_equals((string) $manifest['dumpHash'], (string) hash_file('sha256', $temporary . '/dump'))) throw new RuntimeException('PostgreSQL dump SHA-256 校验失败');
            $lock = $pdo->prepare('SELECT pg_try_advisory_lock(hashtext(?))');
            $lock->execute(['funadmin-restore:' . $this->config['database'] . ':' . $this->schema]);
            if ($lock->fetchColumn() !== true) throw new RuntimeException('PostgreSQL 恢复正在执行');
            $locked = true;
            $pdo->exec('CREATE DATABASE "' . $database . '" TEMPLATE template0 ENCODING \'UTF8\'');
            $created = true;
            $validation = $this->pdo($database);
            if ($this->schema === 'public') $validation->exec('DROP SCHEMA public');
            $this->process(['pg_restore', '--exit-on-error', '--single-transaction', '--no-owner', '--no-acl', '--dbname=' . $database, $temporary . '/dump']);
            $restored = $this->inventory($validation);
            if ($restored !== $manifest['objects']) {
                $different = array_keys(array_filter($restored, static fn (mixed $value, string $key): bool => $value !== ($manifest['objects'][$key] ?? null), ARRAY_FILTER_USE_BOTH));
                $detail = '';
                if (in_array('constraints', $different, true)) {
                    foreach ($restored['constraints'] as $i => $constraint) {
                        if ($constraint !== ($manifest['objects']['constraints'][$i] ?? null)) { $detail = json_encode([$constraint, $manifest['objects']['constraints'][$i] ?? null]); break; }
                    }
                }
                throw new RuntimeException('PostgreSQL 隔离还原校验失败：' . implode(',', $different) . $detail);
            }
            $validation = null;
            // pg_restore 的 clean 不会清除升级新增对象；由 psql 单事务包裹清理与原生恢复 SQL。
            $this->process(['pg_restore', '--exit-on-error', '--clean', '--if-exists', '--no-owner', '--no-acl', '--file=' . $temporary . '/restore.sql', $temporary . '/dump']);
            $input = fopen($temporary . '/input.sql', 'xb');
            fwrite($input, 'DROP SCHEMA "' . $this->schema . '" CASCADE;' . "\n");
            $sql = fopen($temporary . '/restore.sql', 'rb');
            stream_copy_to_stream($sql, $input);
            fclose($sql); fclose($input);
            $this->process(['psql', '--no-psqlrc', '--single-transaction', '--set=ON_ERROR_STOP=1', '--dbname=' . $this->config['database'], '--file=' . $temporary . '/input.sql']);
            return ['backup_schema' => '', 'shadow_schema' => $database];
        } finally {
            if ($created) {
                try { $pdo->exec('DROP DATABASE "' . $database . '" WITH (FORCE)'); } catch (Throwable) { /* 临时库名称保留在错误上下文供人工检查。 */ }
            }
            if ($locked) {
                $statement = $pdo->prepare('SELECT pg_advisory_unlock(hashtext(?))');
                $statement->execute(['funadmin-restore:' . $this->config['database'] . ':' . $this->schema]);
            }
            $this->cleanup($temporary);
        }
    }

    private function assertDependencies(PDO $pdo): void
    {
        $sql = "SELECT count(*) FROM pg_depend d JOIN pg_class c ON c.oid=d.objid AND d.classid='pg_class'::regclass JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_class r ON r.oid=d.refobjid AND d.refclassid='pg_class'::regclass JOIN pg_namespace rn ON rn.oid=r.relnamespace WHERE ((n.nspname=? AND rn.nspname<>?) OR (n.nspname<>? AND rn.nspname=?)) AND n.nspname NOT LIKE 'pg_%' AND rn.nspname NOT LIKE 'pg_%' AND n.nspname<>'information_schema' AND rn.nspname<>'information_schema'";
        $statement = $pdo->prepare($sql);
        $statement->execute(array_fill(0, 4, $this->schema));
        if ((int) $statement->fetchColumn() !== 0) throw new RuntimeException('PostgreSQL 快照存在跨 schema 依赖，禁止自动恢复');
        $statement = $pdo->prepare("SELECT count(*) FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace tn ON tn.oid=t.relnamespace JOIN pg_class r ON r.oid=c.confrelid JOIN pg_namespace rn ON rn.oid=r.relnamespace WHERE c.contype='f' AND ((tn.nspname=? AND rn.nspname<>?) OR (tn.nspname<>? AND rn.nspname=?))");
        $statement->execute(array_fill(0, 4, $this->schema));
        if ((int) $statement->fetchColumn() !== 0) throw new RuntimeException('PostgreSQL 快照存在跨 schema 外键');
        $statement = $pdo->prepare("SELECT count(*) FROM pg_rewrite rw JOIN pg_class v ON v.oid=rw.ev_class JOIN pg_namespace vn ON vn.oid=v.relnamespace JOIN pg_depend d ON d.classid='pg_rewrite'::regclass AND d.objid=rw.oid JOIN pg_class r ON d.refclassid='pg_class'::regclass AND r.oid=d.refobjid JOIN pg_namespace rn ON rn.oid=r.relnamespace WHERE ((vn.nspname=? AND rn.nspname<>?) OR (vn.nspname<>? AND rn.nspname=?)) AND vn.nspname NOT LIKE 'pg_%' AND rn.nspname NOT LIKE 'pg_%' AND vn.nspname<>'information_schema' AND rn.nspname<>'information_schema'");
        $statement->execute(array_fill(0, 4, $this->schema));
        if ((int) $statement->fetchColumn() !== 0) throw new RuntimeException('PostgreSQL 快照存在跨 schema 视图依赖');
        $statement = $pdo->prepare("SELECT count(*) FROM pg_depend d CROSS JOIN LATERAL pg_identify_object(d.classid,d.objid,d.objsubid) o CROSS JOIN LATERAL pg_identify_object(d.refclassid,d.refobjid,d.refobjsubid) r WHERE ((o.schema=? AND r.schema<>?) OR (o.schema<>? AND r.schema=?)) AND o.schema NOT LIKE 'pg_%' AND r.schema NOT LIKE 'pg_%' AND o.schema<>'information_schema' AND r.schema<>'information_schema'");
        $statement->execute(array_fill(0, 4, $this->schema));
        if ((int) $statement->fetchColumn() !== 0) throw new RuntimeException('PostgreSQL 快照存在跨 schema 对象依赖');
    }

    private function inventory(PDO $pdo): array
    {
        $statement = $pdo->prepare("SELECT c.relname,c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname=? AND c.relkind IN ('r','p','v','m','S','i') ORDER BY c.relname");
        $statement->execute([$this->schema]);
        $objects = $statement->fetchAll(PDO::FETCH_ASSOC);
        $tables = [];
        foreach ($objects as $object) {
            if (!in_array($object['relkind'], ['r', 'p'], true)) continue;
            $name = str_replace('"', '""', $object['relname']);
            $rows = $pdo->query('SELECT row_to_json(t)::text AS row FROM "' . $this->schema . '"."' . $name . '" t ORDER BY row_to_json(t)::text COLLATE "C"');
            $hash = hash_init('sha256');
            $count = 0;
            while (($value = $rows->fetchColumn()) !== false) { hash_update($hash, pack('N', strlen($value)) . $value); $count++; }
            $tables[$object['relname']] = ['count' => $count, 'hash' => hash_final($hash)];
        }
        $indexes = $pdo->prepare('SELECT indexname,indexdef FROM pg_indexes WHERE schemaname=? ORDER BY indexname');
        $indexes->execute([$this->schema]);
        $constraints = $pdo->prepare('SELECT t.relname,c.conname,pg_get_constraintdef(c.oid) AS definition FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=t.relnamespace WHERE n.nspname=? AND c.contype<>\'n\' ORDER BY t.relname,c.conname');
        $constraints->execute([$this->schema]);
        $sequences = [];
        foreach ($objects as $object) {
            if ($object['relkind'] !== 'S') continue;
            $name = str_replace('"', '""', $object['relname']);
            $sequences[$object['relname']] = $pdo->query('SELECT last_value,is_called FROM "' . $this->schema . '"."' . $name . '"')->fetch(PDO::FETCH_ASSOC);
        }
        $columns = $pdo->prepare('SELECT table_name,column_name,data_type,is_nullable,column_default,is_identity,generation_expression FROM information_schema.columns WHERE table_schema=? ORDER BY table_name,ordinal_position');
        $columns->execute([$this->schema]);
        $constraintRows = $constraints->fetchAll(PDO::FETCH_ASSOC);
        foreach ($constraintRows as &$constraint) {
            $definition = preg_replace_callback('/\(ARRAY\[((?:\x27(?:[^\x27]|\x27\x27)*\x27::character varying(?:, )?)+)\]\)::text\[\]/', static fn (array $match): string => 'ARRAY[' . preg_replace('/(\x27(?:[^\x27]|\x27\x27)*\x27)::character varying/', '($1::character varying)::text', $match[1]) . ']', $constraint['definition']);
            $constraint['definition'] = $definition;
            if (preg_match('/\ACHECK \(\(\(([a-z_][a-z0-9_]*)\)::text = ANY /', $definition, $column)
                && preg_match_all("/'(?:[^']|'')*'/", $definition, $values)
                && preg_replace("/'(?:[^']|'')*'|CHECK|ANY|ARRAY|character varying|text|[a-z_][a-z0-9_]*|[\\s()\\[\\]:=,]/i", '', $definition) === '') {
                $constraint['definition'] = ['enum' => $column[1], 'values' => $values[0]];
            }
        }
        unset($constraint);
        return ['objects' => $objects, 'tables' => $tables, 'columns' => $columns->fetchAll(PDO::FETCH_ASSOC), 'indexes' => $indexes->fetchAll(PDO::FETCH_ASSOC), 'constraints' => $constraintRows, 'sequences' => $sequences];
    }

    private function pdo(?string $database = null): PDO
    {
        $config = $this->config;
        return new PDO('pgsql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 5432) . ';dbname=' . ($database ?? $config['database']) . ';sslmode=' . ($config['sslmode'] ?? 'prefer'), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    private function process(array $arguments): string
    {
        if ($this->serverMajor !== null) {
            foreach (['/opt/postgresql/', '/usr/lib/postgresql/'] as $base) {
                $binary = $base . $this->serverMajor . '/bin/' . $arguments[0];
                if (is_executable($binary)) { $arguments[0] = $binary; break; }
            }
        }
        $environment = ['PATH' => getenv('PATH') ?: '/usr/bin:/bin', 'PGHOST' => $this->config['host'], 'PGPORT' => (string) ($this->config['port'] ?? 5432), 'PGUSER' => $this->config['username'], 'PGPASSWORD' => $this->config['password'], 'PGSSLMODE' => $this->config['sslmode'] ?? 'prefer', 'PGCONNECT_TIMEOUT' => '5', 'PGCLIENTENCODING' => 'UTF8'];
        if ($this->serverMajor !== null && is_dir('/opt/postgresql/' . $this->serverMajor . '/lib')) $environment['LD_LIBRARY_PATH'] = '/opt/postgresql/' . $this->serverMajor . '/lib';
        $process = proc_open($arguments, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('PostgreSQL 客户端无法启动');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $output = ''; $error = ''; $deadline = microtime(true) + 300;
        do {
            $output .= stream_get_contents($pipes[1]); $error .= stream_get_contents($pipes[2]);
            if (strlen($output) + strlen($error) > 1048576 || microtime(true) > $deadline) { proc_terminate($process, 9); fclose($pipes[1]); fclose($pipes[2]); proc_close($process); throw new RuntimeException('PostgreSQL 客户端超时或输出超限'); }
            $status = proc_get_status($process);
            if ($status['running']) usleep(10000);
        } while ($status['running']);
        $output .= stream_get_contents($pipes[1]); $error .= stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        if ($status['exitcode'] !== 0) {
            throw new RuntimeException('PostgreSQL 客户端执行失败：' . basename($arguments[0]));
        }
        return $output;
    }

    private function safePath(string $path, bool $exists): void
    {
        if ($path === '' || is_link($path)) throw new RuntimeException('PostgreSQL 备份路径不合法');
        for ($directory = dirname($path); $directory !== dirname($directory); $directory = dirname($directory)) if (is_link($directory)) throw new RuntimeException('PostgreSQL 备份路径禁止符号链接');
        if ($exists && !is_file($path)) throw new RuntimeException('PostgreSQL 备份不存在');
        if (!$exists && file_exists($path)) throw new RuntimeException('PostgreSQL 备份禁止覆盖');
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) throw new RuntimeException('无法创建 PostgreSQL 备份目录');
    }

    private function temporary(): string
    {
        $path = sys_get_temp_dir() . '/funadmin-pg-backup-' . bin2hex(random_bytes(16));
        if (!mkdir($path, 0700)) throw new RuntimeException('无法创建 PostgreSQL 私有临时目录');
        return $path;
    }

    private function cleanup(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) if (is_file($file) && !is_link($file)) unlink($file);
        rmdir($path);
    }
}
