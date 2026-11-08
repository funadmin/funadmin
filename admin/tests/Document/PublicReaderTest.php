<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\controller\Reader;
use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use app\document\service\PublicContentRenderer;
use support\Context;
use support\Request;

final class PublicReaderTest extends DocumentTestCase
{
    public function test_public_pages_need_no_session_and_render_the_published_revision(): void
    {
        $row = (new DocumentService())->create(['title'=>'对外文档','slug'=>'getting-started','scope'=>'framework','visibility'=>'public','content'=>$this->doc('访客可读正文')]);
        (new DocumentVersionService())->publish($row['id'], '', 1);
        Context::destroy();
        $home = (new Reader())->index(new Request("GET /docs HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('/docs/getting-started', $home->rawBody());
        $detail = (new Reader())->detail(new Request("GET /docs/getting-started HTTP/1.1\r\nHost: localhost\r\n\r\n"), 'getting-started');
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('访客可读正文', $detail->rawBody());
        self::assertStringNotContainsString('app-layout', $detail->rawBody());
        self::assertStringContainsString('no-store', (string)$detail);
        self::assertSame(404, (new Reader())->detail(new Request("GET /docs/missing HTTP/1.1\r\n\r\n"), 'missing')->getStatusCode());
    }

    public function test_sidebar_lists_each_category_documents_and_category_links_open_the_first_document(): void
    {
        $category = (new \app\document\service\DocumentCategoryService())->create(['scope'=>'plugin_dev','name'=>'目录与 Manifest','slug'=>'manifest']);
        foreach (['plugin-directory'=>'插件目录与 Manifest','plugin-routes'=>'插件后端接口与前端路由'] as $slug => $title) {
            $row = (new DocumentService())->create(['title'=>$title,'slug'=>$slug,'scope'=>'plugin_dev','visibility'=>'public','categoryIds'=>[$category['id']],'content'=>$this->doc($title.'正文')]);
            (new DocumentVersionService())->publish($row['id'], '', 1);
        }
        Context::destroy();
        $page = (new Reader())->detail(new Request("GET /docs/plugin-routes HTTP/1.1\r\nHost: localhost\r\n\r\n"), 'plugin-routes')->rawBody();
        // 左侧菜单直接列出目录下的文档，当前文档高亮，不需要先进入目录列表页。
        self::assertMatchesRegularExpression('/目录与 Manifest.*href="\/docs\/plugin-directory".*插件目录与 Manifest.*href="\/docs\/plugin-routes"[^>]*aria-current="page"/s', $page);
        foreach (['categoryId='.$category['id'], 'scope=plugin_dev'] as $query) {
            $response = (new Reader())->index(new Request("GET /docs?$query HTTP/1.1\r\nHost: localhost\r\n\r\n"));
            self::assertSame(302, $response->getStatusCode(), $query);
            self::assertSame('/docs/plugin-directory', $response->getHeader('Location'), $query);
        }
        $search = (new Reader())->index(new Request("GET /docs?keyword=%E6%8F%92%E4%BB%B6 HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        self::assertSame(200, $search->getStatusCode());
        self::assertStringContainsString('篇文档', $search->rawBody(), '搜索仍然显示结果列表');
    }

    public function test_reader_layout_uses_the_full_width(): void
    {
        $css = (string) file_get_contents(\app\document\service\ReaderPage::assetPath('reader.css'));
        preg_match('/\.docs-shell \{[^}]*\}/', $css, $shell);
        self::assertStringNotContainsString('max-width', $shell[0], '文档页左右不留空白');
    }

    public function test_public_reader_assets_are_served_with_their_correct_types(): void
    {
        Context::destroy();
        $css = (new Reader())->stylesheet();
        $js = (new Reader())->script();
        self::assertSame(200, $css->getStatusCode());
        self::assertSame('text/css; charset=utf-8', $css->getHeader('Content-Type'));
        self::assertSame(200, $js->getStatusCode());
        self::assertSame('text/javascript; charset=utf-8', $js->getHeader('Content-Type'));
        self::assertStringContainsString('immutable', $css->getHeader('Cache-Control'));
    }

    public function test_public_routes_are_still_closed_when_the_plugin_is_disabled(): void
    {
        foreach ([true, false] as $enabled) {
            $reader = new class($enabled) extends \app\common\plugin\sdk\PluginActivationReader {
                public function __construct(private readonly bool $enabled) { parent::__construct('/unused-public-reader-test'); }
                public function readBundle(): array
                {
                    return [
                        'activation'=>\app\common\plugin\sdk\PluginActivationSnapshot::trusted(['document'=>[
                            'code'=>'document','state'=>$this->enabled?'enabled':'disabled','enabled'=>$this->enabled,
                            'needs_reinstall'=>false,'operation_token'=>null,'dependencies'=>[], 'applications'=>['app'=>true],
                        ]]),
                        'ownership'=>\app\common\plugin\sdk\PluginOwnershipSnapshot::trusted(['document'=>['applications'=>['app'=>true]]]),
                    ];
                }
            };
            $request = new Request("GET /docs HTTP/1.1\r\nHost: localhost\r\n\r\n");
            $request->controller = Reader::class;
            $middleware = new \app\common\middleware\PluginApplicationGuardMiddleware($reader);
            $response = $middleware->process($request, static fn()=>new \support\Response(200, [], 'public page'));
            self::assertSame($enabled?200:404, $response->getStatusCode());
        }
    }

    public function test_rich_content_is_escaped_and_safe_headings_tables_and_links_are_rendered(): void
    {
        $content = ['type'=>'doc','content'=>[
            ['type'=>'heading','attrs'=>['level'=>2],'content'=>[['type'=>'text','text'=>'<标题>']]],
            ['type'=>'paragraph','content'=>[['type'=>'text','text'=>'<script>alert(1)</script>','marks'=>[['type'=>'link','attrs'=>['href'=>'javascript:alert(1)']]]]]],
            ['type'=>'paragraph','content'=>[['type'=>'text','text'=>'安全链接','marks'=>[['type'=>'link','attrs'=>['href'=>'https://example.com/docs?a=1&b=2']]]]]],
            ['type'=>'codeBlock','attrs'=>['language'=>'php'],'content'=>[['type'=>'text','text'=>'<?php echo "hello";']]],
            ['type'=>'table','content'=>[['type'=>'tableRow','content'=>[['type'=>'tableCell','content'=>[['type'=>'paragraph','content'=>[['type'=>'text','text'=>'表格']]]]]]]]],
        ]];
        $result = (new PublicContentRenderer())->render($content);
        self::assertStringContainsString('&lt;script&gt;', $result['html']);
        self::assertStringNotContainsString('javascript:', $result['html']);
        self::assertStringNotContainsString('<script', $result['html']);
        self::assertStringContainsString('<table>', $result['html']);
        self::assertStringContainsString('&lt;?php', $result['html']);
        self::assertStringContainsString('data-copy-code', $result['html']);
        self::assertSame([['id'=>'section-1','level'=>2,'title'=>'<标题>']], $result['outline']);
    }
}
