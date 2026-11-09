<?php

declare(strict_types=1);

namespace Tests;

use app\common\view\Blade;
use PHPUnit\Framework\TestCase;

/** Blade 视图处理器：模板定位与框架规则一致，{{ }} 转义、{!! !!} 原样输出。 */
final class BladeViewTest extends TestCase
{
    private string $relative = '';

    protected function setUp(): void
    {
        $this->relative = 'runtime/blade-view-test-' . bin2hex(random_bytes(4));
        mkdir(base_path($this->relative . '/view/partials'), 0700, true);
        file_put_contents(base_path($this->relative . '/view/page.blade.php'), "<h1>{{ \$title }}</h1>{!! \$html !!}@if(\$items)<ul>@foreach(\$items as \$item)<li>{{ \$item }}</li>@endforeach</ul>@endif\n@include('partials.footer')");
        file_put_contents(base_path($this->relative . '/view/partials/footer.blade.php'), '<footer>{{ $title }}</footer>');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($this->relative), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir(base_path($this->relative));
    }

    public function test_renders_project_relative_templates_with_escaping_loops_and_includes(): void
    {
        $html = Blade::render('/' . $this->relative . '/view/page', ['title' => 'A <b>&"', 'html' => '<em>raw</em>', 'items' => ['x<y', 'z']]);
        self::assertSame('<h1>A &lt;b&gt;&amp;&quot;</h1><em>raw</em><ul><li>x&lt;y</li><li>z</li></ul><footer>A &lt;b&gt;&amp;&quot;</footer>', $html);
    }

    public function test_project_config_uses_blade_and_view_helper_renders_through_it(): void
    {
        self::assertSame(Blade::class, config('view.handler'));
        $response = view('/' . $this->relative . '/view/page', ['title' => 'T', 'html' => '', 'items' => []]);
        self::assertSame('<h1>T</h1><footer>T</footer>', $response->rawBody());
    }

    public function test_app_templates_resolve_under_the_app_view_directory(): void
    {
        self::assertFileExists(app_path('document/view/public/reader.blade.php'));
        self::assertFileDoesNotExist(app_path('document/view/public/reader.php'));
    }
}
