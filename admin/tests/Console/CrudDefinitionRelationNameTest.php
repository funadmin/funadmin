<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\DefinitionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** 关系名直接成为生成模型的方法名，不能覆盖 Eloquent 模型自带方法。 */
final class CrudDefinitionRelationNameTest extends TestCase
{
    #[DataProvider('modelMethodNames')]
    public function test_relation_name_overriding_model_method_is_rejected(string $name): void
    {
        $data = CrudDefinitionFixture::make('console-relation-name')->toArray();
        $data['relations'] = [['name' => $name, 'type' => 'hasMany', 'field' => 'id', 'target' => 'ConsoleComment', 'targetField' => 'article_id']];
        try {
            (new DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
            self::fail('覆盖模型方法的关系名应被拒绝');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('关系名与模型内置方法冲突：' . $name, $exception->getMessage());
        }
    }

    public static function modelMethodNames(): array
    {
        return ['delete' => ['delete'], 'save' => ['save']];
    }
}
