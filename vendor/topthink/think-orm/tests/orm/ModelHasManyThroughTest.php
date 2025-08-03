<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelHasManyThroughTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_country`;',
            'CREATE TABLE `test_country` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_author`;',
            'CREATE TABLE `test_author` (
                `id` int NOT NULL AUTO_INCREMENT,
                `country_id` int NOT NULL,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_country_id` (`country_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
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
        Db::execute('TRUNCATE TABLE `test_country`;');
        Db::execute('TRUNCATE TABLE `test_author`;');
        Db::execute('TRUNCATE TABLE `test_post`;');

        // 创建测试数据
        $country1 = Country::create([
            'name' => 'China',
        ]);

        $country2 = Country::create([
            'name' => 'USA',
        ]);

        $author1 = Author::create([
            'country_id' => $country1->id,
            'name'       => 'author1',
            'email'      => 'author1@example.com',
        ]);

        $author2 = Author::create([
            'country_id' => $country1->id,
            'name'       => 'author2',
            'email'      => 'author2@example.com',
        ]);

        $author3 = Author::create([
            'country_id' => $country2->id,
            'name'       => 'author3',
            'email'      => 'author3@example.com',
        ]);

        Post::create([
            'author_id' => $author1->id,
            'title'     => 'Post1',
            'content'   => 'Content1',
        ]);

        Post::create([
            'author_id' => $author1->id,
            'title'     => 'Post2',
            'content'   => 'Content2',
        ]);

        Post::create([
            'author_id' => $author2->id,
            'title'     => 'Post3',
            'content'   => 'Content3',
        ]);

        Post::create([
            'author_id' => $author3->id,
            'title'     => 'Post4',
            'content'   => 'Content4',
        ]);
    }

    public function testHasManyThrough()
    {
        // 测试关联获取
        $country = Country::find(1);
        $this->assertNotNull($country);

        $posts = $country->posts;
        $this->assertCount(3, $posts);
        $this->assertEquals('Post1', $posts[0]->title);

        // 测试预加载
        $country = Country::with(['posts'])->find(1);
        $this->assertCount(3, $country->posts);

        // 测试关联统计
        $country = Country::withCount('posts')->find(1);
        $this->assertEquals(3, $country->posts_count);

        // 测试条件查询
        $posts = $country->posts()->where('title', 'like', '%1%')->select();
        $this->assertCount(1, $posts);
        $this->assertEquals('Post1', $posts[0]->title);

        // 测试排序
        $posts = $country->posts()->order('title', 'desc')->select();
        $this->assertEquals('Post3', $posts[0]->title);
    }

    public function testHasAndHasWhere()
    {
        // 测试has方法
        $countries = Country::has('posts')->select();
        $this->assertCount(2, $countries);

        // 测试hasNot方法
        $countries = Country::hasNot('posts')->select();
        $this->assertCount(0, $countries);

        // 测试hasWhere方法 - 基本条件
        $countries = Country::hasWhere('posts', [
            ['title', 'like', '%Post1%'],
        ])->select();
        $this->assertCount(1, $countries);
        $this->assertEquals('China', $countries[0]->name);

        // 测试hasWhere方法 - 闭包查询
        $countries = Country::hasWhere('posts', function ($query) {
            $query->where('title', 'Post1');
        })->select();
        $this->assertCount(1, $countries);
        $this->assertEquals('China', $countries[0]->name);

        // 测试hasWhere方法 - OR条件查询
        $countries = Country::hasWhere('posts', function ($query) {
            $query->where('title', 'Post1')
                ->whereOr('title', 'Post2');
        })->select();
        $this->assertCount(1, $countries);
        $this->assertEquals('China', $countries[0]->name);

        // 测试hasWhere方法 - 多字段组合查询
        $countries = Country::hasWhere('posts', function ($query) {
            $query->where([
                ['title', 'like', '%Post%'],
                ['content', 'like', '%Content%']
            ]);
        })->select();
        $this->assertCount(2, $countries);

        // 测试hasWhere方法 - 复杂组合查询
        $countries = Country::hasWhere('posts', function ($query) {
            $query->where('title', 'like', '%Post%')
                ->where(function ($query) {
                    $query->where('content', 'Content1')
                        ->whereOr('content', 'Content2');
                });
        })->select();
        $this->assertCount(1, $countries);
        $this->assertEquals('China', $countries[0]->name);
    }
}

class Country extends Model
{
    protected $autoWriteTimestamp = true;

    public function posts()
    {
        return $this->hasManyThrough(
            Post::class,
            Author::class,
            'country_id',
            'author_id',
            'id',
            'id'
        );
    }
}

class Author extends Model
{
    protected $autoWriteTimestamp = true;
}

class Post extends Model
{
    protected $autoWriteTimestamp = true;
}
