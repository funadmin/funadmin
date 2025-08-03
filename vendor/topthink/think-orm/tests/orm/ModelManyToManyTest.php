<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelManyToManyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_student`;',
            'CREATE TABLE `test_student` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_course`;',
            'CREATE TABLE `test_course` (
                `id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL DEFAULT "",
                `credit` int NOT NULL DEFAULT 0,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_student_course`;',
            'CREATE TABLE `test_student_course` (
                `id` int NOT NULL AUTO_INCREMENT,
                `student_id` int NOT NULL,
                `course_id` int NOT NULL,
                `score` decimal(5,2) DEFAULT NULL,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_student_id` (`student_id`),
                KEY `idx_course_id` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_student`;');
        Db::execute('TRUNCATE TABLE `test_course`;');
        Db::execute('TRUNCATE TABLE `test_student_course`;');

        // 创建测试数据
        $student1 = StudentModel::create([
            'name'  => 'student1',
            'email' => 'student1@example.com',
        ]);

        $student2 = StudentModel::create([
            'name'  => 'student2',
            'email' => 'student2@example.com',
        ]);

        $course1 = CourseModel::create([
            'title'  => 'Math',
            'credit' => 3,
        ]);

        $course2 = CourseModel::create([
            'title'  => 'English',
            'credit' => 2,
        ]);

        $course3 = CourseModel::create([
            'title'  => 'Physics',
            'credit' => 4,
        ]);

        // 建立关联关系
        $student1->courses()->attach($course1->id, ['score' => 85.5]);
        $student1->courses()->attach($course2->id, ['score' => 92.0]);
        $student2->courses()->attach($course2->id, ['score' => 88.5]);
        $student2->courses()->attach($course3->id, ['score' => 90.0]);
    }

    public function testManyToManySync()
    {
        // 测试基本同步功能
        $student = StudentModel::find(1);
        $result  = $student->courses()->sync([2, 3]); // 同步为English和Physics课程

        $this->assertTrue([3] === $result['attached']); // 新增Physics
        $this->assertTrue([1] === $result['detached']); // 移除Math

        $courses = $student->courses()->select();
        $this->assertCount(2, $courses);
        $this->assertEquals(['English', 'Physics'], $courses->column('title'));

        // 测试带额外数据的同步
        $syncData = [
            2 => ['score' => 95.0], // 更新English成绩
            3 => ['score' => 88.0], // 更新Physics成绩
        ];
        $result = $student->courses()->sync($syncData);

        $this->assertTrue([2, 3] === $result['updated']); // 更新了两门课的成绩

        $courses = $student->courses()->select();
        foreach ($courses as $course) {
            if ('English' === $course->title) {
                $this->assertEquals(95.0, $course->pivot->score);
            } elseif ('Physics' === $course->title) {
                $this->assertEquals(88.0, $course->pivot->score);
            }
        }

        // 测试清空后重新同步
        $result = $student->courses()->sync([]);
        $this->assertTrue([2, 3] === $result['detached']); // 移除所有课程
        $this->assertCount(0, $student->courses()->select());

        // 测试同步单个ID
        $result = $student->courses()->sync([1 => ['score' => 91.0]]);
        $this->assertTrue([1] === $result['attached']);

        $course = $student->courses()->find();
        $this->assertEquals('Math', $course->title);
        $this->assertEquals(91.0, $course->pivot->score);
    }

    public function testWherePivot()
    {
        // 测试基本等值查询
        $student = StudentModel::find(1);
        $courses = $student->courses()->wherePivot('score', '>=', 90)->select();
        $this->assertCount(1, $courses);
        $this->assertEquals('English', $courses[0]->title);
        $this->assertEquals(92.0, $courses[0]->pivot->score);

        // 测试范围查询
        $courses = $student->courses()->wherePivot('score', 'between', [80, 90])->select();
        $this->assertCount(1, $courses);
        $this->assertEquals('Math', $courses[0]->title);
        $this->assertEquals(85.5, $courses[0]->pivot->score);

        // 测试复杂条件查询
        $student = StudentModel::find(2);
        $courses = $student->courses()
            ->wherePivot('score', '>', 85)
            ->wherePivot('score', '<', 95)
            ->select();
        $this->assertCount(2, $courses);
        $this->assertEquals([88.5, 90.0], [$courses[0]->pivot->score, $courses[1]->pivot->score]);

        // 测试wherePivot与where组合查询
        $courses = $student->courses()
            ->where('credit', '>', 2)
            ->wherePivot('score', '>=', 90)
            ->select();
        $this->assertCount(1, $courses);
        $this->assertEquals('Physics', $courses[0]->title);
        $this->assertEquals(90.0, $courses[0]->pivot->score);
    }

    public function testManyToManyRelation()
    {
        // 测试关联获取
        $student = StudentModel::find(1);
        $this->assertNotNull($student);

        $courses = $student->courses;
        $this->assertCount(2, $courses);
        $this->assertEquals('Math', $courses[0]->title);

        // 测试预加载
        $student = StudentModel::with(['courses'])->find(1);
        $this->assertCount(2, $student->courses);

        // 测试中间表数据
        $student = StudentModel::find(1);
        $course  = $student->courses()->where('test_course.title', 'Math')->find();
        $this->assertEquals(85.5, $course->pivot->score);

        // 测试关联统计
        $student = StudentModel::withCount('courses')->find(1);
        $this->assertEquals(2, $student->courses_count);

        // 测试新增关联
        $student = StudentModel::find(2);
        $result  = $student->courses()->attach(1, ['score' => 87.5]);
        $this->assertEquals(87.5, $result->score);

        // 测试解除关联
        $result = $student->courses()->detach(1);
        $this->assertEquals(1, $result);

        // 测试基础 hasNot 查询
        $student->courses()->detach([2, 3]);
        $students = StudentModel::hasNot('courses')->select();
        $this->assertCount(1, $students);
        $this->assertEquals(['student2'], $students->column('name'));
    }

    public function testHasAndHasWhere()
    {
        // 测试基础has查询
        $students = StudentModel::has('courses')->select();
        $this->assertCount(2, $students);
        $this->assertEquals(['student1', 'student2'], $students->column('name'));

        // 添加课程
        $student = StudentModel::find(1);
        $student->courses()->attach([3], ['score' => 98.0]);

        // 测试带条件的has查询
        $students = StudentModel::has('courses', '>=', 3)->select();
        $this->assertCount(1, $students);
        $this->assertEquals('student1', $students[0]->name);

        // 测试hasWhere查询
        $students = StudentModel::hasWhere('courses', ['title' => 'English'])->select();
        $this->assertCount(2, $students);
        $this->assertEquals(['student1', 'student2'], $students->column('name'));

        // 测试hasWhere带pivot条件查询
        $students = StudentModel::hasWhere('courses', ['pivot.score' => 92.0])->select();
        $this->assertCount(1, $students);
        $this->assertEquals('student1', $students[0]->name);

        // 测试复杂条件查询
        $students = StudentModel::hasWhere('courses', function ($query) {
            $query->where('credit', '>', 2)
                ->where('pivot.score', '>=', 95);
        })->select();
        $this->assertCount(1, $students);
        $this->assertEquals('student1', $students[0]->name);

        // 测试多重条件组合
        $students = StudentModel::hasWhere('courses', ['credit' => 2])
            ->where('name', 'like', '%1%')
            ->select();
        $this->assertCount(1, $students);
        $this->assertEquals('student1', $students[0]->name);
    }

}

class StudentModel extends Model
{
    protected $table              = 'test_student';
    protected $autoWriteTimestamp = true;

    public function courses()
    {
        return $this->belongsToMany(CourseModel::class, 'student_course', 'course_id', 'student_id');
    }
}

class CourseModel extends Model
{
    protected $table              = 'test_course';
    protected $autoWriteTimestamp = true;

    public function students()
    {
        return $this->belongsToMany(StudentModel::class, 'student_course', 'student_id', 'course_id');
    }
}
