<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelMorphTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_comment`;',
            'CREATE TABLE `test_comment` (
                `id` int NOT NULL AUTO_INCREMENT,
                `content` text NOT NULL,
                `morphable_type` varchar(50) NOT NULL,
                `morphable_id` int NOT NULL,
                `user_id` int NOT NULL,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_morphable` (`morphable_type`, `morphable_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_article`;',
            'CREATE TABLE `test_article` (
                `id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `content` text NOT NULL,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_video`;',
            'CREATE TABLE `test_video` (
                `id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `url` varchar(255) NOT NULL,
                `duration` int NOT NULL DEFAULT 0,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_comment`;');
        Db::execute('TRUNCATE TABLE `test_article`;');
        Db::execute('TRUNCATE TABLE `test_video`;');

        // 创建测试数据
        $post = MorphPostModel::create([
            'title'   => 'Test Post',
            'content' => 'Post content',
        ]);

        $video = VideoModel::create([
            'title'    => 'Test Video',
            'url'      => 'https://example.com/video.mp4',
            'duration' => 300,
        ]);

        // 创建评论数据
        CommentModel::create([
            'content'        => 'Comment on post',
            'morphable_type' => MorphPostModel::class,
            'morphable_id'   => $post->id,
            'user_id'        => 1,
        ]);

        CommentModel::create([
            'content'        => 'Another comment on post',
            'morphable_type' => MorphPostModel::class,
            'morphable_id'   => $post->id,
            'user_id'        => 2,
        ]);

        CommentModel::create([
            'content'        => 'Comment on video',
            'morphable_type' => VideoModel::class,
            'morphable_id'   => $video->id,
            'user_id'        => 1,
        ]);
    }

    public function testMorphOne()
    {
        $post = MorphPostModel::find(1);
        $this->assertNotNull($post);

        // 测试获取最新的一条评论
        $latestComment = $post->latestComment;
        $this->assertNotNull($latestComment);
        $this->assertEquals('Another comment on post', $latestComment->content);

        // 测试预加载
        $post = MorphPostModel::with(['latestComment'])->find(1);
        $this->assertNotNull($post->latestComment);

        // 测试has方法
        $posts = MorphPostModel::has('latestComment')->select();
        $this->assertCount(1, $posts);

        $videos = VideoModel::has('latestComment')->select();
        $this->assertCount(1, $videos);

        // 测试hasWhere方法
        $posts = MorphPostModel::hasWhere('latestComment', ['user_id' => 2])->select();
        $this->assertCount(1, $posts);

        $videos = VideoModel::hasWhere('latestComment', ['user_id' => 1])->select();
        $this->assertCount(1, $videos);

        // 测试组合查询
        $posts = MorphPostModel::hasWhere('latestComment', function ($query) {
            $query->where('user_id', 1)->whereOr('user_id', 2);
        })->select();
        $this->assertCount(1, $posts);

        // 测试hasNot方法
        $posts = MorphPostModel::hasNot('latestComment')->select();
        $this->assertCount(0, $posts);

        $videos = VideoModel::hasNot('latestComment')->select();
        $this->assertCount(0, $videos);
    }

    public function testMorphMany()
    {
        $post = MorphPostModel::find(1);
        $this->assertNotNull($post);

        // 测试获取所有评论
        $comments = $post->comments;
        $this->assertCount(2, $comments);

        // 测试预加载
        $post = MorphPostModel::with(['comments'])->find(1);
        $this->assertCount(2, $post->comments);

        // 测试关联统计
        $post = MorphPostModel::withCount('comments')->find(1);
        $this->assertEquals(2, $post->comments_count);

        // 测试新增关联
        $result = $post->comments()->save([
            'content' => 'New comment on post',
            'user_id' => 3,
        ]);
        $this->assertNotNull($result);
        $this->assertEquals(3, $post->comments()->count());

        // 测试视频评论
        $video = VideoModel::find(1);
        $this->assertCount(1, $video->comments);

        // 测试has方法
        $posts = MorphPostModel::has('comments')->select();
        $this->assertCount(1, $posts);

        $videos = VideoModel::has('comments')->select();
        $this->assertCount(1, $videos);

        // 测试hasWhere方法 - 基本条件
        $posts = MorphPostModel::hasWhere('comments', ['user_id' => 1])->select();
        $this->assertCount(1, $posts);

        $videos = VideoModel::hasWhere('comments', ['user_id' => 2])->select();
        $this->assertCount(0, $videos);

        // 测试hasWhere方法 - 闭包查询
        $posts = MorphPostModel::hasWhere('comments', function ($query) {
            $query->where('content', 'like', '%post%');
        })->select();
        $this->assertCount(1, $posts);

        // 测试hasWhere方法 - OR条件查询
        $posts = MorphPostModel::hasWhere('comments', function ($query) {
            $query->where('user_id', 1)
                ->whereOr('user_id', 2);
        })->select();
        $this->assertCount(1, $posts);

        // 测试hasWhere方法 - 复杂组合查询
        $posts = MorphPostModel::hasWhere('comments', function ($query) {
            $query->where('content', 'like', '%post%')
                ->where(function ($query) {
                    $query->where('user_id', 1)
                        ->whereOr('user_id', 2);
                });
        })->select();
        $this->assertCount(1, $posts);
        // 测试hasNot方法
        $posts = MorphPostModel::hasNot('comments')->select();
        $this->assertCount(0, $posts);

        $videos = VideoModel::hasNot('comments')->select();
        $this->assertCount(0, $videos);
    }

    public function testMorphTo()
    {
        $comment = CommentModel::find(1);
        $this->assertNotNull($comment);

        // 测试获取关联的内容
        $commentable = $comment->commentable;
        $this->assertInstanceOf(MorphPostModel::class, $commentable);
        $this->assertEquals('Test Post', $commentable->title);

        // 测试预加载
        $comment = CommentModel::with(['commentable'])->find(3);
        $this->assertInstanceOf(VideoModel::class, $comment->commentable);
        $this->assertEquals('Test Video', $comment->commentable->title);
    }
}

class MorphPostModel extends Model
{
    protected $table = 'test_article';

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'morphable');
    }

    public function latestComment()
    {
        return $this->morphOne(CommentModel::class, 'morphable')->order('id', 'desc');
    }
}

class VideoModel extends Model
{
    protected $table              = 'test_video';
    protected $autoWriteTimestamp = true;

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'morphable');
    }

    public function latestComment()
    {
        return $this->morphOne(CommentModel::class, 'morphable')
            ->order('id', 'desc');
    }
}

class CommentModel extends Model
{
    protected $table              = 'test_comment';
    protected $autoWriteTimestamp = true;

    public function commentable()
    {
        return $this->morphTo('morphable');
    }
}
