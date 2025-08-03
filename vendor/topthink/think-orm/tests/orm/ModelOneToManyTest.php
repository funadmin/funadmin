<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelOneToManyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_author`;',
            'CREATE TABLE `test_author` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_post`;',
            'CREATE TABLE `test_post` (
                `id` int NOT NULL AUTO_INCREMENT,
                `author_id` int NOT NULL,
                `title` varchar(255) NOT NULL DEFAULT "",
                `content` text,
                `status` tinyint NOT NULL DEFAULT 0,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_author_id` (`author_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_author`;');
        Db::execute('TRUNCATE TABLE `test_post`;');

        // 创建测试数据
        $author1 = AuthorModel::create([
            'name'  => 'author1',
            'email' => 'author1@example.com',
        ]);

        $author2 = AuthorModel::create([
            'name'  => 'author2',
            'email' => 'author2@example.com',
        ]);

        // 为作者1创建文章
        PostModel::create([
            'author_id' => $author1->id,
            'title'     => 'Post 1 by author1',
            'content'   => 'Content of post 1',
            'status'    => 1,
        ]);

        PostModel::create([
            'author_id' => $author1->id,
            'title'     => 'Post 2 by author1',
            'content'   => 'Content of post 2',
            'status'    => 1,
        ]);

        // 为作者2创建文章
        PostModel::create([
            'author_id' => $author2->id,
            'title'     => 'Post 1 by author2',
            'content'   => 'Content of post 1',
            'status'    => 0,
        ]);
    }

    public function testHasManyRelation()
    {
        // 测试关联获取
        $author = AuthorModel::find(1);
        $this->assertNotNull($author);

        $posts = $author->posts;
        $this->assertCount(2, $posts);
        $this->assertEquals('Post 1 by author1', $posts[0]->title);

        // 测试预加载
        $author = AuthorModel::with(['posts'])->find(1);
        $this->assertCount(2, $author->posts);

        // 测试关联条件
        $author = AuthorModel::with(['posts' => function ($query) {
            $query->where('status', 1);
        }])->find(2);
        $this->assertCount(0, $author->posts);

        // 测试关联统计
        $author = AuthorModel::withCount('posts')->find(1);
        $this->assertEquals(2, $author->posts_count);

        // 测试关联写入
        $author = AuthorModel::find(2);
        $result = $author->posts()->save([
            'title'   => 'New post by author2',
            'content' => 'New content',
            'status'  => 1,
        ]);
        $this->assertNotNull($result);
        $this->assertEquals($author->id, $result->author_id);
    }

    public function testHasAndHasWhereQuery()
    {
        // 测试基础 has 查询
        $authors = AuthorModel::has('posts')->select();
        $this->assertCount(2, $authors);
        $this->assertEquals(['author1', 'author2'], $authors->column('name'));

        // 测试 has 数量条件查询
        $authors = AuthorModel::has('posts', '>', 1)->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试基础 hasWhere 查询
        $authors = AuthorModel::hasWhere('posts', ['status' => 1])->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试 hasWhere 复杂条件
        $authors = AuthorModel::hasWhere('posts', [
            ['title', 'like', '%Post 1%'],
            ['status', '=', 1],
        ])->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试 hasWhere 使用闭包
        $authors = AuthorModel::hasWhere('posts', function ($query) {
            $query->where('status', 1)
                ->where('title', 'like', '%Post 2%');
        })->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试 hasWhere 闭包 OR 条件
        $authors = AuthorModel::hasWhere('posts', function ($query) {
            $query->where('status', 1)
                ->where('title', 'like', '%Post 1%');
        })->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试 hasWhere 闭包多字段组合查询
        $authors = AuthorModel::hasWhere('posts', function ($query) {
            $query->where([
                ['status', '=', 1],
                ['title', 'like', '%Post%'],
                ['content', 'like', '%Content%'],
            ]);
        })->select();
        $this->assertCount(1, $authors);
        $this->assertEquals('author1', $authors[0]->name);

        // 测试 hasWhere 与 field
        $authors = AuthorModel::hasWhere('posts', ['status' => 1], '*')->select();
        $this->assertCount(1, $authors);
        $this->assertArrayHasKey('name', $authors[0]->toArray());
        $author3 = AuthorModel::create([
            'name'  => 'author3',
            'email' => 'author3@example.com',
        ]);

        // 测试没有文章的作者
        $authors = AuthorModel::has('posts', '=', 0)->select();
        $this->assertCount(1, $authors);
    }

    public function testHasNotQuery()
    {
        // 创建测试数据
        $author1 = AuthorModel::create([
            'name'  => 'author1',
            'email' => 'author1@example.com',
        ]);

        $author2 = AuthorModel::create([
            'name'  => 'author2',
            'email' => 'author2@example.com',
        ]);

        $author3 = AuthorModel::create([
            'name'  => 'author3',
            'email' => 'author3@example.com',
        ]);

        // 只给 author1 创建文章
        PostModel::create([
            'author_id' => $author1->id,
            'title'     => 'Post 1 by author1',
            'content'   => 'Content of post 1',
            'status'    => 1,
        ]);

        // 测试基础 hasNot 查询
        $authors = AuthorModel::hasNot('posts')->select();
        $this->assertCount(2, $authors);
        $this->assertEquals(['author2', 'author3'], $authors->column('name'));

        // 测试 hasNot 与 where 组合查询
        $authors = AuthorModel::hasNot('posts')
            ->where('name', 'like', 'author%')
            ->select();
        $this->assertCount(2, $authors);
        $this->assertEquals(['author2', 'author3'], $authors->column('name'));
    }
}

class AuthorModel extends Model
{
    protected $table              = 'test_author';
    protected $autoWriteTimestamp = true;

    public function posts()
    {
        return $this->hasMany(PostModel::class, 'author_id', 'id');
    }
}

class PostModel extends Model
{
    protected $table              = 'test_post';
    protected $autoWriteTimestamp = true;

    public function author()
    {
        return $this->belongsTo(AuthorModel::class, 'author_id', 'id');
    }
}
