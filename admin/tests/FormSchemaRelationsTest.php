<?php

declare(strict_types=1);

namespace Tests;

use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaException;
use app\common\form\schema\FormSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormSchemaRelationsTest extends TestCase
{
    public function test_empty_or_missing_relations_do_not_change_hash(): void
    {
        $missing = $this->compile($this->document());
        $empty = $this->compile($this->document(['relations' => []]));

        self::assertSame($missing->hash(), $empty->hash());
        self::assertSame($missing->canonicalJson(), $empty->canonicalJson());
        self::assertArrayNotHasKey('relations', $missing->document());
        self::assertArrayNotHasKey('relations', $empty->document());
    }

    public function test_valid_relations_are_kept_in_canonical_document(): void
    {
        $relations = [
            ['type' => 'hasOne', 'name' => 'profile', 'table' => 'fun_article_profile', 'localField' => 'id', 'foreignField' => 'article_id'],
            [
                'type' => 'belongsToMany', 'name' => 'tags', 'table' => 'fun_tag', 'localField' => 'id', 'foreignField' => 'id',
                'pivot' => ['table' => 'fun_article_tag', 'localKey' => 'article_id', 'foreignKey' => 'tag_id'],
            ],
        ];
        $compiled = $this->compile($this->document(['relations' => $relations]));

        $expected = array_map(static function (array $relation): array {
            ksort($relation, SORT_STRING);
            if (isset($relation['pivot'])) ksort($relation['pivot'], SORT_STRING);
            return $relation;
        }, $relations);
        self::assertSame($expected, $compiled->document()['relations']);
        self::assertNotSame($this->compile($this->document())->hash(), $compiled->hash());
    }

    public function test_local_field_may_reference_a_document_field(): void
    {
        $compiled = $this->compile($this->document(['relations' => [
            ['type' => 'hasMany', 'name' => 'comments', 'table' => 'fun_comment', 'localField' => 'title', 'foreignField' => 'article_title'],
        ]]));

        self::assertSame('title', $compiled->document()['relations'][0]['localField']);
    }

    #[DataProvider('invalidRelations')]
    public function test_invalid_relations_are_rejected(mixed $relations, string $path): void
    {
        try {
            $this->compile($this->document(['relations' => $relations]));
            self::fail('非法 relations 应被拒绝');
        } catch (FormSchemaException $exception) {
            self::assertSame($path, $exception->schemaPath());
        }
    }

    public static function invalidRelations(): array
    {
        $hasOne = ['type' => 'hasOne', 'name' => 'profile', 'table' => 'fun_profile', 'localField' => 'id', 'foreignField' => 'article_id'];
        $pivot = ['table' => 'fun_article_tag', 'localKey' => 'article_id', 'foreignKey' => 'tag_id'];
        $belongsToMany = ['type' => 'belongsToMany', 'name' => 'tags', 'table' => 'fun_tag', 'localField' => 'id', 'foreignField' => 'id', 'pivot' => $pivot];

        return [
            'relations 不是数组' => [['profile' => $hasOne], '/relations'],
            '关联不是对象' => [['profile'], '/relations/0'],
            '未知关联属性' => [[$hasOne + ['extra' => 1]], '/relations/0/extra'],
            '非法名称标识符' => [[array_replace($hasOne, ['name' => 'Bad-Name'])], '/relations/0/name'],
            '非法表名标识符' => [[array_replace($hasOne, ['table' => 'fun profile'])], '/relations/0/table'],
            '非法外键标识符' => [[array_replace($hasOne, ['foreignField' => ''])], '/relations/0/foreignField'],
            '名称重复' => [[$hasOne, array_replace($hasOne, ['table' => 'fun_other'])], '/relations/1/name'],
            '名称与字段冲突' => [[array_replace($hasOne, ['name' => 'title'])], '/relations/0/name'],
            'localField 不存在' => [[array_replace($hasOne, ['localField' => 'missing_field'])], '/relations/0/localField'],
            'belongsToMany 缺 pivot' => [[array_diff_key($belongsToMany, ['pivot' => true])], '/relations/0/pivot'],
            'pivot 缺列' => [[array_replace($belongsToMany, ['pivot' => array_diff_key($pivot, ['foreignKey' => true])])], '/relations/0/pivot/foreignKey'],
            'pivot 未知属性' => [[array_replace($belongsToMany, ['pivot' => $pivot + ['extra' => 'x']])], '/relations/0/pivot/extra'],
            'hasOne 带 pivot' => [[$hasOne + ['pivot' => $pivot]], '/relations/0/pivot'],
            'type=belongsTo' => [[array_replace($hasOne, ['type' => 'belongsTo'])], '/relations/0/type'],
        ];
    }

    /** 关联名生成为模型方法名，不能覆盖 Eloquent 模型自带方法。 */
    #[DataProvider('modelMethodNames')]
    public function test_relation_name_overriding_model_method_is_rejected(string $name): void
    {
        try {
            $this->compile($this->document(['relations' => [
                ['type' => 'hasMany', 'name' => $name, 'table' => 'fun_comment', 'localField' => 'id', 'foreignField' => 'article_id'],
            ]]));
            self::fail('覆盖模型方法的关联名应被拒绝');
        } catch (FormSchemaException $exception) {
            self::assertSame('/relations/0/name', $exception->schemaPath());
            self::assertSame('关联名称与模型内置方法冲突：' . $name, $exception->getMessage());
        }
    }

    public static function modelMethodNames(): array
    {
        return ['delete' => ['delete'], 'save' => ['save']];
    }

    private function compile(array $document): \app\common\form\schema\FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile($document);
    }

    private function document(array $extra = []): array
    {
        return [
            'schemaVersion' => 2, 'key' => 'article', 'title' => '文章',
            'database' => ['connection' => 'mysql', 'table' => 'fun_article', 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]],
        ] + $extra;
    }
}
