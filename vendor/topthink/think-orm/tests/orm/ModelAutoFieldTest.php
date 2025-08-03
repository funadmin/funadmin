<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelAutoFieldTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_auto_field`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_auto_field` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `type` varchar(32) DEFAULT NULL,
     `ip` varchar(15) DEFAULT NULL,
     `user_agent` varchar(255) DEFAULT NULL,
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_auto_field`;');
    }

    public function testAutoField()
    {
        // 测试基本自动写入
        $data = ['name' => 'test1'];
        $model = new TestAutoModel($data);
        $model->save();

        $this->assertNotEmpty($model->ip);
        $this->assertEquals('PHPUnit Test Agent', $model->user_agent);
        $this->assertEquals('normal', $model->type);
        $this->assertNotEmpty($model->create_time);

        // 测试条件自动写入
        $data = ['name' => 'test2', 'status' => 1];
        $model = new TestAutoModel($data);
        $model->save();

        $this->assertEquals('special', $model->type);
        $this->assertNotEmpty($model->ip);
        $this->assertNotEmpty($model->user_agent);

        // 测试更新时的自动写入
        $model = TestAutoModel::find(1);
        $model->name = 'updated';
        $model->save();

        $this->assertNotEmpty($model->update_time);
        $this->assertNotEmpty($model->ip);
        $this->assertEquals('PHPUnit Update Agent', $model->user_agent);
    }
}

class TestAutoModel extends Model
{
    protected $table = 'test_auto_field';
    protected $autoWriteTimestamp = true;

    // 设置自动完成的字段
    protected $insert = [
        'type',
        'ip',
        'user_agent' => 'PHPUnit Test Agent',
    ];

    protected $update = [
        'type',
        'ip',
        'user_agent' => 'PHPUnit Update Agent',
    ];

    protected function setIpAttr($value, $data)
    {
        return '127.0.0.1';
    }

    protected function setTypeAttr($value, $data)
    {
        // 当状态为1时，自动设置type为special
        if (isset($data['status']) && $data['status'] == 1) {
            return 'special';
        }
        return $value ?: 'normal';
    }
}