<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;
use app\common\crud\DefinitionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** 核心目标的后端分层文件路径必须是实体派生的标准布局，否则命名空间与 PSR-4 不一致。 */
final class CrudCoreTargetPathTest extends TestCase
{
    public function test_canonical_core_layer_paths_pass(): void
    {
        (new DefinitionValidator())->validate(CrudDefinition::fromArray(CrudDefinitionFixture::make('core-path')->toArray()), sys_get_temp_dir());
        self::addToAssertionCount(1);
    }

    /** @return array<string, array{string, string, string}> */
    public static function wrongPaths(): array
    {
        return [
            'wrong dir' => ['model', 'app/admin/business/other_dir/model/CorePath.php', 'app/admin/business/core_path/model/CorePath.php'],
            'wrong layer' => ['service', 'app/admin/business/core_path/model/CorePathService.php', 'app/admin/business/core_path/service/CorePathService.php'],
            'wrong class' => ['controller', 'app/admin/business/core_path/controller/OtherController.php', 'app/admin/business/core_path/controller/CorePathController.php'],
            'member api' => ['memberApiController', 'app/api/controller/generated/CorePathController.php', 'app/api/controller/business/CorePathController.php'],
        ];
    }

    #[DataProvider('wrongPaths')]
    public function test_non_canonical_core_layer_path_is_rejected(string $layer, string $path, string $expected): void
    {
        $data = CrudDefinitionFixture::make('core-path')->toArray();
        $data['generationTargets'][$layer] = $path;
        try {
            (new DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
            self::fail('非标准分层路径应被拒绝');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("generationTargets.{$layer} 必须为 {$expected}", $exception->getMessage());
        }
    }

    public function test_canonical_relation_model_path_passes(): void
    {
        $data = CrudDefinitionFixture::make('core-path')->toArray();
        $data['generationTargets']['relationModel:CoreCategory'] = 'app/admin/business/core_path/model/relation/CoreCategory.php';
        (new DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
        self::addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function wrongRelationPaths(): array
    {
        return [
            'other module dir' => ['app/admin/business/other_dir/model/relation/CoreCategory.php'],
            'outside business' => ['app/custom/model/relation/CoreCategory.php'],
        ];
    }

    #[DataProvider('wrongRelationPaths')]
    public function test_non_canonical_relation_model_path_is_rejected(string $path): void
    {
        $data = CrudDefinitionFixture::make('core-path')->toArray();
        $data['generationTargets']['relationModel:CoreCategory'] = $path;
        try {
            (new DefinitionValidator())->validate(CrudDefinition::fromArray($data), sys_get_temp_dir());
            self::fail('非标准关联模型路径应被拒绝');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('generationTargets.relationModel:CoreCategory 必须为 app/admin/business/core_path/model/relation/CoreCategory.php', $exception->getMessage());
        }
    }

    /** 插件目标不接受 generationTargets，关联模型路径由 PluginCrudTarget 派生，不受核心规则影响。 */
    public function test_plugin_target_is_unaffected_by_core_path_rules(): void
    {
        $root = sys_get_temp_dir() . '/core-path-plugin-' . bin2hex(random_bytes(6));
        mkdir($root . '/plugins', 0700, true);
        try {
            (new \app\common\plugin\sdk\PluginScaffolder($root . '/plugins'))->scaffold('pathkit', '路径插件', false, true, true);
            $definition = (new \app\admin\development\service\FormCrudDefinitionFactory())
                ->forBusinessTarget(CrudDefinitionFixture::make('core-path'), ['type' => 'plugin', 'pluginCode' => 'pathkit']);
            (new DefinitionValidator())->validate($definition, $root);
            self::addToAssertionCount(1);
        } finally {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
