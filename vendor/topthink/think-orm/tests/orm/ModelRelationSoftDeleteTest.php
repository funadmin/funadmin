<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;

class ModelRelationSoftDeleteTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_user_soft`;',
            'CREATE TABLE `test_user_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_profile_soft`;',
            'CREATE TABLE `test_profile_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `email` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_post_soft`;',
            'CREATE TABLE `test_post_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `title` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_tag_soft`;',
            'CREATE TABLE `test_tag_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_post_tag_soft`;',
            'CREATE TABLE `test_post_tag_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `post_id` int NOT NULL,
                `tag_id` int NOT NULL,
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_account_soft`;',
            'CREATE TABLE `test_account_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `account` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_info_soft`;',
            'CREATE TABLE `test_info_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `account_id` int NOT NULL,
                `email` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_comment_soft`;',
            'CREATE TABLE `test_comment_soft` (
                `id` int NOT NULL AUTO_INCREMENT,
                `commentable_id` int NOT NULL,
                `commentable_type` varchar(255) NOT NULL,
                `content` varchar(255) NOT NULL DEFAULT "",
                `delete_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user_soft`;');
        Db::execute('TRUNCATE TABLE `test_profile_soft`;');
        Db::execute('TRUNCATE TABLE `test_post_soft`;');
        Db::execute('TRUNCATE TABLE `test_tag_soft`;');
        Db::execute('TRUNCATE TABLE `test_post_tag_soft`;');
        Db::execute('TRUNCATE TABLE `test_account_soft`;');
        Db::execute('TRUNCATE TABLE `test_info_soft`;');
        Db::execute('TRUNCATE TABLE `test_comment_soft`;');

        // 创建测试数据
        $user1 = UserSoftModel::create(['name' => 'user1']);
        $user2 = UserSoftModel::create(['name' => 'user2']);

        ProfileSoftModel::create(['user_id' => $user1->id, 'email' => 'user1@test.com']);
        ProfileSoftModel::create(['user_id' => $user2->id, 'email' => 'user2@test.com']);

        $post1 = PostSoftModel::create(['user_id' => $user1->id, 'title' => 'Post 1']);
        $post2 = PostSoftModel::create(['user_id' => $user1->id, 'title' => 'Post 2']);
        PostSoftModel::create(['user_id' => $user2->id, 'title' => 'Post 3']);

        $tag1 = TagSoftModel::create(['name' => 'Tag 1']);
        $tag2 = TagSoftModel::create(['name' => 'Tag 2']);

        // 建立文章和标签的关联
        $post1->tags()->attach($tag1->id);
        $post1->tags()->attach($tag2->id);
        $post2->tags()->attach($tag1->id);

        // 创建账户和信息数据
        $account1 = AccountSoftModel::create(['user_id' => $user1->id, 'account' => 'account1']);
        $account2 = AccountSoftModel::create(['user_id' => $user2->id, 'account' => 'account2']);

        InfoSoftModel::create(['account_id' => $account1->id, 'email' => 'info1@test.com']);
        InfoSoftModel::create(['account_id' => $account2->id, 'email' => 'info2@test.com']);

        // 创建评论数据
        CommentSoftModel::create([
            'commentable_id'   => $post1->id,
            'commentable_type' => PostSoftModel::class,
            'content'          => 'Comment 1',
        ]);
        CommentSoftModel::create([
            'commentable_id'   => $post2->id,
            'commentable_type' => PostSoftModel::class,
            'content'          => 'Comment 2',
        ]);
    }

    public function testHasOneWithSoftDelete()
    {
        $user1 = UserSoftModel::find(1);
        $user2 = UserSoftModel::find(2);

        // 软删除关联数据
        $user1->profile->delete();

        // 测试 has 方法
        $users = UserSoftModel::has('profile')->select();
        $this->assertCount(1, $users);
        $this->assertEquals('user2', $users[0]->name);

        // 测试 hasWhere 方法
        $users = UserSoftModel::hasWhere('profile', ['email' => 'user2@test.com'])->select();
        $this->assertCount(1, $users);
        $this->assertEquals('user2', $users[0]->name);
    }

    public function testHasManyWithSoftDelete()
    {
        $user1 = UserSoftModel::find(1);

        // 软删除一篇文章
        PostSoftModel::destroy(1);

        // 测试 has 方法
        $users = UserSoftModel::has('posts')->select();
        $this->assertCount(2, $users);

        // 测试 hasWhere 方法
        $users = UserSoftModel::hasWhere('posts', ['title' => 'Post 2'])->select();
        $this->assertCount(1, $users);
    }

    public function testBelongsToManyWithSoftDelete()
    {
        $post1 = PostSoftModel::find(1);
        $tag1  = TagSoftModel::find(1);

        // 软删除标签
        $tag1->delete();

        // 测试 has 方法
        $posts = PostSoftModel::has('tags')->select();
        $this->assertCount(1, $posts);

        // 测试 hasWhere 方法
        $posts = PostSoftModel::hasWhere('tags', ['name' => 'Tag 2'])->select();
        $this->assertCount(1, $posts);
    }

    public function testHasOneThroughWithSoftDelete()
    {
        $info = InfoSoftModel::find(1);

        // 软删除中间表数据
        $info->delete();

        // 测试 has 方法
        $users = AccountSoftModel::has('info')->select();
        $this->assertCount(1, $users);
        $this->assertEquals('account2', $users[0]->account);

        // 测试 hasWhere 方法
        $users = AccountSoftModel::hasWhere('info', ['email' => 'info2@test.com'])->select();
        $this->assertCount(1, $users);
        $this->assertEquals('account2', $users[0]->account);
    }

    public function testMorphManyWithSoftDelete()
    {
        $post1    = PostSoftModel::find(1);
        $comment1 = CommentSoftModel::find(1);

        // 软删除评论
        $comment1->delete();

        // 测试 has 方法
        $posts = PostSoftModel::has('comments')->select();
        $this->assertCount(1, $posts);
        $this->assertEquals('Post 2', $posts[0]->title);

        // 测试 hasWhere 方法
        $posts = PostSoftModel::hasWhere('comments', ['content' => 'Comment 2'])->select();
        $this->assertCount(1, $posts);
        $this->assertEquals('Post 2', $posts[0]->title);
    }
}

class UserSoftModel extends Model
{
    protected $table = 'test_user_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function profile()
    {
        return $this->hasOne(ProfileSoftModel::class, 'user_id');
    }

    public function posts()
    {
        return $this->hasMany(PostSoftModel::class, 'user_id');
    }
}

class ProfileSoftModel extends Model
{
    protected $table = 'test_profile_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function user()
    {
        return $this->belongsTo(UserSoftModel::class, 'user_id');
    }
}

class PostSoftModel extends Model
{
    protected $table = 'test_post_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function user()
    {
        return $this->belongsTo(UserSoftModel::class, 'user_id');
    }

    public function tags()
    {
        return $this->belongsToMany(TagSoftModel::class, PostTagSoftModel::class, 'post_id', 'tag_id');
    }

    public function comments()
    {
        return $this->morphMany(CommentSoftModel::class, 'commentable');
    }
}

class TagSoftModel extends Model
{
    protected $table = 'test_tag_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function posts()
    {
        return $this->belongsToMany(PostSoftModel::class, PostTagSoftModel::class, 'tag_id', 'post_id');
    }
}

class PostTagSoftModel extends \think\model\Pivot
{
    protected $table = 'test_post_tag_soft';
    use SoftDelete;
}

class AccountSoftModel extends Model
{
    protected $table = 'test_account_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function user()
    {
        return $this->belongsTo(UserSoftModel::class, 'user_id');
    }

    public function info()
    {
        return $this->hasOne(InfoSoftModel::class, 'account_id');
    }
}

class InfoSoftModel extends Model
{
    protected $table = 'test_info_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function account()
    {
        return $this->belongsTo(AccountSoftModel::class, 'account_id');
    }
}

class CommentSoftModel extends Model
{
    protected $table = 'test_comment_soft';
    use SoftDelete;
    protected $deleteTime = 'delete_time';

    public function commentable()
    {
        return $this->morphTo();
    }
}
