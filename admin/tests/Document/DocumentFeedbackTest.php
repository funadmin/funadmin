<?php

declare(strict_types=1);

namespace Tests\Document;

use app\admin\plugins\document\controller\FeedbackController;
use app\document\controller\Reader;
use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use app\document\service\FeedbackService;
use app\document\service\Schema;
use support\Context;
use support\Db;
use support\Request;

final class DocumentFeedbackTest extends DocumentTestCase
{
    private const VISITOR = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    protected function setUp(): void
    {
        parent::setUp();
        \Webman\Limiter\Limiter::init(null);
    }

    private function publish(string $slug, string $visibility = 'public'): int
    {
        $row = (new DocumentService())->create(['title'=>$slug, 'slug'=>$slug, 'scope'=>'framework', 'visibility'=>$visibility, 'content'=>$this->doc($slug)]);
        (new DocumentVersionService())->publish($row['id'], '', 1);
        return (int)$row['id'];
    }

    private function post(string $slug, array|string $payload, array $headers = ['Content-Type'=>'application/json'], string $ip = '203.0.113.9'): \support\Response
    {
        $body = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
        $lines = "POST /docs/$slug/feedback HTTP/1.1\r\nHost: docs.example.com\r\n";
        foreach ($headers as $name => $value) $lines .= "$name: $value\r\n";
        $request = new class($lines . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body) extends Request {
            public string $fakeIp = '';
            public function getRemoteIp(): string { return $this->fakeIp; }
        };
        $request->fakeIp = $ip;
        Context::destroy();
        return (new Reader())->feedback($request, $slug);
    }

    public function test_visitors_can_vote_and_a_second_vote_replaces_the_first(): void
    {
        $id = $this->publish('getting-started');
        $response = $this->post('getting-started', ['visitor'=>self::VISITOR, 'helpful'=>true]);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        self::assertSame(['helpful'=>1, 'unhelpful'=>0], (new FeedbackService())->counts([$id])[$id]);

        $response = $this->post('getting-started', ['visitor'=>self::VISITOR, 'helpful'=>false, 'reasons'=>['outdated', 'incomplete', 'outdated'], 'comment'=>"  第三步报错\u{0007}  "]);
        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        self::assertSame(['helpful'=>0, 'unhelpful'=>1], (new FeedbackService())->counts([$id])[$id], '同一访客重复提交只保留最后一次');
        $row = Db::table('document_feedback')->first();
        self::assertSame(['incomplete', 'outdated'], json_decode((string)$row->reasons, true));
        self::assertSame('第三步报错', $row->comment);
        self::assertSame(1, (int)$row->revision_no);
        self::assertSame(64, strlen((string)$row->voter));
        self::assertStringNotContainsString(self::VISITOR, json_encode((array)$row, JSON_THROW_ON_ERROR), '不保存访客标识原值');

        $this->post('getting-started', ['visitor'=>str_repeat('z', 32), 'helpful'=>true]);
        self::assertSame(['helpful'=>1, 'unhelpful'=>1], (new FeedbackService())->counts([$id])[$id]);
    }

    public function test_helpful_votes_drop_reasons_and_comments(): void
    {
        $this->publish('guide');
        $this->post('guide', ['visitor'=>self::VISITOR, 'helpful'=>true, 'reasons'=>['unclear'], 'comment'=>'不该保存']);
        $row = Db::table('document_feedback')->first();
        self::assertSame([], json_decode((string)$row->reasons, true));
        self::assertSame('', $row->comment);
    }

