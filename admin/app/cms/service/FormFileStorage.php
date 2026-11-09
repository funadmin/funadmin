<?php
declare(strict_types=1);
namespace app\cms\service;
use InvalidArgumentException;
use RuntimeException;
/** 访客表单附件的私有存储：校验类型与大小，落盘到非公开目录，按站点隔离读取与删除。不做任何数据库操作。 */
final class FormFileStorage
{
    /** 可执行或可被浏览器执行的扩展名；文件名任何一段扩展名命中即拒绝，字段 accept 不能放行。 */
    public const BLACKLIST = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'js', 'mjs', 'cjs', 'xml', 'xsl', 'xslt', 'exe', 'sh', 'bash', 'bat', 'cmd', 'com', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'jspx', 'hta', 'htaccess', 'vbs', 'ps1', 'msi', 'dll', 'jar', 'swf'];
    /** 扩展名 → 可接受的嗅探 MIME；第一项为规范 MIME。Office/OpenDocument 容器常被嗅探为 zip 或 octet-stream。 */
    private const MIME = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'tar' => ['application/x-tar'],
        'gz' => ['application/gzip', 'application/x-gzip'],
    ];
    /** 内容以这些标记开头（忽略 BOM、空白与大小写）的文件一律拒绝，不论扩展名与嗅探结果。 */
    private const SCRIPT_PREFIXES = ['<?php', '<?=', '<script', '<html', '<svg', '<!doctype'];
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? runtime_path('plugins-data/cms/forms'), '/');
    }

    /**
     * @param array{tmp?:mixed,name?:mixed,size?:mixed} $upload
     * @return array{ext:string,mime:string,size:int,name:string}
     */
    public function inspect(array $field, array $upload): array
    {
        $tmp = $upload['tmp'] ?? null;
        if (!in_array($field['type'] ?? null, FormFieldCatalog::FILE_TYPES, true) || !is_string($tmp) || is_link($tmp) || !is_file($tmp) || !is_readable($tmp)) throw self::rejected('invalid_file_type');
        clearstatcache(true, $tmp);
        $size = filesize($tmp);
        if ($size === false) throw self::rejected('invalid_file_type');
        $max = $field['max_size'] ?? null;
        $max = is_int($max) && $max > 0 ? min($max, FormFieldCatalog::MAX_FILE_SIZE) : FormFieldCatalog::MAX_FILE_SIZE;
        if ($size > $max) throw self::rejected('file_too_large');
        $name = self::name($upload['name'] ?? '');
        $segments = explode('.', strtolower($name));
        $ext = count($segments) > 1 ? end($segments) : '';
        if ($ext === '' || array_intersect(array_slice($segments, 1), self::BLACKLIST) !== [] || !in_array($ext, self::allowed($field), true)) throw self::rejected('invalid_file_type');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !in_array($mime, self::MIME[$ext], true) || self::scriptLike($tmp)) throw self::rejected('invalid_file_type');
        return ['ext' => $ext, 'mime' => self::MIME[$ext][0], 'size' => $size, 'name' => $name];
    }

    /**
     * 复制到 `{root}/{siteId}/{Ym}/{32 位随机 hex}`（目录 0700、文件 0600），返回相对 root 的路径。
     *
     * @param array{ext:string,mime:string,size:int,name:string} $inspected
     * @return array{path:string,sha256:string}
     */
    public function store(string $siteId, array $inspected, string $tmpPath): array
    {
        $siteId = Input::id($siteId);
        $month = date('Ym');
        self::directory($this->root, root: true);
        self::directory($this->root . '/' . $siteId);
        self::directory($this->root . '/' . $siteId . '/' . $month);
        $path = $siteId . '/' . $month . '/' . bin2hex(random_bytes(16));
        $target = $this->root . '/' . $path;
        $source = @fopen($tmpPath, 'rb');
        if ($source === false) throw new RuntimeException('无法读取上传文件');
        $out = @fopen($target, 'xb');
        if ($out === false) {
            fclose($source);
            throw new RuntimeException('无法写入附件');
        }
        chmod($target, 0600);
        $copied = stream_copy_to_stream($source, $out);
        fclose($source);
        $closed = fclose($out);
        if ($copied === false || !$closed || $copied !== ($inspected['size'] ?? $copied)) {
            @unlink($target);
            throw new RuntimeException('附件写入不完整');
        }
        return ['path' => $path, 'sha256' => hash_file('sha256', $target)];
    }

    /** 解析附件的绝对路径；路径必须是本站点 store 生成的格式，root 以下不经过任何符号链接。文件可以不存在。 */
    public function absolute(string $siteId, string $path): string
    {
        $siteId = Input::id($siteId);
        if (!preg_match('#^' . $siteId . '/[0-9]{6}/[0-9a-f]{32}$#D', $path)) throw self::notFound();
        $site = $this->root . '/' . $siteId;
        $file = $this->root . '/' . $path;
        foreach ([$site, dirname($file), $file] as $item) {
            if (is_link($item)) throw self::notFound();
        }
        if (file_exists($file)) {
            $real = realpath($file);
            $base = realpath($site);
            if ($real === false || $base === false || !str_starts_with($real, $base . '/')) throw self::notFound();
        }
        return $file;
    }

    /** 删除附件；文件已不存在时不做任何事。站点目录保留。 */
    public function delete(string $siteId, string $path): void
    {
        $file = $this->absolute($siteId, $path);
        if (is_file($file) && !@unlink($file) && is_file($file)) throw new RuntimeException('无法删除附件');
    }

    /** @return list<string> 字段允许的扩展名：accept 中的类别展开、扩展名原样，限定在预设类别内；image 字段只取图片类别。 */
    private static function allowed(array $field): array
    {
        $categories = ($field['type'] ?? null) === 'image' ? ['image' => FormFieldCatalog::ACCEPT_CATEGORIES['image']] : FormFieldCatalog::ACCEPT_CATEGORIES;
        $known = array_merge(...array_values($categories));
        $accept = $field['accept'] ?? [];
        if (!is_array($accept) || $accept === []) return $known;
        $result = [];
        foreach ($accept as $item) {
            if (!is_string($item)) continue;
            $item = strtolower(ltrim(trim($item), '.'));
            array_push($result, ...($categories[$item] ?? (in_array($item, $known, true) ? [$item] : [])));
        }
        return $result;
    }

    private static function name(mixed $value): string
    {
        $name = is_string($value) ? mb_scrub($value, 'UTF-8') : '';
        $name = str_replace('\\', '/', preg_replace('/\p{Cc}/u', '', $name) ?? '');
        $slash = strrpos($name, '/');
        $name = trim($slash === false ? $name : substr($name, $slash + 1));
        if (mb_strlen($name) <= 255) return $name;
        $dot = strrpos($name, '.');
        $suffix = $dot === false ? '' : substr($name, $dot);
        return mb_strlen($suffix) < 255 ? mb_substr($name, 0, 255 - mb_strlen($suffix)) . $suffix : mb_substr($name, 0, 255);
    }

    private static function scriptLike(string $tmp): bool
    {
        $head = (string)@file_get_contents($tmp, false, null, 0, 1024);
        $head = strtolower(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head) ?? $head));
        foreach (self::SCRIPT_PREFIXES as $prefix) {
            if (str_starts_with($head, $prefix)) return true;
        }
        return false;
    }

    /** root 本身可以由部署方链接到别处；root 以下的站点、月份目录不允许是符号链接。 */
    private static function directory(string $path, bool $root = false): void
    {
        if (!$root && is_link($path)) throw new RuntimeException('附件目录不能是符号链接');
        if (!is_dir($path)) {
            if (!@mkdir($path, 0700, $root) && !is_dir($path)) throw new RuntimeException('无法创建附件目录');
            chmod($path, 0700);
        }
    }

    private static function rejected(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException($key, 422);
    }

    private static function notFound(): InvalidArgumentException
    {
        return new InvalidArgumentException('附件不存在', 404);
    }
}
