<?php
declare(strict_types=1);
namespace app\cms\template;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/** Blade includes and layouts are confined to this theme, including symlink targets. */
final class ThemeViewFinder extends FileViewFinder
{
    private string $root;
    public function __construct(Filesystem $files,string $root)
    {
        $resolved=realpath($root);
        if(!$resolved||!is_dir($resolved))throw new InvalidArgumentException('主题不存在');
        $this->root=$resolved;
        parent::__construct($files,[$resolved],['blade.php']);
    }
    public function find($view)
    {
        if(!is_string($view)||!preg_match('~^[a-zA-Z0-9_-]+(?:[/.][a-zA-Z0-9_-]+)*$~D',$view))throw new InvalidArgumentException('模板路径无效');
        $path=parent::find($view);$resolved=realpath($path);
        if(!$resolved||!is_file($resolved)||!is_readable($resolved)||!str_starts_with($resolved,$this->root.DIRECTORY_SEPARATOR))throw new InvalidArgumentException('模板不存在或越界');
        if(filesize($resolved)>2_000_000)throw new InvalidArgumentException('模板过大');
        return $resolved;
    }
}
