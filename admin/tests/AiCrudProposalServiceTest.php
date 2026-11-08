<?php

declare(strict_types=1);

namespace Tests;

use app\admin\ai\service\AiCrudProposalService;
use PHPUnit\Framework\TestCase;

final class AiCrudProposalServiceTest extends TestCase
{
    public function test_form_schema_proposal_with_relations_is_accepted(): void
    {
        $relations = [['type' => 'hasMany', 'name' => 'comments', 'table' => 'fun_comment', 'localField' => 'id', 'foreignField' => 'article_id']];
        $normalized = (new AiCrudProposalService())->validate([
            'schema_version' => 1, 'proposal_type' => 'form_schema', 'module_id' => 1,
            'form_schema' => [
                'schemaVersion' => 2, 'key' => 'article', 'title' => '文章',
                'database' => ['connection' => 'mysql', 'table' => 'fun_article', 'source' => 'created'],
                'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                    'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]],
                'relations' => $relations,
            ],
        ]);

        self::assertSame('comments', $normalized['form_schema']['relations'][0]['name']);
    }
}