    public function test_only_public_published_documents_accept_feedback(): void
    {
        $this->publish('internal', 'admin');
        (new DocumentService())->create(['title'=>'草稿', 'slug'=>'draft-only', 'scope'=>'framework', 'visibility'=>'public', 'content'=>$this->doc()]);
        foreach (['internal', 'draft-only', 'missing'] as $slug) {
            self::assertSame(404, $this->post($slug, ['visitor'=>self::VISITOR, 'helpful'=>true])->getStatusCode(), $slug);
        }
        self::assertSame(0, Db::table('document_feedback')->count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->publish('guide');
        $cases = [
            'helpful 必须是布尔值' => ['visitor'=>self::VISITOR, 'helpful'=>'yes'],
            '缺少访客标识' => ['helpful'=>true],
            '访客标识过短' => ['visitor'=>'short', 'helpful'=>true],
            '未知原因' => ['visitor'=>self::VISITOR, 'helpful'=>false, 'reasons'=>['spam']],
            '原因不是列表' => ['visitor'=>self::VISITOR, 'helpful'=>false, 'reasons'=>['a'=>'unclear']],
            '说明过长' => ['visitor'=>self::VISITOR, 'helpful'=>false, 'comment'=>str_repeat('长', 501)],
        ];
        foreach ($cases as $name => $payload) self::assertSame(422, $this->post('guide', $payload)->getStatusCode(), $name);
        self::assertSame(422, $this->post('guide', 'not json')->getStatusCode());
        self::assertSame(0, Db::table('document_feedback')->count());
    }

    public function test_cross_site_and_non_json_requests_are_refused(): void
    {
        $this->publish('guide');
        $vote = ['visitor'=>self::VISITOR, 'helpful'=>true];
        self::assertSame(415, $this->post('guide', $vote, ['Content-Type'=>'text/plain'])->getStatusCode(), '表单式提交无需预检，必须拒绝');
        self::assertSame(403, $this->post('guide', $vote, ['Content-Type'=>'application/json', 'Origin'=>'https://evil.example'])->getStatusCode());
        self::assertSame(403, $this->post('guide', $vote, ['Content-Type'=>'application/json', 'Sec-Fetch-Site'=>'cross-site'])->getStatusCode());
        self::assertSame(200, $this->post('guide', $vote, ['Content-Type'=>'application/json; charset=utf-8', 'Origin'=>'https://docs.example.com', 'Sec-Fetch-Site'=>'same-origin'])->getStatusCode());
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        $this->publish('guide');
        for ($i = 0; $i < FeedbackService::THROTTLE_LIMIT; $i++) {
            self::assertSame(200, $this->post('guide', ['visitor'=>sprintf('%032d', $i), 'helpful'=>true])->getStatusCode());
        }
        self::assertSame(429, $this->post('guide', ['visitor'=>self::VISITOR, 'helpful'=>true])->getStatusCode());
        self::assertSame(200, $this->post('guide', ['visitor'=>self::VISITOR, 'helpful'=>true], ip: '198.51.100.7')->getStatusCode(), '其他 IP 不受影响');
    }

    public function test_admin_list_reports_counts_and_filters(): void
    {
        $guide = $this->publish('guide');
        $other = $this->publish('other');
        $this->post('guide', ['visitor'=>str_repeat('a', 32), 'helpful'=>true]);
        $this->post('guide', ['visitor'=>str_repeat('b', 32), 'helpful'=>false, 'reasons'=>['unclear'], 'comment'=>'看不懂']);
        $this->post('other', ['visitor'=>str_repeat('c', 32), 'helpful'=>false]);

        $all = (new FeedbackService())->list([]);
        self::assertSame(3, $all['total']);
        self::assertSame(['helpful'=>1, 'unhelpful'=>2], $all['summary']);
        $guideOnly = (new FeedbackService())->list(['documentId'=>(string)$guide, 'helpful'=>'0']);
        self::assertSame(1, $guideOnly['total']);
        self::assertSame(['helpful'=>1, 'unhelpful'=>1], $guideOnly['summary'], '汇总不受「是否有帮助」筛选影响');
        self::assertSame(['unclear'], $guideOnly['list'][0]['reasons']);
        self::assertSame('看不懂', $guideOnly['list'][0]['comment']);
        self::assertSame(1, (new FeedbackService())->list(['withComment'=>'1'])['total']);

        $this->actAs(1);
        $list = (new DocumentService())->list([]);
        $byId = array_column($list['list'], 'feedback', 'id');
        self::assertSame(['helpful'=>1, 'unhelpful'=>1], $byId[$guide]);
        self::assertSame(['helpful'=>0, 'unhelpful'=>1], $byId[$other]);

        $request = new Request("GET /admin/plugins/document/feedback?helpful=0 HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        $body = json_decode((new FeedbackController())->index()->rawBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(200, $body['code'], $body['msg']);
        self::assertSame(2, $body['data']['total']);
    }

    public function test_feedback_migration_matches_the_schema_definition(): void
    {
        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/plugins/document/database/migrations/002_document_feedback.sql');
        self::assertSame(Schema::feedbackMysql(), $sql);
        self::assertSame(Schema::mysql(), (string)file_get_contents(dirname(__DIR__, 2) . '/plugins/document/database/migrations/001_document_baseline.sql'), '基线迁移不随新表改变');
    }

    public function test_reader_page_renders_the_feedback_widget(): void
    {
        $this->publish('guide');
        Context::destroy();
        $html = (new Reader())->detail(new Request("GET /docs/guide HTTP/1.1\r\nHost: localhost\r\n\r\n"), 'guide')->rawBody();
        self::assertSame(2, substr_count($html, 'data-feedback data-slug="guide"'), '右栏与文末各一处');
        self::assertStringContainsString('本文是否对您有帮助', $html);
        foreach (FeedbackService::REASONS as $code => $label) self::assertStringContainsString('value="' . $code . '"', $html);
    }
}
