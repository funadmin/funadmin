<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelEventTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_event_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_event_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_event_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'status' => 1],
            ['id' => 2, 'name' => 'test2', 'status' => 0],
        ];
    }

    public function testInsertEvents()
    {
        EventModel::resetEventFlags();
        $data = ['name' => 'test3', 'status' => 1];
        $result = EventModel::create($data);

        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeInsertCalled'], 'before_insert event not triggered');
        $this->assertTrue($flags['afterInsertCalled'], 'after_insert event not triggered');
        $this->assertEquals('modified_write_test3', $result->name);
    }

    public function testUpdateEvents()
    {
        // 先创建一条记录
        $record = EventModel::create(['name' => 'test4', 'status' => 1]);

        EventModel::resetEventFlags();
        // 更新记录
        $record->name = 'new_name';
        $record->save();

        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeUpdateCalled'], 'before_update event not triggered');
        $this->assertTrue($flags['afterUpdateCalled'], 'after_update event not triggered');
        $this->assertEquals('updated_write_new_name', $record->name);
    }

    public function testDeleteEvents()
    {
        // 创建两条记录
        $record1 = EventModel::create(['name' => 'test5', 'status' => 1]);
        $record2 = EventModel::create(['name' => 'test6', 'status' => 0]);

        // 尝试删除状态为1的记录
        EventModel::resetEventFlags();
        $record1->delete();
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeDeleteCalled'], 'before_delete event not triggered');
        $this->assertTrue($flags['afterDeleteCalled'], 'after_delete event not triggered');

        // 尝试删除状态为0的记录
        EventModel::resetEventFlags();
        $result = $record2->delete();
        $this->assertFalse($result);
        $flags  = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeDeleteCalled'], 'before_delete event not triggered');
        $this->assertFalse($flags['afterDeleteCalled'], 'after_delete event should not be triggered');

        // 验证记录2仍然存在
        $this->assertNotNull(EventModel::find($record2->id));
    }

    public function testWriteEvents()
    {
        // 测试插入时的写入事件
        EventModel::resetEventFlags();
        $record = EventModel::create(['name' => 'test7', 'status' => 1]);
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeWriteCalled'], 'before_write event not triggered on insert');
        $this->assertTrue($flags['afterWriteCalled'], 'after_write event not triggered on insert');
        $this->assertEquals('modified_write_test7', $record->name);

        // 测试更新时的写入事件
        EventModel::resetEventFlags();
        $record->name = 'test8';
        $record->save();
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeWriteCalled'], 'before_write event not triggered on update');
        $this->assertTrue($flags['afterWriteCalled'], 'after_write event not triggered on update');
        $this->assertEquals('updated_write_test8', $record->name);
    }
}

class EventModel extends Model
{
    protected $table = 'test_event_model';
    protected $autoWriteTimestamp = true;

    protected static $beforeInsertCalled = false;
    protected static $afterInsertCalled = false;
    protected static $beforeUpdateCalled = false;
    protected static $afterUpdateCalled = false;
    protected static $beforeDeleteCalled = false;
    protected static $afterDeleteCalled = false;
    protected static $beforeWriteCalled = false;
    protected static $afterWriteCalled = false;

    public static function resetEventFlags(): void
    {
        self::$beforeInsertCalled = false;
        self::$afterInsertCalled = false;
        self::$beforeUpdateCalled = false;
        self::$afterUpdateCalled = false;
        self::$beforeDeleteCalled = false;
        self::$afterDeleteCalled = false;
        self::$beforeWriteCalled = false;
        self::$afterWriteCalled = false;
    }

    public static function getEventFlags(): array
    {
        return [
            'beforeInsertCalled' => self::$beforeInsertCalled,
            'afterInsertCalled' => self::$afterInsertCalled,
            'beforeUpdateCalled' => self::$beforeUpdateCalled,
            'afterUpdateCalled' => self::$afterUpdateCalled,
            'beforeDeleteCalled' => self::$beforeDeleteCalled,
            'afterDeleteCalled' => self::$afterDeleteCalled,
            'beforeWriteCalled' => self::$beforeWriteCalled,
            'afterWriteCalled' => self::$afterWriteCalled,
        ];
    }

    public function onBeforeInsert($model)
    {
        self::$beforeInsertCalled = true;
        // 在插入前修改数据
        $model->name = 'modified_' . $model->name;
    }

    public function onAfterInsert($model)
    {
        self::$afterInsertCalled = true;
    }

    public function onBeforeUpdate($model)
    {
        self::$beforeUpdateCalled = true;
        // 在更新前修改数据
        $model->name = 'updated_' . $model->name;
    }

    public function onAfterUpdate($model)
    {
        self::$afterUpdateCalled = true;
    }

    public function onBeforeDelete($model)
    {
        self::$beforeDeleteCalled = true;
        // 可以在删除前执行一些验证
        if ($model->status === 0) {
            return false; // 阻止删除
        }
    }

    public function onAfterDelete($model)
    {
        self::$afterDeleteCalled = true;
    }

    public function onBeforeWrite($model)
    {
        self::$beforeWriteCalled = true;
        // 在写入前修改数据
        $model->name = 'write_' . $model->name;
    }

    public function onAfterWrite($model)
    {
        self::$afterWriteCalled = true;
    }
}