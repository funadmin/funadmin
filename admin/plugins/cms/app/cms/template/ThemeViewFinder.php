<?php
declare(strict_types=1);
namespace app\cms\template;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/**
 * Blade includes and layouts resolve in the theme first, then in the shared theme layer (`view/_shared`).
 * Both roots are confined, including symlink targets; a theme file with the same name always overrides the shared one.
 */
final class ThemeViewFinder extends FileViewFinder
{
    public const SHARED = '_shared';
    /** @var list<string> */
    private array $roots;
    public function __construct(Filesystem $files,string $root)
    {
        $resolved=realpath($root);
        if(!$resolved||!is_dir($resolved))throw new InvalidArgumentException('主题不存在');
        $this->roots=array_values(array_filter([$resolved,self::sharedRoot($resolved)]));
        parent::__construct($files,$this->roots,['blade.php']);
    }
    /** 主题目录同级的共享层，只用于首页为 Blade 的主题；旧版 `.html` 主题、共享层本身或共享层缺失时返回 null。 */
    public static function sharedRoot(string $themeRoot): ?string
    {
        if(!is_file($themeRoot.DIRECTORY_SEPARATOR.'index.blade.php'))return null;
        $shared=realpath(dirname($themeRoot).DIRECTORY_SEPARATOR.self::SHARED);
        return $shared&&is_dir($shared)&&$shared!==realpath($themeRoot)?$shared:null;
    }
    /** 模板在主题或共享层中存在（Blade 文件）。 */
    public static function exists(string $themeRoot,string $template): bool
    {
        foreach(array_filter([$themeRoot,self::sharedRoot($themeRoot)]) as $root)if(is_file($root.'/'.$template.'.blade.php'))return true;
        return false;
    }
    public function find($view)
    {
        if(!is_string($view)||!preg_match('~^[a-zA-Z0-9_-]+(?:[/.][a-zA-Z0-9_-]+)*$~D',$view))throw new InvalidArgumentException('模板路径无效');
        $path=parent::find($view);$resolved=realpath($path);
        $inside=false;foreach($this->roots as $root)if($resolved&&str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))$inside=true;
        if(!$resolved||!is_file($resolved)||!is_readable($resolved)||!$inside)throw new InvalidArgumentException('模板不存在或越界');
        if(filesize($resolved)>2_000_000)throw new InvalidArgumentException('模板过大');
        return $resolved;
    }
}
