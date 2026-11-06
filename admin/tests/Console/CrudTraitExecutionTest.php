<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

final class CrudTraitExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('console_trait', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->integer('status')->default(1); $table->timestamps(); $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('console_trait');
        Context::destroy();
    }

    private function request(array $post = [], string $query = ''): void
    {
        $request = new Request("POST /console-trait" . ($query === '' ? '' : '?' . $query) . " HTTP/1.1\r\n\r\n");
        $request->setPost($post);
        Context::set(\Webman\Http\Request::class, $request);
    }

    public function test_trait_create_update_list_soft_delete_restore_and_destroy(): void
    {
        $controller = new ConsoleTraitController();
        $this->request(['title' => 'before']);
        $created = json_decode($controller->create()->rawBody(), true);
        self::assertSame(200, $created['code']);
        $id = $created['data']['id'];
        $this->request(['title' => 'after']);
        self::assertSame('after', json_decode($controller->update($id)->rawBody(), true)['data']['title']);
        $this->request([], 'page=1&pageSize=5');
        self::assertSame(1, json_decode($controller->index()->rawBody(), true)['data']['total']);
        self::assertSame(200, json_decode($controller->remove($id)->rawBody(), true)['code']);
        self::assertNull(ConsoleTraitModel::find($id));
        self::assertSame(200, json_decode($controller->restoreOne($id)->rawBody(), true)['code']);
        self::assertNotNull(ConsoleTraitModel::find($id));
        $controller->remove($id);
        $controller->destroyOne($id);
        self::assertNull(ConsoleTraitModel::withTrashed()->find($id));
    }

    public function test_trait_import_export_and_batch_actions(): void
    {
        $controller = new ConsoleTraitController();
        $this->request(['rows' => [['title' => 'one'], ['title' => 'two']]]);
        self::assertSame(2, json_decode($controller->import()->rawBody(), true)['data']['created']);
        $this->request();
        self::assertCount(2, json_decode($controller->export()->rawBody(), true)['data']);
        $ids = ConsoleTraitModel::pluck('id')->all();
        $this->request(['ids' => $ids]);
        self::assertSame(2, json_decode($controller->recycle()->rawBody(), true)['data']['removed']);
        self::assertSame(0, ConsoleTraitModel::count());
        self::assertSame(2, json_decode($controller->restore()->rawBody(), true)['data']['restored']);
    }
}

final class ConsoleTraitModel extends BaseModel
{
    use LaravelSoftDelete;
    protected $table = 'console_trait';
    protected $fillable = ['title', 'status'];
}

final class ConsoleTraitController extends \app\common\controller\AdminController
{
    use \app\common\traits\Crud;
    protected string $model = ConsoleTraitModel::class;
    protected function payload(?Model $model = null): array { return array_intersect_key($this->request->post(), ['title' => true]); }
    protected function importFields(): array { return ['title' => 'title']; }
}
