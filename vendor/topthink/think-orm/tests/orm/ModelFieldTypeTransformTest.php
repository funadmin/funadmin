<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;
use think\model\contract\FieldTypeTransform;
use think\model\contract\Modelable;

class ModelFieldTypeTransformTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_transform`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_transform` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `point` varchar(50) NULL DEFAULT '',
     `status` varchar(32) NOT NULL DEFAULT '',
     `create_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_transform`;');
    }

    public function testFieldTypeTransform()
    {
        // 测试保存Point对象
        $model        = new TransformModel;
        $point        = new Point(10, 20);
        $model->point = $point;
        $model->save();

        // 测试读取Point对象
        $model = TransformModel::find($model->id);
        $this->assertInstanceOf(Point::class, $model->point);
        $this->assertEquals(10, $model->point->x);
        $this->assertEquals(20, $model->point->y);

        // 测试更新Point对象
        $model->point = new Point(30, 40);
        $model->save();
        $model = TransformModel::find($model->id);
        $this->assertEquals(30, $model->point->x);
        $this->assertEquals(40, $model->point->y);

        // 测试设置null值
        $model->point = null;
        $model->save();
        $model = TransformModel::find($model->id);
        $this->assertNull($model->point);

        // 测试设置无效值
        $model->point = 'invalid';
        $model->save();
        $model = TransformModel::find($model->id);
        $this->assertNull($model->point);

        // 测试批量赋值
        $model = new TransformModel;
        $model->save([
            'point' => new Point(50, 60),
        ]);
        $model = TransformModel::find($model->id);
        $this->assertEquals(50, $model->point->x);
        $this->assertEquals(60, $model->point->y);

        // 测试查询条件
        $count = TransformModel::where('point', new Point(50, 60))->count();
        $this->assertEquals(1, $count);
    }
}

class Point implements FieldTypeTransform
{
    public function __construct(
        public int $x = 0,
        public int $y = 0
    ) {}

    public function __toString(): string
    {
        return json_encode([
            'x' => $this->x,
            'y' => $this->y,
        ]);
    }

    public static function get(mixed $value, Modelable $model): ?static
    {
        if (empty($value)) {
            return null;
        }

        $data = json_decode($value, true);
        if (! is_array($data) || ! isset($data['x'], $data['y'])) {
            return null;
        }

        return new static($data['x'], $data['y']);
    }

    public static function set($value, Modelable $model) : mixed
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof static ) {
            return json_encode([
                'x' => $value->x,
                'y' => $value->y,
            ]);
        }

        return '';
    }
}

class TransformModel extends Model
{
    protected $table = 'test_transform';

    protected $type = [
        'point' => Point::class,
    ];
}
